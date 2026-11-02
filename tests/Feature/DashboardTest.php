<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

it('redirects guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows the dashboard to a company member', function () {
    $this->seed(RoleSeeder::class);
    $user = memberOf(Company::factory()->create(), Role::Employee);

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('refuses users who are not linked to any company', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
});
