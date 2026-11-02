<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->lead = Employee::factory()->for($this->company)->create(['full_name' => 'Lead Person']);
    $this->report = Employee::factory()->for($this->company)->create(['full_name' => 'Direct Report', 'manager_id' => $this->lead->id]);
    $this->other = Employee::factory()->for($this->company)->create(['full_name' => 'Someone Else']);
    $this->report->identifiers()->create(['type' => 'nik', 'value' => '3174010101900001']);
    $this->report->compensations()->create(['effective_from' => '2025-01-01', 'base_salary' => 15000000, 'currency' => 'IDR', 'reason' => 'Starting salary']);
});

it('lets a manager see a direct report but not other employees', function () {
    $manager = memberOf($this->company, Role::Manager, $this->lead);

    signIn($manager)->get(route('employees.show', $this->report))->assertOk();
    signIn($manager)->get(route('employees.show', $this->other))->assertForbidden();
});

it('shows a manager only their team in the directory', function () {
    $manager = memberOf($this->company, Role::Manager, $this->lead);

    signIn($manager)->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'team')
            ->has('employees.data', 1)
            ->where('employees.data.0.full_name', 'Direct Report'));
});

it('masks identifiers and hides salary from a manager', function () {
    $manager = memberOf($this->company, Role::Manager, $this->lead);

    $response = signIn($manager)->get(route('employees.show', $this->report));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('identifiers.0.masked', '•••• 0001')
        ->where('can.reveal', false)
        ->where('compensation', null));
    expect($response->getContent())->not->toContain('3174010101900001');

    signIn($manager)->post(route('employees.identifiers.reveal', [$this->report, $this->report->identifiers()->first()]))->assertForbidden();
});

it('sends an employee from the directory to their own profile', function () {
    $user = memberOf($this->company, Role::Employee, $this->report);

    signIn($user)->get(route('employees.index'))->assertRedirect(route('employees.show', $this->report));
    signIn($user)->get(route('employees.show', $this->other))->assertForbidden();
});

it('lets an employee see their own salary history', function () {
    $user = memberOf($this->company, Role::Employee, $this->report);

    signIn($user)->get(route('employees.show', $this->report))
        ->assertInertia(fn (Assert $page) => $page->has('compensation', 1)->where('can.manageCompensation', false));
});

it('lets an auditor read salaries but not change them', function () {
    $auditor = memberOf($this->company, Role::Auditor);

    signIn($auditor)->get(route('employees.show', $this->report))
        ->assertInertia(fn (Assert $page) => $page->has('compensation', 1)->where('can.reveal', false));

    signIn($auditor)->post(route('employees.compensation.store', $this->report), [
        'effective_from' => now()->toDateString(), 'base_salary' => 16000000, 'reason' => 'Raise',
    ])->assertForbidden();
});

it('validates a salary change before saving it', function () {
    $hr = memberOf($this->company, Role::HrAdmin);

    signIn($hr)->post(route('employees.compensation.store', $this->report), ['base_salary' => -5])
        ->assertSessionHasErrors(['effective_from', 'base_salary', 'reason']);
});
