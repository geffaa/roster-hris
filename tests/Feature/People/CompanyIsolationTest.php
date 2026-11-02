<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->a = Company::factory()->create(['name' => 'Alpha']);
    $this->b = Company::factory()->generic()->create(['name' => 'Bravo']);
    $this->alice = Employee::factory()->for($this->a)->create(['full_name' => 'Alice Alpha']);
    $this->bob = Employee::factory()->for($this->b)->create(['full_name' => 'Bob Bravo']);
    $this->bobId = $this->bob->identifiers()->create(['type' => 'ni', 'value' => 'QQ123456C']);
    $this->hr = memberOf($this->a, Role::HrAdmin);
});

it('returns 404 for an employee of another company', function () {
    signIn($this->hr)->get(route('employees.show', $this->bob))->assertNotFound();
});

it('cannot add compensation to another company\'s employee', function () {
    signIn($this->hr)->post(route('employees.compensation.store', $this->bob), [
        'effective_from' => now()->toDateString(), 'base_salary' => 1000, 'reason' => 'Raise',
    ])->assertNotFound();

    expect($this->bob->compensations()->count())->toBe(0);
});

it('cannot reveal an identifier from another company', function () {
    signIn($this->hr)->post(route('employees.identifiers.reveal', [$this->bob, $this->bobId]))->assertNotFound();
});

it('lists only employees of the current company', function () {
    signIn($this->hr)->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->component('people/EmployeesIndex')
            ->has('employees.data', 1)
            ->where('employees.data.0.full_name', 'Alice Alpha'));
});

it('refuses to switch to a company the user does not belong to', function () {
    signIn($this->hr)->post(route('companies.switch', $this->b))->assertNotFound();
});

it('lets a member of both companies switch and see the other directory', function () {
    $this->hr->companies()->attach($this->b->id);
    setPermissionsTeamId($this->b->id);
    $this->hr->assignRole(Role::HrAdmin->value);

    signIn($this->hr)->post(route('companies.switch', $this->b))->assertRedirect(route('dashboard'));

    $this->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('company.name', 'Bravo')
            ->has('employees.data', 1)
            ->where('employees.data.0.full_name', 'Bob Bravo'));
});
