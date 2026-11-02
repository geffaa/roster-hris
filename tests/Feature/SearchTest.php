<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->lead = Employee::factory()->for($this->company)->create(['full_name' => 'Laras Lead']);
    $this->mine = Employee::factory()->for($this->company)->create(['full_name' => 'Nadia Rahma', 'manager_id' => $this->lead->id]);
    $this->other = Employee::factory()->for($this->company)->create(['full_name' => 'Nadia Wulan']);
    Employee::factory()->for(Company::factory()->create())->create(['full_name' => 'Nadia Elsewhere']);
});

it('finds people within what the person may see', function () {
    $hr = memberOf($this->company, Role::HrAdmin);
    $manager = memberOf($this->company, Role::Manager, $this->lead);

    signIn($hr)->getJson(route('search', ['q' => 'nadia']))->assertJsonCount(2, 'people');
    signIn($manager)->getJson(route('search', ['q' => 'nadia']))->assertJsonCount(1, 'people')->assertJsonPath('people.0.name', 'Nadia Rahma');
    signIn(memberOf($this->company, Role::Employee))->getJson(route('search', ['q' => 'nadia']))->assertJsonCount(0, 'people');
    signIn($hr)->getJson(route('search', ['q' => 'n']))->assertJsonCount(0, 'people');
});
