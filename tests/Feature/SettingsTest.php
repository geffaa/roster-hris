<?php

use App\Enums\Role;
use App\Models\LeaveType;
use App\Models\WorkPattern;

beforeEach(function () {
    $this->t = timeSetup();
    $this->hr = memberOf($this->t['company'], Role::HrAdmin);
});

it('lets HR add an overnight pattern and move the default', function () {
    signIn($this->hr)->post(route('settings.patterns.store'), ['name' => 'Warehouse night', 'start_time' => '22:00', 'end_time' => '06:00', 'grace_min' => 15, 'working_days' => [1, 2, 3, 4, 5, 6], 'is_default' => true])
        ->assertSessionHasNoErrors();

    $night = WorkPattern::where('name', 'Warehouse night')->sole();
    expect($night->crosses_midnight)->toBeTrue()->and($night->is_default)->toBeTrue()->and($this->t['pattern']->refresh()->is_default)->toBeFalse()
        ->and($night->working_days)->toBe([1, 2, 3, 4, 5, 6]);
});

it('refuses impossible patterns and leaving the company without a default', function () {
    signIn($this->hr)->post(route('settings.patterns.store'), ['name' => 'Odd', 'start_time' => '08:07', 'end_time' => '08:07', 'grace_min' => 5, 'working_days' => []])
        ->assertSessionHasErrors(['start_time', 'end_time', 'working_days']);
    signIn($this->hr)->put(route('settings.patterns.update', $this->t['pattern']), ['name' => 'Office', 'start_time' => '08:30', 'end_time' => '17:30', 'grace_min' => 10, 'working_days' => [1, 2, 3, 4, 5], 'is_default' => false])
        ->assertStatus(422);
});

it('lets HR add and change leave types, and keeps others out', function () {
    signIn($this->hr)->post(route('settings.leave-types.store'), ['name' => 'Study leave', 'paid' => true, 'annual_days' => 5, 'accrual' => 'upfront', 'min_service_months' => 12, 'half_day_allowed' => true, 'requires_second_approval' => true, 'color' => '#7B61D9'])
        ->assertSessionHasNoErrors();
    $study = LeaveType::where('code', 'study_leave')->sole();
    expect($study->requires_second_approval)->toBeTrue()->and((float) $study->annual_days)->toBe(5.0);

    signIn($this->hr)->put(route('settings.leave-types.update', $study), ['name' => 'Study leave', 'paid' => true, 'annual_days' => null, 'accrual' => 'upfront', 'min_service_months' => 0, 'color' => '#D9594C'])->assertSessionHasNoErrors();
    expect($study->refresh()->isLimited())->toBeFalse();

    signIn(memberOf($this->t['company'], Role::Manager))->get(route('settings.time'))->assertForbidden();
    signIn($this->hr)->post(route('settings.leave-types.store'), ['name' => 'X', 'accrual' => 'upfront', 'min_service_months' => 0, 'color' => '#000000'])->assertSessionHasErrors('color');
});
