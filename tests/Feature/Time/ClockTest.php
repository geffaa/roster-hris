<?php

use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\WorkPattern;
use Illuminate\Support\Carbon;

beforeEach(function () {
    extract($this->t = timeSetup());
    $this->employee = Employee::factory()->for($company)->create(['location_id' => $office->id, 'hire_date' => '2024-01-08']);
    $this->user = memberOf($company, Role::Employee, $this->employee);
    $this->inside = ['lat' => -6.2299, 'lng' => 106.8296];   // ~25 m from the office
});

afterEach(fn () => Carbon::setTestNow());

/** Travel to a wall-clock time in Jakarta (the server itself runs in UTC). */
function at(string $jakarta): void
{
    test()->travelTo(Carbon::parse($jakarta, 'Asia/Jakarta'));
}

it('clocks in on time inside the geofence using the server clock', function () {
    at('2026-11-16 08:37');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasNoErrors();

    $r = AttendanceRecord::first();
    expect($r->status)->toBe('ok')->and($r->late_min)->toBe(0)->and($r->work_date->toDateString())->toBe('2026-11-16')
        ->and($r->in_distance_m)->toBeLessThan(40);
});

it('marks a clock-in after the grace period as late with the minutes beyond it', function () {
    at('2026-11-16 08:57');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside]);

    expect(AttendanceRecord::first())->status->toBe('late')->late_min->toBe(17);
});

it('rejects a clock-in outside the radius with the distance', function () {
    at('2026-11-16 08:30');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', 'lat' => -6.2405, 'lng' => 106.8295])
        ->assertSessionHasErrors(['clock' => 'You are 1.2 km from Jakarta HQ. Clock-in works within 120 m.']);

    expect(AttendanceRecord::count())->toBe(0);
});

it('requires a location for a geofenced office but not for a remote-allowed one', function () {
    at('2026-11-16 08:30');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in'])->assertSessionHasErrors('clock');

    $this->t['office']->update(['allow_remote' => true]);
    signIn($this->user)->post(route('me.clock'), ['action' => 'in'])->assertSessionHasNoErrors();
    expect(AttendanceRecord::first()->remote)->toBeTrue();
});

it('refuses a second clock-in on the same day', function () {
    at('2026-11-16 08:30');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside]);
    at('2026-11-16 08:30:20');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasErrors(['clock' => 'You clocked in at 08:30.']);

    expect(AttendanceRecord::count())->toBe(1);
});

it('only opens clock-in from two hours before the shift until it ends', function () {
    at('2026-11-16 06:10');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasErrors(['clock' => 'Clock-in opens at 06:30, two hours before your shift.']);
    at('2026-11-16 17:30');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasErrors(['clock' => 'Your shift ended at 17:30. If you worked today, send a correction or request overtime.']);

    expect(AttendanceRecord::count())->toBe(0);
});

it('describes long lateness in hours and minutes', function () {
    at('2026-11-16 11:05');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])
        ->assertSessionHas('success', 'Clocked in at 11:05, 2 h 25 min after the grace period.');
});

it('refuses clock-in on public holidays and days off', function () {
    at('2026-11-17 08:30');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasErrors('clock');
    at('2026-11-21 08:30'); // Saturday
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasErrors('clock');

    expect(AttendanceRecord::count())->toBe(0);
});

it('keeps a night shift that crosses midnight on one work date', function () {
    $night = WorkPattern::create(['company_id' => $this->t['company']->id, 'name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true, 'grace_min' => 10, 'working_days' => [1, 2, 3, 4, 5]]);
    $this->employee->contracts()->create(['type' => 'permanent', 'start_date' => '2024-01-08', 'work_pattern_id' => $night->id]);

    at('2026-11-16 22:04');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasNoErrors();
    at('2026-11-17 02:00');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside])->assertSessionHasErrors('clock'); // still Monday's shift
    at('2026-11-17 06:03');
    signIn($this->user)->post(route('me.clock'), ['action' => 'out', ...$this->inside])->assertSessionHasNoErrors();

    $r = AttendanceRecord::sole();
    expect($r->work_date->toDateString())->toBe('2026-11-16')->and($r->clock_out_at)->not->toBeNull()->and($r->status)->toBe('ok');
});

it('flags shifts without a clock-out as incomplete four hours after the end', function () {
    at('2026-11-16 08:30');
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', ...$this->inside]);

    at('2026-11-16 21:00');
    $this->artisan('attendance:close-incomplete');
    expect(AttendanceRecord::first()->status)->toBe('ok');

    at('2026-11-16 21:31');
    $this->artisan('attendance:close-incomplete');
    expect(AttendanceRecord::first()->status)->toBe('incomplete');
});

it('renders the phone screen with the shift and location', function () {
    at('2026-11-16 08:00');
    signIn($this->user)->get(route('me.today'))->assertInertia(fn ($page) => $page->component('me/Today')
        ->where('shift.start', '08:30')->where('location.radius', 120)->where('record', null));
});
