<?php

namespace App\Http\Controllers\Pay;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Jobs\CalculatePayrollRun;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Assistant\PayrollSummary;
use App\Services\Payroll\PayrollRuns;
use App\Support\Csv;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** payroll runs: create, calculate, review, approve twice, lock, publish, export. */
class PayrollRunController extends Controller
{
    public function __construct(private PayrollRuns $runs, private CurrentCompany $company) {}

    public function index(Request $request): Response
    {
        $this->allow($request, Permission::PayrollView);
        $company = $this->company->get();
        $runs = PayrollRun::orderByDesc('period_start')->get();
        $last = $runs->first();
        $next = $last ? CarbonImmutable::parse($last->period_start->toDateString())->addMonthNoOverflow() : CarbonImmutable::now($company->timezone)->startOfMonth();

        return Inertia::render('pay/PayrollRuns', [
            'runs' => $runs->map(fn (PayrollRun $r) => [
                'id' => $r->id, 'reference' => $r->reference, 'period' => $r->period_start->toDateString(), 'pay_date' => $r->pay_date->toDateString(),
                'status' => $r->status, 'headcount' => $r->headcount, 'totals' => $r->totals, 'approvals' => $r->approvals(), 'include_thr' => $r->include_thr,
            ]),
            'currency' => $company->base_currency,
            'next' => $next->toDateString(),
            'blocked' => $runs->first(fn ($r) => ! $r->isFinal())?->reference,
            'can' => ['run' => $request->user()->can(Permission::PayrollRun->value)],
            'thr' => $company->country_code === 'ID',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, Permission::PayrollRun);
        $data = $request->validate(['period' => ['required', 'date_format:Y-m-d'], 'include_thr' => ['boolean']]);
        $run = $this->runs->create($this->company->get(), CarbonImmutable::parse($data['period']), (bool) ($data['include_thr'] ?? false), $request->user());

        return redirect()->route('payroll.show', $run)->with('success', "{$run->reference} created. Calculate it when this month's inputs are in.");
    }

    public function show(Request $request, PayrollRun $run): Response
    {
        $this->allow($request, Permission::PayrollView);
        $company = $this->company->get();
        $run->load('rulePackVersion:id,version,effective_from');
        $items = $run->items()->with('employee:id,full_name')->orderBy('id')->get();
        $previous = PayrollRun::whereIn('status', ['locked', 'published'])->whereDate('period_start', '<', $run->period_start)->orderByDesc('period_start')->first();
        $names = User::whereIn('id', array_filter([$run->calculated_by, $run->approved_by_1, $run->approved_by_2, $run->locked_by]))->pluck('name', 'id');
        $user = $request->user();

        return Inertia::render('pay/PayrollRun', [
            'run' => [
                'id' => $run->id, 'reference' => $run->reference, 'period' => $run->period_start->toDateString(), 'pay_date' => $run->pay_date->toDateString(),
                'status' => $run->status, 'progress' => $run->progress, 'headcount' => $run->headcount, 'include_thr' => $run->include_thr, 'totals' => $run->totals,
                'version' => $run->rulePackVersion?->version, 'calculated_at' => $run->calculated_at?->toIso8601String(), 'calculated_by' => $names[$run->calculated_by] ?? null,
                'approvals' => [
                    ['by' => $names[$run->approved_by_1] ?? null, 'at' => $run->approved_at_1?->toIso8601String(), 'id' => $run->approved_by_1],
                    ['by' => $names[$run->approved_by_2] ?? null, 'at' => $run->approved_at_2?->toIso8601String(), 'id' => $run->approved_by_2],
                ],
                'locked_by' => $names[$run->locked_by] ?? null, 'locked_at' => $run->locked_at?->toIso8601String(), 'published_at' => $run->published_at?->toIso8601String(),
            ],
            'previous' => $previous ? ['month' => $previous->period_start->format('F'), 'totals' => $previous->totals] : null,
            'currency' => $company->base_currency,
            'decimals' => $company->base_currency === 'IDR' ? 0 : 2,
            'items' => $items->map(fn (PayrollItem $i) => [
                'id' => $i->id, 'employee_id' => $i->employee_id, 'context' => $i->context, 'gross' => (float) $i->gross, 'tax' => (float) $i->tax,
                'employee_contributions' => (float) $i->employee_contributions, 'employer_contributions' => (float) $i->employer_contributions,
                'other_deductions' => (float) $i->other_deductions, 'net' => (float) $i->net, 'previous_net' => $i->previous_net === null ? null : (float) $i->previous_net,
                'breakdown' => $i->breakdown, 'tax_detail' => $i->tax_detail, 'flags' => $i->flags, 'reviewed' => $i->reviewed_at !== null,
            ]),
            'can' => [
                'run' => $user->can(Permission::PayrollRun->value),
                'approve' => $user->can(Permission::PayrollApprove->value) && $run->status === 'calculated' && $run->approved_by_1 !== $user->id,
                'already_approved' => $run->approved_by_1 === $user->id,
            ],
        ]);
    }

    public function calculate(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->allow($request, Permission::PayrollRun);
        abort_if($run->isFinal(), 422, 'This run is locked.');
        $run->update(['status' => 'calculating', 'progress' => 0]);
        CalculatePayrollRun::dispatchAfterResponse($run->id, $request->user()->id);

        return back()->with('success', 'Calculating. Approvals and reviews start again from zero.');
    }

    public function review(Request $request, PayrollRun $run, PayrollItem $item): RedirectResponse
    {
        $this->allow($request, Permission::PayrollRun);
        abort_unless($item->payroll_run_id === $run->id, 404);
        $this->runs->review($item, $request->user());

        return back()->with('success', "Marked {$item->context['name']} as reviewed.");
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->allow($request, Permission::PayrollApprove);
        $this->runs->approve($run, $request->user());
        $run->refresh();

        return back()->with('success', $run->status === 'approved' ? 'Second approval recorded. The run can be locked.' : 'Approved (1 of 2). Someone else must approve too.');
    }

    public function lock(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->allow($request, Permission::PayrollRun);
        $this->runs->lock($run, $request->user());

        return back()->with('success', "{$run->reference} is locked. Its numbers no longer change.");
    }

    public function publish(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->allow($request, Permission::PayrollRun);
        $this->runs->publish($run);

        return back()->with('success', 'Payslips sent: everyone can see theirs in the Roster app.');
    }

    /** Bank transfer file. Full account numbers leave the system only here, for locked runs, to people who run payroll. */
    public function bankFile(Request $request, PayrollRun $run): StreamedResponse
    {
        $this->allow($request, Permission::PayrollRun);
        abort_unless($run->isFinal(), 422, 'Lock the run before exporting the transfer file.');
        $items = $run->items()->with('employee:id,employee_no,full_name,bank_name,bank_account')->orderBy('id')->get();
        $ref = 'Salary '.$run->period_start->format('M Y');
        $rows = $items->filter(fn (PayrollItem $i) => filled($i->employee->bank_account) && (float) $i->net > 0)
            ->map(fn (PayrollItem $i) => [$i->employee->employee_no, $i->employee->full_name, $i->employee->bank_name, $i->employee->bank_account, $i->net, $i->currency, $ref]);
        Audit::create([
            'company_id' => $run->company_id, 'user_type' => $request->user()::class, 'user_id' => $request->user()->id,
            'event' => 'bank_file_exported', 'auditable_type' => PayrollRun::class, 'auditable_id' => $run->id,
            'old_values' => [], 'new_values' => ['rows' => $rows->count()], 'url' => $request->fullUrl(), 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1023), 'tags' => 'pii',
        ]);

        return Csv::download(Str::slug($this->company->get()->name)."-{$run->reference}-bank.csv", ['Employee no', 'Name', 'Bank', 'Account', 'Amount', 'Currency', 'Reference'], $rows->values());
    }

