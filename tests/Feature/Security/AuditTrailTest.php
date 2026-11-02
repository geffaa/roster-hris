<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Compensation;
use App\Models\Employee;
use App\Models\EmployeeIdentifier;
use Database\Seeders\RoleSeeder;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->employee = Employee::factory()->for($this->company)->create(['hire_date' => '2024-01-15']);
    $this->identifier = $this->employee->identifiers()->create(['type' => 'npwp', 'value' => '09.254.300.1-011.000']);
    $this->hr = memberOf($this->company, Role::HrAdmin);
});

it('records a salary change with the author and company', function () {
    signIn($this->hr)->post(route('employees.compensation.store', $this->employee), [
        'effective_from' => '2026-04-01', 'base_salary' => 21000000, 'tax_status' => 'K/1', 'reason' => 'Annual review 2026',
    ])->assertRedirect();

    $audit = Audit::where('auditable_type', Compensation::class)->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->event)->toBe('created')
        ->and($audit->user_id)->toBe($this->hr->id)
        ->and($audit->company_id)->toBe($this->company->id)
        ->and((float) $audit->new_values['base_salary'])->toBe(21000000.0);
});

it('reveals an identifier to HR and logs who looked', function () {
    signIn($this->hr)->postJson(route('employees.identifiers.reveal', [$this->employee, $this->identifier]))
        ->assertOk()
        ->assertJson(['value' => '09.254.300.1-011.000']);

    $audit = Audit::where('event', 'revealed')->first();
    expect($audit->user_id)->toBe($this->hr->id)
        ->and($audit->company_id)->toBe($this->company->id)
        ->and($audit->auditable_id)->toBe($this->identifier->id);
});

it('never writes identifier values or personal data into the audit log', function () {
    $this->employee->update(['personal_data' => ['phone' => '+62 812 0000 1111'], 'job_title' => 'Lead Engineer']);

    $dump = Audit::all()->map(fn ($a) => json_encode([$a->old_values, $a->new_values]))->implode(' ');

    expect($dump)->not->toContain('09.254.300')
        ->and($dump)->not->toContain('0000 1111')
        ->and($dump)->toContain('Lead Engineer');
});

it('stores identifiers encrypted with only the last four digits in clear', function () {
    $raw = DB::table('employee_identifiers')->where('id', $this->identifier->id)->first();

    expect($raw->value)->not->toContain('254.300')
        ->and($raw->value_last4)->toBe('1000')
        ->and(EmployeeIdentifier::find($this->identifier->id)->value)->toBe('09.254.300.1-011.000');
});
