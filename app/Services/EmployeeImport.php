<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Payroll\Tax\TerMonthlyWithTrueUp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Reads an employee spreadsheet, validates every row against the current company,
 * and imports only when no row has an error ("resolve before you approve").
 */
class EmployeeImport
{
    public const MAX_ROWS = 1000;

    /** Column => required. Order is the template order. */
    public const COLUMNS = [
        'employee_no' => true,
        'full_name' => true,
        'job_title' => true,
        'hire_date' => true,
        'work_email' => false,
        'department' => false,
        'manager_employee_no' => false,
        'location' => false,
        'contract_type' => false,
        'base_salary' => false,
        'tax_status' => false,
    ];

    public const CONTRACT_TYPES = ['permanent', 'fixed_term', 'intern', 'part_time'];

    /** @return list<array<string, string>> */
    public function read(UploadedFile $file): array
    {
        $reader = match (strtolower($file->getClientOriginalExtension())) {
            'csv', 'txt' => new CsvReader,
            'xlsx' => new XlsxReader,
            default => throw new RuntimeException('Upload a .csv or .xlsx file.'),
        };

        $reader->open($file->getRealPath());
        $header = null;
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v), $row->toArray());

                    if ($header === null) {
                        $header = array_map(fn ($h) => str_replace([' ', '-'], '_', strtolower(ltrim($h, "\u{FEFF}"))), $cells);

                        continue;
                    }
                    if (count(array_filter($cells, fn ($c) => $c !== '')) === 0) {
                        continue;
                    }
                    if (count($rows) >= self::MAX_ROWS) {
                        throw new RuntimeException('Files are limited to '.self::MAX_ROWS.' rows. Split the file and import in parts.');
                    }
                    $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        if ($header === null) {
            throw new RuntimeException('The file is empty.');
        }

        $missing = array_keys(array_filter(self::COLUMNS, fn ($required, $col) => $required && ! in_array($col, $header, true), ARRAY_FILTER_USE_BOTH));
        if ($missing) {
            throw new RuntimeException('Missing required columns: '.implode(', ', $missing).'. Download the template to see the expected headers.');
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array{line: int, data: array<string, mixed>, errors: list<string>, warnings: list<string>}>
     */
    public function validate(array $rows, Company $company): array
    {
        $existingNos = Employee::withoutGlobalScopes()->where('company_id', $company->id)->pluck('employee_no')->map(fn ($n) => strtolower($n))->all();
        $departments = Department::withoutGlobalScopes()->where('company_id', $company->id)->pluck('name')->map(fn ($n) => strtolower($n))->all();
        $locations = Location::withoutGlobalScopes()->where('company_id', $company->id)->pluck('name')->map(fn ($n) => strtolower($n))->all();
        $fileNos = array_count_values(array_map(fn ($r) => strtolower($r['employee_no'] ?? ''), $rows));

        $result = [];
        foreach ($rows as $i => $row) {
            $errors = [];
            $warnings = [];
            $data = [];
            foreach (self::COLUMNS as $col => $required) {
                $data[$col] = trim((string) ($row[$col] ?? ''));
                if ($required && $data[$col] === '') {
                    $errors[] = str_replace('_', ' ', ucfirst($col)).' is required.';
                }
            }

            $no = strtolower($data['employee_no']);
            if ($no !== '' && ($fileNos[$no] ?? 0) > 1) {
                $errors[] = "Employee ID {$data['employee_no']} appears more than once in the file.";
            }
            if ($no !== '' && in_array($no, $existingNos, true)) {
                $errors[] = "Employee ID {$data['employee_no']} already exists in {$company->name}.";
            }

            if ($data['hire_date'] !== '') {
                $date = $this->parseDate($data['hire_date']);
                if (! $date) {
                    $errors[] = 'Hire date must look like 2024-03-18 or 18/03/2024.';
                } elseif ($date->isAfter(now()->addYear())) {
                    $errors[] = 'Hire date is more than a year in the future.';
                } else {
                    $data['hire_date'] = $date->toDateString();
                }
            }

            if ($data['work_email'] !== '' && ! filter_var($data['work_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Work email is not a valid address.';
            }

            if ($data['department'] !== '' && ! in_array(strtolower($data['department']), $departments, true)) {
                $warnings[] = "Department “{$data['department']}” will be created.";
            }
            if ($data['location'] !== '' && ! in_array(strtolower($data['location']), $locations, true)) {
                $errors[] = "Location “{$data['location']}” does not exist. Add it in settings first or fix the spelling.";
            }

            $manager = strtolower($data['manager_employee_no']);
            if ($manager !== '') {
                if ($manager === $no) {
                    $errors[] = 'An employee cannot be their own manager.';
                } elseif (! in_array($manager, $existingNos, true) && ! isset($fileNos[$manager])) {
                    $errors[] = "Manager {$data['manager_employee_no']} is not in this company or in the file.";
                }
            }

            $data['contract_type'] = $data['contract_type'] === '' ? 'permanent' : strtolower(str_replace([' ', '-'], '_', $data['contract_type']));
            if (! in_array($data['contract_type'], self::CONTRACT_TYPES, true)) {
                $errors[] = 'Contract type must be one of: '.implode(', ', self::CONTRACT_TYPES).'.';
            }

            if ($data['base_salary'] !== '') {
                $amount = str_replace([',', ' '], '', $data['base_salary']);
                if (! is_numeric($amount) || (float) $amount < 0) {
                    $errors[] = 'Base salary must be a positive number without currency symbols.';
                } else {
                    $data['base_salary'] = $amount;
                }
            } else {
                $warnings[] = 'No salary given; add it on the profile before payroll.';
            }

            if ($data['tax_status'] !== '' && $company->country_code === 'ID' && ! preg_match(TerMonthlyWithTrueUp::STATUS_PATTERN, $data['tax_status'])) {
                $errors[] = 'Tax status must be a PTKP code such as TK/0 or K/1.';
            }

            $result[] = ['line' => $i + 2, 'data' => $data, 'errors' => $errors, 'warnings' => $warnings];
        }

        return $result;
    }

    /**
     * Import validated rows in one transaction. Managers are linked after all rows exist,
     * so a file may list a report before their manager.
     *
     * @param  list<array{line: int, data: array<string, mixed>, errors: list<string>, warnings: list<string>}>  $validated
     */
    public function import(array $validated, Company $company, User $by): int
    {
        if (collect($validated)->contains(fn ($r) => $r['errors'] !== [])) {
            throw new RuntimeException('Fix every row with an error before importing.');
        }

        return DB::transaction(function () use ($validated, $company, $by) {
            $deptIds = Department::withoutGlobalScopes()->where('company_id', $company->id)->get()->mapWithKeys(fn ($d) => [strtolower($d->name) => $d->id])->all();
            $locIds = Location::withoutGlobalScopes()->where('company_id', $company->id)->get()->mapWithKeys(fn ($l) => [strtolower($l->name) => $l->id])->all();
            $created = [];

            foreach ($validated as ['data' => $d]) {
                $deptKey = strtolower($d['department']);
                if ($deptKey !== '' && ! isset($deptIds[$deptKey])) {
                    $deptIds[$deptKey] = Department::create(['company_id' => $company->id, 'name' => $d['department']])->id;
                }

                $employee = Employee::create([
                    'company_id' => $company->id,
                    'employee_no' => $d['employee_no'],
                    'full_name' => $d['full_name'],
                    'job_title' => $d['job_title'],
                    'work_email' => $d['work_email'] ?: null,
                    'hire_date' => $d['hire_date'],
                    'department_id' => $deptIds[$deptKey] ?? null,
                    'location_id' => $locIds[strtolower($d['location'])] ?? null,
                    'status' => 'active',
                ]);
                $employee->contracts()->create(['type' => $d['contract_type'], 'start_date' => $d['hire_date']]);

                if ($d['base_salary'] !== '') {
                    $employee->compensations()->create([
                        'effective_from' => $d['hire_date'],
                        'base_salary' => $d['base_salary'],
                        'currency' => $company->base_currency,
                        'tax_status' => $d['tax_status'] ?: null,
                        'reason' => 'Imported from spreadsheet',
                        'created_by' => $by->id,
                    ]);
                }
                $created[strtolower($d['employee_no'])] = [$employee, strtolower($d['manager_employee_no'])];
            }

            $allIds = Employee::withoutGlobalScopes()->where('company_id', $company->id)->get(['id', 'employee_no'])
                ->mapWithKeys(fn ($e) => [strtolower($e->employee_no) => $e->id])->all();
            foreach ($created as [$employee, $managerNo]) {
                if ($managerNo !== '') {
                    $employee->update(['manager_id' => $allIds[$managerNo]]);
                }
            }

            return count($created);
        });
    }

    private function parseDate(string $value): ?Carbon
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'j/n/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
