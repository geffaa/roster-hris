<?php

use App\Enums\Role;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\RulePack;
use App\Models\RulePackVersion;
use App\Services\Payroll\PayrollRuns;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->t = payrollSetup();
    $this->travelTo(Carbon::parse('2026-10-31 10:00', 'Asia/Jakarta'));
    $this->author = memberOf($this->t['company'], Role::HrAdmin);
    $this->approver = $this->t['hr'];
});

afterEach(fn () => Carbon::setTestNow());

function draftFrom(RulePackVersion $base, $user): RulePackVersion
{
    signIn($user)->post(route('rule-packs.draft', $base))->assertRedirect();

    return RulePackVersion::where('status', 'draft')->sole();
}

it('changes numbers only through a dated draft that a second person approves', function () {
    $draft = draftFrom($this->t['v2'], $this->author);
    expect($draft->version)->toBe('ID-2027.1')->and($draft->based_on_id)->toBe($this->t['v2']->id);

    $config = $draft->config;
    $config['contributions'][2]['employer'] = 0.54;
    signIn($this->author)->put(route('rule-packs.update', $draft), ['effective_from' => '2027-01-01', 'config' => $config])->assertSessionHasNoErrors();

    // Changed numbers need a fresh source and a reason before they can be sent.
    signIn($this->author)->post(route('rule-packs.submit', $draft))->assertSessionHasErrors(['sources.contributions.jkk', 'change_note']);
    signIn($this->author)->put(route('rule-packs.update', $draft), [
        'effective_from' => '2027-01-01', 'config' => $config, 'change_note' => 'Risk class reassessed',
        'sources' => ['contributions.jkk' => ['note' => 'BPJS TK reassessment letter, risk class low', 'verified' => '2026-10-31']],
    ])->assertSessionHasNoErrors();
    signIn($this->author)->post(route('rule-packs.submit', $draft))->assertSessionHasNoErrors();

    signIn($this->author)->post(route('rule-packs.approve', $draft))->assertSessionHasErrors(['version' => 'A second person must approve: you prepared this version.']);
    signIn($this->approver)->post(route('rule-packs.approve', $draft))->assertSessionHasNoErrors();

    expect($draft->refresh()->status)->toBe('approved')->and($draft->approved_by)->toBe($this->approver->id)
        ->and($draft->config['contributions'][2]['employer'])->toBe(0.54)
        ->and($draft->audits()->count())->toBeGreaterThan(0);
});

it('lets the approver send a draft back with a reason, and the author fix it', function () {
    $draft = draftFrom($this->t['v2'], $this->author);
    $config = $draft->config;
    $config['ptkp']['base'] = 60_000_000;
    signIn($this->author)->put(route('rule-packs.update', $draft), ['effective_from' => '2027-01-01', 'config' => $config, 'change_note' => 'New PTKP',
        'sources' => ['ptkp' => ['note' => 'Rumour', 'verified' => '2026-10-31']]]);
    signIn($this->author)->post(route('rule-packs.submit', $draft))->assertSessionHasNoErrors();

    signIn($this->approver)->post(route('rule-packs.reject', $draft), ['reason' => 'No PMK has changed PTKP. Attach the regulation.'])->assertSessionHasNoErrors();
    expect($draft->refresh()->status)->toBe('rejected')->and($draft->isEditable())->toBeTrue();
});

it('refuses impossible numbers and dates', function () {
    $draft = draftFrom($this->t['v2'], $this->author);
    $config = $draft->config;
    $config['contributions'][4]['employee'] = 150;
    $config['contributions'][1]['cap'] = -5;

    signIn($this->author)->put(route('rule-packs.update', $draft), ['effective_from' => '2027-01-01', 'config' => $config])
        ->assertSessionHasErrors(['config.contributions.4.employee', 'config.contributions.1.cap']);
    signIn($this->author)->put(route('rule-packs.update', $draft), ['effective_from' => '2027-01-15', 'config' => $draft->config])
        ->assertSessionHasErrors(['effective_from' => 'Versions start on the 1st of a month, so a payroll period never mixes two versions.']);
    signIn($this->author)->put(route('rule-packs.update', $draft), ['effective_from' => '2026-03-01', 'config' => $draft->config])
        ->assertSessionHasErrors(['effective_from' => 'ID-2026.2 already starts on this date.']);
});

it('never starts a version inside a locked period, so past payroll keeps its numbers', function () {
    $runs = app(PayrollRuns::class);
    hire($this->t['company'], 10_000_000);
    $run = $runs->calculate($runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));
    $runs->approve($run->refresh(), $this->t['officer']);
    $runs->approve($run->refresh(), $this->t['hr']);
    $runs->lock($run->refresh(), $this->t['officer']);

    $draft = draftFrom($this->t['v2'], $this->author);
    signIn($this->author)->put(route('rule-packs.update', $draft), ['effective_from' => '2026-10-01', 'config' => $draft->config])
        ->assertSessionHasErrors(['effective_from' => 'Payroll up to 31 Oct 2026 is already locked. Start this version after that.']);
    expect(PayrollItem::where('payroll_run_id', $run->id)->sole()->tax_detail['version'])->toBe('ID-2026.2');
});

it('keeps every version: approved ones are read-only and none can be deleted', function () {
    signIn($this->author)->put(route('rule-packs.update', $this->t['v2']), ['effective_from' => '2026-03-01', 'config' => $this->t['v2']->config])
        ->assertSessionHasErrors('version');
    expect(fn () => $this->t['v1']->delete())->toThrow(LogicException::class);

    $draft = draftFrom($this->t['v2'], $this->author);
    signIn($this->author)->post(route('rule-packs.discard', $draft))->assertRedirect();
    expect($draft->refresh()->status)->toBe('discarded')->and(RulePackVersion::count())->toBe(3);
});

it('shows only the company\'s own pack and limits editing to rule pack managers', function () {
    $other = RulePack::create(['code' => 'GEN', 'name' => 'Generic', 'method' => 'progressive_monthly']);
    $foreign = RulePackVersion::create(['rule_pack_id' => $other->id, 'version' => 'GEN-2026.1', 'effective_from' => '2026-01-01', 'status' => 'approved', 'config' => ['method' => 'progressive_monthly', 'contributions' => []], 'sources' => []]);

    signIn($this->author)->get(route('rule-packs.show', $foreign))->assertNotFound();
    signIn($this->author)->get(route('rule-packs.show'))->assertInertia(fn ($p) => $p->component('pay/RulePack')->where('version.version', 'ID-2026.2')->where('versions.1.state', 'active'));
    signIn($this->t['officer'])->post(route('rule-packs.draft', $this->t['v2']))->assertForbidden();
    signIn(memberOf($this->t['company'], Role::Employee))->get(route('rule-packs.show'))->assertForbidden();
});

it('calculates through the endpoint and shows the run for review', function () {
    hire($this->t['company'], 10_000_000);
    $run = app(PayrollRuns::class)->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']);

    signIn($this->t['officer'])->post(route('payroll.calculate', $run))->assertSessionHasNoErrors();
    expect($run->refresh()->status)->toBe('calculated');
    signIn($this->t['officer'])->get(route('payroll.show', $run))
        ->assertInertia(fn ($p) => $p->component('pay/PayrollRun')->where('run.totals.net', '9338650.00')->has('items', 1)->where('can.approve', true));
    expect(PayrollRun::count())->toBe(1);
});
