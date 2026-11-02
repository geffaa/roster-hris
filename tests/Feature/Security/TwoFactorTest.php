<?php

use App\Enums\Role;
use App\Models\Company;
use Database\Seeders\RoleSeeder;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
});

it('forces an HR admin without two-factor to enrol first', function () {
    $hr = memberOf($this->company, Role::HrAdmin);

    $this->actingAs($hr)->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
    $this->actingAs($hr)->get(route('employees.index'))->assertRedirect(route('two-factor.setup'));
});

it('enrols with a valid code and shows recovery codes once', function () {
    $hr = memberOf($this->company, Role::HrAdmin);

    $this->actingAs($hr)->get(route('two-factor.setup'))->assertOk();
    $secret = $hr->fresh()->two_factor_secret;
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->actingAs($hr)->post(route('two-factor.confirm'), ['code' => $code])
        ->assertRedirect(route('two-factor.recovery-codes'));

    expect($hr->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($hr->fresh()->two_factor_recovery_codes)->toHaveCount(8);

    $this->get(route('dashboard'))->assertOk();
});

it('rejects a wrong enrolment code', function () {
    $hr = memberOf($this->company, Role::HrAdmin);
    $this->actingAs($hr)->get(route('two-factor.setup'));

    $this->actingAs($hr)->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
    expect($hr->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('challenges an enrolled admin once per session', function () {
    $hr = memberOf($this->company, Role::PayrollOfficer);
    $secret = app(Google2FA::class)->generateSecretKey(32);
    $hr->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($hr)->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));

    $this->post(route('two-factor.verify'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
});

it('accepts a recovery code only once', function () {
    $hr = memberOf($this->company, Role::HrAdmin);
    $hr->forceFill(['two_factor_secret' => app(Google2FA::class)->generateSecretKey(32), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['abcde-fghij', 'klmno-pqrst']])->save();

    $this->actingAs($hr)->post(route('two-factor.verify'), ['recovery_code' => 'abcde-fghij'])->assertRedirect();
    expect($hr->fresh()->two_factor_recovery_codes)->toBe(['klmno-pqrst']);
});

it('does not require two-factor for managers and employees', function () {
    $manager = memberOf($this->company, Role::Manager);

    $this->actingAs($manager)->get(route('dashboard'))->assertOk();
});
