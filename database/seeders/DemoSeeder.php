<?php

namespace Database\Seeders;

use App\Enums\IdentifierType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Synthetic demo data: two companies, ~40 employees, one login per role.
 * All names, IDs and salaries are fictional. Password for every demo login: "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $nusantara = Company::create([
            'name' => 'Nusantara Kreatif', 'legal_name' => 'PT Nusantara Kreatif Digital',
            'country_code' => 'ID', 'base_currency' => 'IDR', 'timezone' => 'Asia/Jakarta', 'locale' => 'id',
        ]);
        $northwind = Company::create([
            'name' => 'Northwind Studio', 'legal_name' => 'Northwind Studio Ltd',
            'country_code' => 'GB', 'base_currency' => 'GBP', 'timezone' => 'Europe/London', 'locale' => 'en',
        ]);

        $this->seedCompany($nusantara, $this->indonesia());
        $this->seedCompany($northwind, $this->generic());

        $this->login('Ardi Prasetyo', 'super@roster.test', [[$nusantara, Role::SuperAdmin, null], [$northwind, Role::SuperAdmin, null]]);
        $this->login('Dewi Lestari', 'hr@roster.test', [[$nusantara, Role::HrAdmin, 'NK-0003']]);
        $this->login('Rina Wulandari', 'payroll@roster.test', [[$nusantara, Role::PayrollOfficer, 'NK-0006']]);
        $this->login('Bima Saputra', 'manager@roster.test', [[$nusantara, Role::Manager, 'NK-0010']]);
        $this->login('Dimas Arya', 'employee@roster.test', [[$nusantara, Role::Employee, 'NK-0014']]);
        $this->login('Sekar Ayuningtyas', 'auditor@roster.test', [[$nusantara, Role::Auditor, null]]);
        $this->login('Hannah Brooks', 'uk.hr@roster.test', [[$northwind, Role::HrAdmin, 'NW-002']]);

        $this->call(TimeSeeder::class);
        $this->call(PayrollSeeder::class);
        $this->call(ClaimSeeder::class);
        $this->call(ActivitySeeder::class);
        $this->call(PolicySeeder::class);

        setPermissionsTeamId(null);
    }

    /** @param list<array{0: Company, 1: Role, 2: ?string}> $memberships */
    private function login(string $name, string $email, array $memberships): void
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make('password'), 'email_verified_at' => now()]);

        foreach ($memberships as $i => [$company, $role, $employeeNo]) {
            $user->companies()->attach($company->id, ['is_default' => $i === 0]);
            setPermissionsTeamId($company->id);
            $user->unsetRelation('roles');
            $user->assignRole($role->value);

            if ($employeeNo) {
                Employee::withoutGlobalScopes()->where('company_id', $company->id)->where('employee_no', $employeeNo)->update(['user_id' => $user->id]);
            }
        }

        $user->forceFill(['current_company_id' => $memberships[0][0]->id])->save();
    }

    private function seedCompany(Company $company, array $spec): void
    {
        $departments = collect($spec['departments'])->mapWithKeys(fn ($name) => [$name => Department::create(['company_id' => $company->id, 'name' => $name])]);
        $locations = collect($spec['locations'])->mapWithKeys(fn ($l) => [$l['name'] => Location::create(['company_id' => $company->id, ...$l])]);
        $byNo = [];

        foreach ($spec['people'] as $i => [$no, $name, $title, $dept, $managerNo, $location, $hired, $salary, $tax, $contract, $status]) {
            $employee = Employee::create([
                'company_id' => $company->id,
                'employee_no' => $no,
                'full_name' => $name,
                'job_title' => $title,
                'work_email' => strtolower(str_replace(' ', '.', $name)).'@'.$spec['domain'],
                'hire_date' => $hired,
                'department_id' => $departments[$dept]->id,
                'manager_id' => $managerNo ? $byNo[$managerNo]->id : null,
                'location_id' => $locations[$location]->id,
                'status' => $status,
                'personal_data' => ['phone' => $spec['phone']($i), 'birth_year' => 1984 + ($i * 7) % 16],
            ]);
            $byNo[$no] = $employee;

            $employee->contracts()->create([
                'type' => $contract,
                'start_date' => $hired,
                'end_date' => $contract === 'fixed_term' ? Carbon::parse('2026-12-31')->subDays(($i * 11) % 50)->toDateString() : null,
            ]);

            foreach ($spec['identifiers']($i) as $type => $value) {
                $employee->identifiers()->create(['type' => $type, 'value' => $value]);
            }

            // Effective-dated history: hire salary, then yearly reviews each 1 April up to the current one.
            $start = Carbon::parse($hired);
            $amount = round($salary * pow(0.94, max(0, 2026 - max(2023, $start->year))), -3 + ($company->base_currency === 'GBP' ? 1 : 0));
            $employee->compensations()->create([
                'effective_from' => $hired, 'base_salary' => $amount, 'currency' => $company->base_currency,
                'tax_status' => $tax, 'reason' => 'Starting salary',
            ]);
            for ($year = max(2024, $start->year + 1); $year <= 2026; $year++) {
                $amount = $year === 2026 ? $salary : round($amount / 0.94, $company->base_currency === 'GBP' ? -1 : -3);
                $employee->compensations()->create([
                    'effective_from' => "$year-04-01", 'base_salary' => $amount, 'currency' => $company->base_currency,
                    'tax_status' => $tax, 'reason' => 'Annual review '.$year,
                ]);
            }
        }
    }

    private function indonesia(): array
    {
        return [
            'domain' => 'nusantarakreatif.id',
            'departments' => ['Leadership', 'People & Culture', 'Finance', 'Engineering', 'Design', 'Operations', 'Sales'],
            'locations' => [
                ['name' => 'Jakarta HQ', 'address' => 'Jl. H.R. Rasuna Said Kav. 62, Kuningan, Jakarta Selatan', 'lat' => -6.229700, 'lng' => 106.829500, 'radius_m' => 120],
                ['name' => 'Bandung Studio', 'address' => 'Jl. Braga No. 44, Bandung', 'lat' => -6.917500, 'lng' => 107.609300, 'radius_m' => 100],
                ['name' => 'Remote', 'address' => null, 'lat' => null, 'lng' => null, 'radius_m' => 0, 'allow_remote' => true],
            ],
            'phone' => fn (int $i) => '+62 812 '.str_pad((string) (3100 + $i * 37), 4, '0', STR_PAD_LEFT).' '.str_pad((string) (2200 + $i * 53), 4, '0', STR_PAD_LEFT),
            'identifiers' => fn (int $i) => [
                IdentifierType::Nik->value => '3174'.str_pad((string) (105591 + $i * 1373), 6, '0', STR_PAD_LEFT).str_pad((string) (1000 + $i * 17), 6, '0', STR_PAD_LEFT),
                IdentifierType::Npwp->value => '09.'.str_pad((string) (254 + $i * 3), 3, '0', STR_PAD_LEFT).'.'.str_pad((string) (300 + $i * 11), 3, '0', STR_PAD_LEFT).'.'.($i % 9 + 1).'-'.str_pad((string) (11 + $i), 3, '0', STR_PAD_LEFT).'.000',
                IdentifierType::BpjsKesehatan->value => '0001'.str_pad((string) (48712000 + $i * 731), 9, '0', STR_PAD_LEFT),
                IdentifierType::BpjsKetenagakerjaan->value => '19'.str_pad((string) (40021000 + $i * 977), 9, '0', STR_PAD_LEFT),
            ],
            // no, name, title, department, manager, location, hire date, monthly base (IDR), PTKP, contract, status
            'people' => [
                ['NK-0001', 'Hendra Kusuma', 'Managing Director', 'Leadership', null, 'Jakarta HQ', '2019-02-04', 68000000, 'K/2', 'permanent', 'active'],
                ['NK-0002', 'Larasati Putri', 'Head of People', 'People & Culture', 'NK-0001', 'Jakarta HQ', '2019-06-17', 42000000, 'K/1', 'permanent', 'active'],
                ['NK-0003', 'Dewi Lestari', 'HR Generalist', 'People & Culture', 'NK-0002', 'Jakarta HQ', '2021-03-01', 16500000, 'TK/0', 'permanent', 'active'],
                ['NK-0004', 'Yusuf Hidayat', 'Talent Acquisition Specialist', 'People & Culture', 'NK-0002', 'Jakarta HQ', '2023-08-14', 13000000, 'K/0', 'permanent', 'active'],
                ['NK-0005', 'Agus Santoso', 'Finance Manager', 'Finance', 'NK-0001', 'Jakarta HQ', '2019-09-02', 38000000, 'K/3', 'permanent', 'active'],
                ['NK-0006', 'Rina Wulandari', 'Payroll Officer', 'Finance', 'NK-0005', 'Jakarta HQ', '2020-11-09', 17500000, 'K/1', 'permanent', 'active'],
                ['NK-0007', 'Fajar Nugroho', 'Accountant', 'Finance', 'NK-0005', 'Jakarta HQ', '2022-05-23', 14000000, 'TK/0', 'permanent', 'active'],
                ['NK-0008', 'Citra Maharani', 'Head of Engineering', 'Engineering', 'NK-0001', 'Jakarta HQ', '2019-04-15', 58000000, 'K/1', 'permanent', 'active'],
                ['NK-0009', 'Galih Pratama', 'Staff Engineer', 'Engineering', 'NK-0008', 'Remote', '2020-01-20', 45000000, 'K/2', 'permanent', 'active'],
                ['NK-0010', 'Bima Saputra', 'Engineering Lead, Platform', 'Engineering', 'NK-0008', 'Jakarta HQ', '2020-07-06', 41000000, 'K/1', 'permanent', 'active'],
                ['NK-0011', 'Nadia Rahmawati', 'Senior Backend Engineer', 'Engineering', 'NK-0010', 'Jakarta HQ', '2021-02-15', 32000000, 'TK/0', 'permanent', 'active'],
                ['NK-0012', 'Reza Firmansyah', 'Backend Engineer', 'Engineering', 'NK-0010', 'Remote', '2022-09-12', 24000000, 'TK/0', 'permanent', 'on_leave'],
                ['NK-0013', 'Putri Anggraini', 'Frontend Engineer', 'Engineering', 'NK-0010', 'Bandung Studio', '2023-01-09', 21500000, 'K/0', 'permanent', 'active'],
                ['NK-0014', 'Dimas Arya', 'Mobile Engineer', 'Engineering', 'NK-0010', 'Jakarta HQ', '2023-06-05', 19500000, 'TK/0', 'permanent', 'active'],
                ['NK-0015', 'Ilham Maulana', 'QA Engineer', 'Engineering', 'NK-0010', 'Bandung Studio', '2024-03-18', 15500000, 'TK/0', 'fixed_term', 'active'],
                ['NK-0016', 'Salsabila Zahra', 'Engineering Intern', 'Engineering', 'NK-0010', 'Jakarta HQ', '2026-08-03', 4500000, 'TK/0', 'intern', 'active'],
                ['NK-0017', 'Ayu Kartika', 'Design Lead', 'Design', 'NK-0001', 'Bandung Studio', '2020-03-16', 36000000, 'K/1', 'permanent', 'active'],
                ['NK-0018', 'Kevin Wijaya', 'Product Designer', 'Design', 'NK-0017', 'Bandung Studio', '2022-02-07', 22000000, 'TK/0', 'permanent', 'active'],
                ['NK-0019', 'Melati Sari', 'UX Researcher', 'Design', 'NK-0017', 'Remote', '2024-10-01', 18500000, 'K/0', 'fixed_term', 'active'],
                ['NK-0020', 'Teguh Wibowo', 'Operations Manager', 'Operations', 'NK-0001', 'Jakarta HQ', '2019-11-11', 30000000, 'K/2', 'permanent', 'active'],
                ['NK-0021', 'Siti Nurhaliza', 'Office Coordinator', 'Operations', 'NK-0020', 'Jakarta HQ', '2021-07-19', 9500000, 'K/1', 'permanent', 'active'],
                ['NK-0022', 'Joko Susilo', 'IT Support', 'Operations', 'NK-0020', 'Jakarta HQ', '2022-12-05', 10500000, 'K/2', 'permanent', 'active'],
                ['NK-0023', 'Maya Indriani', 'Head of Sales', 'Sales', 'NK-0001', 'Jakarta HQ', '2020-05-04', 40000000, 'K/1', 'permanent', 'active'],
                ['NK-0024', 'Andi Setiawan', 'Account Executive', 'Sales', 'NK-0023', 'Jakarta HQ', '2023-04-10', 16000000, 'TK/0', 'permanent', 'active'],
                ['NK-0025', 'Wulan Purnama', 'Account Executive', 'Sales', 'NK-0023', 'Bandung Studio', '2025-01-13', 14500000, 'K/0', 'fixed_term', 'active'],
                ['NK-0026', 'Bayu Ramadhan', 'Sales Development Rep', 'Sales', 'NK-0023', 'Jakarta HQ', '2022-08-01', 11000000, 'TK/0', 'permanent', 'resigned'],
            ],
        ];
    }

    private function generic(): array
    {
        return [
            'domain' => 'northwind.studio',
            'departments' => ['Leadership', 'Studio', 'Engineering', 'Operations', 'Client Services'],
            'locations' => [
                ['name' => 'London Shoreditch', 'address' => '41 Rivington Street, London EC2A 3QQ', 'lat' => 51.526100, 'lng' => -0.079900, 'radius_m' => 80],
                ['name' => 'Manchester', 'address' => '12 Tariff Street, Manchester M1 2FF', 'lat' => 53.482900, 'lng' => -2.232000, 'radius_m' => 80],
            ],
            'phone' => fn (int $i) => '+44 7700 900'.str_pad((string) (100 + $i * 7), 3, '0', STR_PAD_LEFT),
            // "QQ" National Insurance prefixes are reserved for examples and never issued.
            'identifiers' => fn (int $i) => [IdentifierType::NationalInsurance->value => sprintf('QQ%02d%02d%02d%s', 12 + $i, 34 + $i, 56 + $i, 'ABCD'[$i % 4])],
            // no, name, title, department, manager, location, hire date, monthly base (GBP), tax code, contract, status
            'people' => [
                ['NW-001', 'Oliver Grant', 'Studio Director', 'Leadership', null, 'London Shoreditch', '2018-09-03', 9800, '1257L', 'permanent', 'active'],
                ['NW-002', 'Hannah Brooks', 'People Partner', 'Operations', 'NW-001', 'London Shoreditch', '2021-01-11', 4600, '1257L', 'permanent', 'active'],
                ['NW-003', 'Priya Shah', 'Head of Engineering', 'Engineering', 'NW-001', 'London Shoreditch', '2019-05-20', 8200, '1257L', 'permanent', 'active'],
                ['NW-004', 'Tom Whitaker', 'Senior Developer', 'Engineering', 'NW-003', 'Manchester', '2020-10-05', 5900, '1257L', 'permanent', 'active'],
                ['NW-005', 'Aisha Rahman', 'Developer', 'Engineering', 'NW-003', 'Manchester', '2023-02-27', 4300, '1257L', 'permanent', 'active'],
                ['NW-006', 'Callum Reid', 'Developer', 'Engineering', 'NW-003', 'London Shoreditch', '2024-06-17', 4000, 'BR', 'fixed_term', 'active'],
                ['NW-007', 'Grace Okafor', 'Design Director', 'Studio', 'NW-001', 'London Shoreditch', '2019-03-04', 7400, '1257L', 'permanent', 'active'],
                ['NW-008', 'Leo Fernandes', 'Senior Designer', 'Studio', 'NW-007', 'London Shoreditch', '2021-09-13', 5200, '1257L', 'permanent', 'active'],
                ['NW-009', 'Mia Turner', 'Designer', 'Studio', 'NW-007', 'Manchester', '2023-11-06', 3800, '1257L', 'permanent', 'on_leave'],
                ['NW-010', 'Ethan Clarke', 'Account Director', 'Client Services', 'NW-001', 'London Shoreditch', '2020-02-17', 6900, '1257L', 'permanent', 'active'],
                ['NW-011', 'Sophie Patel', 'Account Manager', 'Client Services', 'NW-010', 'London Shoreditch', '2022-07-04', 4100, '1257L', 'permanent', 'active'],
                ['NW-012', 'Jack Morrison', 'Producer', 'Client Services', 'NW-010', 'Manchester', '2024-01-08', 3900, '1257L', 'fixed_term', 'active'],
                ['NW-013', 'Emily Hughes', 'Office Manager', 'Operations', 'NW-002', 'London Shoreditch', '2022-03-21', 3300, '1257L', 'permanent', 'active'],
                ['NW-014', 'Noah Bennett', 'Finance Lead', 'Operations', 'NW-001', 'London Shoreditch', '2021-05-10', 5600, '1257L', 'permanent', 'active'],
            ],
        ];
    }
}