    public function summary(Request $request, PayrollRun $run): StreamedResponse
    {
        $this->allow($request, Permission::PayrollView);
        $items = $run->items()->orderBy('id')->get();
        $codes = $items->flatMap(fn (PayrollItem $i) => collect($i->breakdown)->filter(fn ($l) => str_contains($l['section'], 'contribution'))->pluck('code'))->unique()->values();
        $header = ['Employee no', 'Name', 'Tax status', 'Gross', 'Tax', 'Employee contributions', 'Employer contributions', 'Other deductions', 'Net', ...$codes->all()];
        $rows = $items->map(function (PayrollItem $i) use ($codes) {
            $by = collect($i->breakdown)->keyBy('code');
            $dec = $i->currency === 'IDR' ? 0 : 2;

            return [$i->context['employee_no'], $i->context['name'], $i->context['tax_status'], $i->gross, $i->tax, $i->employee_contributions, $i->employer_contributions, $i->other_deductions, $i->net,
                ...$codes->map(fn ($c) => isset($by[$c]) ? number_format(abs($by[$c]['amount']) / 10 ** $dec, $dec, '.', '') : '0')->all()];
        });

        return Csv::download(Str::slug($this->company->get()->name)."-{$run->reference}-summary.csv", $header, $rows);
    }

    public function briefing(Request $request, PayrollRun $run, PayrollSummary $summary): JsonResponse
    {
        $this->allow($request, Permission::PayrollView);
        abort_unless($run->totals !== null, 422, 'Calculate the run first.');

        return response()->json($summary->summarize($run, $request->user()));
    }

    private function allow(Request $request, Permission $permission): void
    {
        abort_unless($request->user()->can($permission->value), 403);
    }
}
