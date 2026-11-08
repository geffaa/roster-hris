<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

function csvFile(array $rows): UploadedFile
{
    $lines = array_map(fn ($r) => implode(',', array_map(fn ($c) => str_contains($c, ',') ? "\"$c\"" : $c, $r)), $rows);

    return UploadedFile::fake()->createWithContent('people.csv', implode("\n", $lines));
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['base_currency' => 'IDR']);
    Location::create(['company_id' => $this->company->id, 'name' => 'Jakarta HQ']);
    Department::create(['company_id' => $this->company->id, 'name' => 'Engineering']);
    $this->boss = Employee::factory()->for($this->company)->create(['employee_no' => 'NK-0001']);
    $this->hr = memberOf($this->company, Role::HrAdmin);
    $this->header = ['employee_no', 'full_name', 'job_title', 'hire_date', 'work_email', 'department', 'manager_employee_no', 'location', 'contract_type', 'base_salary', 'tax_status'];
});

function previewToken($response): string
{
    return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
}

it('previews rows with errors and blocks the import', function () {
    $file = csvFile([
        $this->header,
        ['NK-0101', 'Rahma Aulia', 'CS Lead', '2026-11-02', 'rahma@example.id', 'Support', 'NK-0001', 'Jakarta HQ', 'permanent', '18500000', 'TK/0'],
        ['NK-0001', 'Duplicate Boss', 'X', '31/02/2024', 'not-an-email', '', 'NK-9999', 'Mars', 'contractor', 'Rp 5', ''],
    ]);

    $response = signIn($this->hr)->post(route('employees.import.preview'), ['file' => $file]);
    $token = previewToken($response);

    $this->get(route('employees.import.show', $token))->assertInertia(fn (Assert $page) => $page
        ->component('people/EmployeeImport')
        ->where('preview.rows.0.errors', [])
        ->where('preview.rows.0.warnings', ['Department “Support” will be created.'])
        ->where('preview.rows.1.errors', fn ($errors) => count($errors) === 7 && str_contains($errors[0], 'already exists')));

    $this->post(route('employees.import.store', $token))->assertSessionHasErrors('file');
    expect(Employee::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(1);
});

it('imports a clean file, links managers listed later, and records salaries', function () {
    $file = csvFile([
        $this->header,
        ['NK-0102', 'Bagus Wirawan', 'Engineer', '18/03/2024', '', 'Engineering', 'NK-0103', 'Jakarta HQ', 'permanent', '15,500,000', 'K/1'],
        ['NK-0103', 'Clara Setiadi', 'Engineering Manager', '2022-01-10', '', 'Engineering', 'NK-0001', '', 'permanent', '32000000', 'K/0'],
    ]);

    $token = previewToken(signIn($this->hr)->post(route('employees.import.preview'), ['file' => $file]));
    $this->post(route('employees.import.store', $token))->assertRedirect(route('employees.index'));

    $bagus = Employee::withoutGlobalScopes()->where('employee_no', 'NK-0102')->first();
    $clara = Employee::withoutGlobalScopes()->where('employee_no', 'NK-0103')->first();

    expect($bagus->company_id)->toBe($this->company->id)
        ->and($bagus->manager_id)->toBe($clara->id)
        ->and($clara->manager_id)->toBe($this->boss->id)
        ->and($bagus->hire_date->toDateString())->toBe('2024-03-18')
        ->and((float) $bagus->compensations()->first()->base_salary)->toBe(15500000.0)
        ->and($bagus->compensations()->first()->currency)->toBe('IDR');
});

it('flags a tax status that payroll cannot use', function () {
    $file = csvFile([
        $this->header,
        ['NK-0104', 'Dewi Lestari', 'Analyst', '2025-02-03', '', 'Engineering', '', '', 'permanent', '12000000', 'K1'],
    ]);

    $token = previewToken(signIn($this->hr)->post(route('employees.import.preview'), ['file' => $file]));

    $this->get(route('employees.import.show', $token))->assertInertia(fn (Assert $page) => $page
        ->where('preview.rows.0.errors', ['Tax status must be a PTKP code such as TK/0 or K/1.']));
});

it('rejects files without required columns', function () {
    $file = csvFile([['name', 'email'], ['A', 'a@example.id']]);

    signIn($this->hr)->post(route('employees.import.preview'), ['file' => $file])
        ->assertSessionHasErrors(['file' => 'Missing required columns: employee_no, full_name, job_title, hire_date. Download the template to see the expected headers.']);
});

it('does not let a manager import', function () {
    $manager = memberOf($this->company, Role::Manager);

    signIn($manager)->get(route('employees.import.create'))->assertForbidden();
    signIn($manager)->post(route('employees.import.preview'), ['file' => csvFile([$this->header])])->assertForbidden();
});

it('will not confirm a preview from another company', function () {
    $file = csvFile([$this->header, ['NK-0104', 'Dian', 'Analyst', '2025-05-05', '', '', '', '', '', '9000000', '']]);
    $token = previewToken(signIn($this->hr)->post(route('employees.import.preview'), ['file' => $file]));

    $other = Company::factory()->create();
    $this->hr->companies()->attach($other->id);
    setPermissionsTeamId($other->id);
    $this->hr->assignRole(Role::HrAdmin->value);
    $this->post(route('companies.switch', $other));

    $this->post(route('employees.import.store', $token))->assertNotFound();
});
