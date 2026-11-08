<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('only accepts PTKP codes as the tax status of an Indonesian employee', function () {
    $company = Company::factory()->create();
    $employee = Employee::factory()->for($company)->create(['hire_date' => '2024-01-15']);
    $hr = memberOf($company, Role::HrAdmin);

    // Payroll cannot calculate "K1" or "K/4", so they must not get saved.
    foreach (['K1', 'k/1', 'K/4'] as $status) {
        signIn($hr)->post(route('employees.compensation.store', $employee), [
            'effective_from' => '2026-04-01', 'base_salary' => 21000000, 'tax_status' => $status, 'reason' => 'Annual review 2026',
        ])->assertSessionHasErrors(['tax_status' => 'Tax status must be a PTKP code such as TK/0 or K/1.']);
    }
    signIn($hr)->post(route('employees.compensation.store', $employee), [
        'effective_from' => '2026-04-01', 'base_salary' => 21000000, 'tax_status' => 'K/3', 'reason' => 'Annual review 2026',
    ])->assertSessionHasNoErrors();

    expect($employee->compensations()->pluck('tax_status')->all())->toBe(['K/3']);
});

it('leaves the tax status free-form outside Indonesia', function () {
    $company = Company::factory()->generic()->create();
    $employee = Employee::factory()->for($company)->create(['hire_date' => '2024-01-15']);

    signIn(memberOf($company, Role::HrAdmin))->post(route('employees.compensation.store', $employee), [
        'effective_from' => '2026-04-01', 'base_salary' => 4000, 'tax_status' => '1257L', 'reason' => 'Annual review 2026',
    ])->assertSessionHasNoErrors();

    expect($employee->compensations()->value('tax_status'))->toBe('1257L');
});
