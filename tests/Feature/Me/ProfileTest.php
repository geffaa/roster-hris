<?php

use App\Enums\Role;
use App\Models\ApprovalStep;
use App\Models\BankAccountChange;
use App\Models\Employee;
use App\Notifications\RosterNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->t = timeSetup();
    $this->manager = Employee::factory()->for($this->t['company'])->create();
    $this->me = Employee::factory()->for($this->t['company'])->create(['manager_id' => $this->manager->id, 'bank_name' => 'BCA', 'bank_account' => '5270011223']);
    $this->user = memberOf($this->t['company'], Role::Employee, $this->me);
    $this->managerUser = memberOf($this->t['company'], Role::Manager, $this->manager);
    $this->hr = memberOf($this->t['company'], Role::HrAdmin);
});

it('sends a bank change to HR, not the manager, and applies it only after approval', function () {
    Notification::fake();
    signIn($this->user)->post(route('me.profile.bank'), ['bank_name' => 'Mandiri', 'account_number' => '1230009876543', 'holder_name' => 'Test Person'])->assertSessionHasNoErrors();

    $step = ApprovalStep::sole();
    expect($step->approver_id)->toBeNull()->and($this->me->refresh()->bank_account_last4)->toBe('1223');
    Notification::assertSentTo($this->hr, RosterNotification::class, fn ($n) => $n->kind === 'approval_needed' && str_contains($n->title, 'bank account change'));
    Notification::assertNotSentTo($this->managerUser, RosterNotification::class);

    signIn($this->managerUser)->post(route('approvals.decide', $step), ['decision' => 'approve'])->assertForbidden();
    signIn($this->hr)->post(route('approvals.decide', $step), ['decision' => 'approve'])->assertSessionHasNoErrors();

    expect($this->me->refresh()->bank_name)->toBe('Mandiri')->and($this->me->bank_account)->toBe('1230009876543')->and($this->me->bank_account_last4)->toBe('6543');
    Notification::assertSentTo($this->user, RosterNotification::class, fn ($n) => $n->kind === 'request_approved');
});

it('keeps the account number out of the audit log and allows one pending change at a time', function () {
    signIn($this->user)->post(route('me.profile.bank'), ['bank_name' => 'BNI', 'account_number' => '0099887766', 'holder_name' => 'Test Person']);
    $change = BankAccountChange::sole();

    expect(json_encode($change->audits()->first()->new_values))->not->toContain('0099887766');
    signIn($this->user)->post(route('me.profile.bank'), ['bank_name' => 'BRI', 'account_number' => '1111222233', 'holder_name' => 'Test Person'])->assertStatus(422);

    signIn($this->user)->delete(route('me.profile.bank.cancel', $change))->assertSessionHasNoErrors();
    expect($change->refresh()->status)->toBe('cancelled')->and(ApprovalStep::sole()->status)->toBe('skipped');
});

it('validates account numbers and lets people update their phone and language', function () {
    signIn($this->user)->post(route('me.profile.bank'), ['bank_name' => 'BCA', 'account_number' => '12ab', 'holder_name' => 'X'])->assertSessionHasErrors('account_number');
    signIn($this->user)->put(route('me.profile.contact'), ['phone' => '+62 812 5555 0101'])->assertSessionHasNoErrors();
    signIn($this->user)->put(route('me.locale'), ['locale' => 'id'])->assertSessionHasNoErrors();

    expect($this->me->refresh()->personal_data['phone'])->toBe('+62 812 5555 0101')->and($this->user->refresh()->locale)->toBe('id');
    signIn($this->user)->post(route('me.profile.bank'), ['bank_name' => 'BCA', 'account_number' => '12ab', 'holder_name' => 'X'])
        ->assertSessionHasErrors(['account_number' => 'Gunakan angka saja (spasi dan tanda hubung boleh), 6 sampai 34 karakter.']);
});

it('tells approvers about new requests and requesters about decisions, through the bell', function () {
    signIn($this->user)->post(route('me.time-off.store'), ['leave_type_id' => $this->t['sick']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'half_day' => 'none']);

    signIn($this->managerUser)->get(route('inbox'))->assertInertia(fn ($p) => $p->where('notifications.unread', 1)->where('notifications.latest.0.kind', 'approval_needed'));
    $id = $this->managerUser->notifications()->value('id');
    signIn($this->managerUser)->get(route('notifications.open', $id))->assertRedirect('/inbox');
    expect($this->managerUser->unreadNotifications()->count())->toBe(0);

    signIn($this->managerUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'reject', 'comment' => 'Client workshop that day']);
    $note = $this->user->notifications()->sole();
    expect($note->data['kind'])->toBe('request_rejected')->and($note->data['body'])->toContain('Client workshop that day');

    // Someone else cannot open it.
    signIn($this->managerUser)->get(route('notifications.open', $note->id))->assertNotFound();
});
