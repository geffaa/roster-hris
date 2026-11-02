<?php

use App\Enums\Role;
use App\Models\Company;
use App\Notifications\RosterNotification;
use Database\Seeders\RoleSeeder;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = memberOf($this->company, Role::Employee);
});

it('stores and removes this phone\'s push subscription and adds web push to notifications', function () {
    $sub = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg']];
    $notification = new RosterNotification('payslip', 'Ready', 'Body', '/me/pay', $this->company->id);

    expect($notification->via($this->user))->not->toContain(WebPushChannel::class);
    signIn($this->user)->postJson(route('push.store'), $sub)->assertOk()->assertJson(['subscribed' => true]);
    expect($this->user->pushSubscriptions()->count())->toBe(1)->and($notification->via($this->user->refresh()))->toContain(WebPushChannel::class);
    expect($notification->toWebPush($this->user, $notification)->toArray())->toMatchArray(['title' => 'Ready', 'data' => ['url' => '/me/pay']]);

    signIn($this->user)->deleteJson(route('push.destroy'), ['endpoint' => $sub['endpoint']])->assertOk();
    expect($this->user->pushSubscriptions()->count())->toBe(0);
});

it('only accepts secure push endpoints', function () {
    signIn($this->user)->postJson(route('push.store'), ['endpoint' => 'http://evil.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertUnprocessable();
});

it('ships an installable manifest and a service worker that never caches actions', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
    $sw = file_get_contents(public_path('sw.js'));

    expect($manifest['display'])->toBe('standalone')->and($manifest['start_url'])->toStartWith('/me/today')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and($sw)->toContain("if (req.method !== 'GET') return; // network only")
        ->and(file_exists(public_path('offline.html')))->toBeTrue();
});
