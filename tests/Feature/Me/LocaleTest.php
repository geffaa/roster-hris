<?php

use App\Enums\Role;
use App\Models\Employee;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->t = timeSetup();
    $this->me = Employee::factory()->for($this->t['company'])->create(['hire_date' => '2024-01-08', 'location_id' => $this->t['office']->id]);
    $this->user = memberOf($this->t['company'], Role::Employee, $this->me);
    $this->user->update(['locale' => 'id']);
});

afterEach(fn () => Carbon::setTestNow());

it('answers in Indonesian for people who chose it', function () {
    $this->travelTo(Carbon::parse('2026-11-16 06:00', 'Asia/Jakarta'));
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', 'lat' => -6.2297, 'lng' => 106.8295])
        ->assertSessionHasErrors(['clock' => 'Clock-in dibuka pukul 06:30, dua jam sebelum shift.']);

    $this->travelTo(Carbon::parse('2026-11-16 08:20', 'Asia/Jakarta'));
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', 'lat' => -6.2297, 'lng' => 106.8295])
        ->assertSessionHas('success', 'Clock-in pukul 08:20. Selamat bekerja.');
    signIn($this->user)->get(route('me.today'))->assertInertia(fn ($p) => $p->where('auth.user.locale', 'id'));
});

it('keeps English for everyone else', function () {
    $this->user->update(['locale' => 'en']);
    $this->travelTo(Carbon::parse('2026-11-16 06:00', 'Asia/Jakarta'));
    signIn($this->user)->post(route('me.clock'), ['action' => 'in', 'lat' => -6.2297, 'lng' => 106.8295])
        ->assertSessionHasErrors(['clock' => 'Clock-in opens at 06:30, two hours before your shift.']);
});
