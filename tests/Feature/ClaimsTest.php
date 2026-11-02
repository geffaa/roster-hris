<?php

use App\Enums\Role;
use App\Models\ApprovalStep;
use App\Models\Claim;
use App\Models\ClaimCategory;
use App\Models\PayrollItem;
use App\Services\Payroll\PayrollRuns;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->t = payrollSetup();
    $this->travelTo(Carbon::parse('2026-10-20 10:00', 'Asia/Jakarta'));
    $this->lead = hire($this->t['company'], 20_000_000);
    $this->me = hire($this->t['company'], 10_000_000);
    $this->me->update(['manager_id' => $this->lead->id]);
    $this->user = memberOf($this->t['company'], Role::Employee, $this->me);
    $this->leadUser = memberOf($this->t['company'], Role::Manager, $this->lead);
    $this->meals = ClaimCategory::create(['company_id' => $this->t['company']->id, 'code' => 'meals', 'name' => 'Client meals', 'monthly_limit' => 1_000_000, 'receipt_required' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function claimNow(array $over = [])
{
    return signIn(test()->user)->post(route('me.claims.store'), [
        'claim_category_id' => test()->meals->id, 'spent_on' => '2026-10-18', 'amount' => 600_000, 'description' => 'Lunch with client',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'), ...$over,
    ]);
}

it('checks receipts, dates and the monthly limit', function () {
    claimNow(['receipt' => null])->assertSessionHasErrors('receipt');
    claimNow(['spent_on' => '2026-10-25'])->assertSessionHasErrors('spent_on');
    claimNow()->assertSessionHasNoErrors();
    claimNow(['amount' => 500_000])->assertSessionHasErrors(['amount' => 'Client meals is limited to 1,000,000 a month; 400,000 is left for October.']);
});

it('goes to the manager, then into the next payroll untaxed, and is paid once', function () {
    claimNow();
    $claim = Claim::sole();
    Storage::disk('local')->assertExists($claim->receipt_path);
    signIn($this->leadUser)->get(route('claims.receipt', $claim))->assertOk();
    signIn(memberOf($this->t['company'], Role::Employee))->get(route('claims.receipt', $claim))->assertNotFound();

    signIn($this->leadUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($claim->refresh()->status)->toBe('approved');

    $runs = app(PayrollRuns::class);
    $oct = $runs->calculate($runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));
    $item = PayrollItem::where('payroll_run_id', $oct->id)->where('employee_id', $this->me->id)->sole();
    $line = collect($item->breakdown)->firstWhere('code', "claim_{$claim->id}");
    // Base 10,000,000 → TER on 10,454,000 = 261,350; the 600,000 reimbursement adds to net but not to taxable gross.
    expect($line['amount'])->toBe(600_000)->and((float) $item->tax)->toBe(261_350.0)->and((float) $item->net)->toBe(9_938_650.0);

    $runs->approve($oct->refresh(), $this->t['officer']);
    $runs->approve($oct->refresh(), $this->t['hr']);
    $runs->lock($oct->refresh(), $this->t['officer']);
    expect($claim->refresh()->status)->toBe('paid')->and($claim->payroll_run_id)->toBe($oct->id);

    $nov = $runs->calculate($runs->create($this->t['company'], CarbonImmutable::create(2026, 11, 1), false, $this->t['officer']));
    expect(collect(PayrollItem::where('payroll_run_id', $nov->id)->where('employee_id', $this->me->id)->sole()->breakdown)->firstWhere('code', "claim_{$claim->id}"))->toBeNull();
});

it('lets payroll see all claims and keeps them from managers', function () {
    claimNow();
    signIn($this->t['officer'])->get(route('claims.index'))->assertInertia(fn ($p) => $p->component('pay/Claims')->has('claims', 1)->where('counts.pending.n', 1));
    signIn($this->leadUser)->get(route('claims.index'))->assertForbidden();
});
