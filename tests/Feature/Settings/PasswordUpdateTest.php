<?php

use App\Enums\Role;
use App\Models\Company;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = memberOf(Company::factory()->create(), Role::Employee);
});

it('updates the password', function () {
    $this->actingAs($this->user)->from('/settings/password')
        ->put('/settings/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    expect(Hash::check('new-password', $this->user->refresh()->password))->toBeTrue();
});

it('requires the current password', function () {
    $this->actingAs($this->user)->from('/settings/password')
        ->put('/settings/password', ['current_password' => 'wrong-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/settings/password');
});
