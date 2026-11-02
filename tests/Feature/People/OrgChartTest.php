<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->ceo = Employee::factory()->for($this->company)->create(['full_name' => 'Chief']);
    $this->lead = Employee::factory()->for($this->company)->create(['full_name' => 'Lead', 'manager_id' => $this->ceo->id]);
    $this->dev = Employee::factory()->for($this->company)->create(['full_name' => 'Dev', 'manager_id' => $this->lead->id]);
    $this->sales = Employee::factory()->for($this->company)->create(['full_name' => 'Sales', 'manager_id' => $this->ceo->id]);
    Employee::factory()->for(Company::factory())->create(['full_name' => 'Outsider']);
});

it('builds the whole company tree with team sizes for HR', function () {
    signIn(memberOf($this->company, Role::HrAdmin))->get(route('org-chart'))
        ->assertInertia(fn (Assert $page) => $page->component('people/OrgChart')
            ->has('roots', 1)
            ->where('roots.0.name', 'Chief')
            ->where('roots.0.size', 3)
            ->where('total', 4));
});

it('shows a manager only their own subtree', function () {
    signIn(memberOf($this->company, Role::Manager, $this->lead))->get(route('org-chart'))
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'team')
            ->has('roots', 1)
            ->where('roots.0.name', 'Lead')
            ->where('roots.0.children.0.name', 'Dev'));
});

it('keeps people whose manager has left visible as roots', function () {
    $this->lead->update(['status' => 'resigned']);

    signIn(memberOf($this->company, Role::HrAdmin))->get(route('org-chart'))
        ->assertInertia(fn (Assert $page) => $page->has('roots', 2));
});

it('sends an employee to their own profile', function () {
    signIn(memberOf($this->company, Role::Employee, $this->dev))->get(route('org-chart'))
        ->assertRedirect(route('employees.show', $this->dev));
});
