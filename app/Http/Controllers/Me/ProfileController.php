<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\BankAccountChange;
use App\Models\Employee;
use App\Services\Time\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** S11 "Me": contact details, bank account (changes wait for HR), language. */
class ProfileController extends Controller
{
    public function __construct(private ApprovalService $approvals) {}

    public function show(Request $request): Response
    {
        $e = TodayController::employeeOf($request);
        $e->loadMissing(['department:id,name', 'location:id,name']);
        $manager = $e->manager_id ? Employee::find($e->manager_id, ['id', 'full_name']) : null;
        $pending = BankAccountChange::where('employee_id', $e->id)->where('status', 'pending')->latest()->first();

        return Inertia::render('me/Profile', [
            'me' => [
                'name' => $e->full_name, 'employee_no' => $e->employee_no, 'job_title' => $e->job_title, 'department' => $e->department?->name,
                'location' => $e->location?->name, 'manager' => $manager?->full_name, 'work_email' => $e->work_email,
                'hire_date' => $e->hire_date->toDateString(), 'phone' => $e->personal_data['phone'] ?? null,
                'bank' => $e->bank_account_last4 ? ['name' => $e->bank_name, 'last4' => $e->bank_account_last4] : null,
            ],
            'pending' => $pending ? ['id' => $pending->id, 'bank_name' => $pending->bank_name, 'last4' => $pending->account_last4, 'since' => $pending->created_at->toDateString()] : null,
            'banks' => config('roster.banks.'.strtolower($e->company()->value('country_code')), config('roster.banks.default')),
        ]);
    }

    public function contact(Request $request): RedirectResponse
    {
        $e = TodayController::employeeOf($request);
        $data = $request->validate(['phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/']], ['phone.regex' => 'Use digits, spaces and an optional + at the start.']);
        $e->update(['personal_data' => [...($e->personal_data ?? []), 'phone' => trim($data['phone'])]]);

        return back()->with('success', __('me.saved'));
    }

    public function bank(Request $request): RedirectResponse
    {
        $e = TodayController::employeeOf($request);
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:60'],
            'account_number' => ['required', 'string', 'regex:/^[0-9 -]{6,34}$/'],
            'holder_name' => ['required', 'string', 'max:120'],
        ], ['account_number.regex' => __('me.account_format')]);
        abort_if(BankAccountChange::where('employee_id', $e->id)->where('status', 'pending')->exists(), 422, 'A change is already waiting for HR.');

        DB::transaction(function () use ($e, $data) {
            $change = BankAccountChange::create(['company_id' => $e->company_id, 'employee_id' => $e->id, ...$data]);
            $this->approvals->submit('bank_change', $change, $e, hrOnly: true);
        });

        return back()->with('success', __('me.bank_sent'));
    }

    public function cancelBank(Request $request, BankAccountChange $change): RedirectResponse
    {
        $e = TodayController::employeeOf($request);
        abort_unless($change->employee_id === $e->id && $change->status === 'pending', 404);
        $approval = ApprovalRequest::where('subject_type', BankAccountChange::class)->where('subject_id', $change->id)->firstOrFail();
        $this->approvals->cancel($approval);

        return back()->with('success', __('me.bank_cancelled'));
    }

    public function locale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:en,id']]);
        $request->user()->update(['locale' => $data['locale']]);

        return back();
    }
}
