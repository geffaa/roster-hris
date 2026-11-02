<?php

use App\Enums\Role;
use App\Models\Company;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = memberOf(Company::factory()->create(), Role::Employee);
});

it('displays the profile page', function () {
    $this->actingAs($this->user)->get('/settings/profile')->assertOk();
});

it('updates profile information', function () {
    $this->actingAs($this->user)
        ->patch('/settings/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $this->user->refresh();
    expect($this->user->name)->toBe('Test User')
        ->and($this->user->email)->toBe('test@example.com')
        ->and($this->user->email_verified_at)->toBeNull();
});

it('keeps verification when the email is unchanged', function () {
    $this->actingAs($this->user)
        ->patch('/settings/profile', ['name' => 'Test User', 'email' => $this->user->email])
        ->assertSessionHasNoErrors();

    expect($this->user->refresh()->email_verified_at)->not->toBeNull();
});

it('does not let users delete their own account', function () {
    // Accounts are deactivated by HR, never self-deleted (employment records must stay).
    $this->actingAs($this->user)->delete('/settings/profile', ['password' => 'password'])->assertMethodNotAllowed();
    expect($this->user->fresh())->not->toBeNull();
});
