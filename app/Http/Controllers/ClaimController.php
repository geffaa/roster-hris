<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Me\TodayController;
use App\Models\ApprovalRequest;
use App\Models\Claim;
use App\Models\ClaimCategory;
use App\Services\ClaimService;
use App\Services\Time\ApprovalService;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Expense claims: employees submit from the app; payroll and HR see every claim and its status. */
class ClaimController extends Controller
{
    public function __construct(private ClaimService $claims, private ApprovalService $approvals, private CurrentCompany $company) {}

    public function mine(Request $request): Response
    {
        $e = TodayController::employeeOf($request);

        return Inertia::render('me/Claims', [
            'categories' => ClaimCategory::orderBy('name')->get()->map(fn (ClaimCategory $c) => ['id' => $c->id, 'name' => $c->name, 'limit' => $c->monthly_limit === null ? null : (float) $c->monthly_limit, 'receipt' => $c->receipt_required]),
            'claims' => Claim::with('category:id,name')->where('employee_id', $e->id)->latest('spent_on')->limit(30)->get()->map(fn (Claim $c) => $this->row($c)),
            'currency' => $this->company->get()->base_currency,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $e = TodayController::employeeOf($request);
        $data = $request->validate([
            'claim_category_id' => ['required', 'integer'],
            'spent_on' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'description' => ['required', 'string', 'max:300'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $category = ClaimCategory::findOrFail($data['claim_category_id']);
        $this->claims->submit($e, $category, $data['spent_on'], (float) $data['amount'], $data['description'], $request->file('receipt'));

        return back()->with('success', __('Claim sent to your manager. Once approved it is paid with the next payroll.'));
    }

    public function cancel(Request $request, Claim $claim): RedirectResponse
    {
        $e = TodayController::employeeOf($request);
        abort_unless($claim->employee_id === $e->id && $claim->status === 'pending', 404);
        $this->approvals->cancel(ApprovalRequest::where('subject_type', Claim::class)->where('subject_id', $claim->id)->firstOrFail());

        return back()->with('success', __('Claim withdrawn.'));
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::PayrollView->value) || $request->user()->can(Permission::EmployeesManage->value), 403);
        $status = in_array($request->query('status'), ['pending', 'approved', 'paid', 'rejected'], true) ? $request->query('status') : null;
        $all = Claim::with(['category:id,name', 'employee:id,full_name,employee_no'])->latest('spent_on');

        return Inertia::render('pay/Claims', [
            'claims' => (clone $all)->when($status, fn ($q) => $q->where('status', $status))->limit(200)->get()->map(fn (Claim $c) => $this->row($c) + ['employee' => $c->employee->full_name, 'employee_no' => $c->employee->employee_no]),
            'counts' => Claim::toBase()->selectRaw('status, count(*) n, sum(amount) total')->groupBy('status')->get()->keyBy('status')->map(fn ($r) => ['n' => (int) $r->n, 'total' => (float) $r->total]),
            'status' => $status,
            'currency' => $this->company->get()->base_currency,
        ]);
    }

    /** Receipts are private: the claimant, their approver chain (via the inbox) and payroll/HR can open them. */
    public function receipt(Request $request, Claim $claim): StreamedResponse
    {
        $user = $request->user();
        $mine = $user->employee()->value('id') === $claim->employee_id;
        $approver = $claim->employee()->value('manager_id') === $user->employee()->value('id');
        abort_unless($claim->receipt_path && ($mine || $approver || $user->can(Permission::PayrollView->value) || $user->can(Permission::EmployeesManage->value)), 404);

        return Storage::disk('local')->response($claim->receipt_path);
    }

    /** @return array<string, mixed> */
    private function row(Claim $c): array
    {
        return [
            'id' => $c->id, 'category' => $c->category->name, 'spent_on' => $c->spent_on->toDateString(), 'amount' => (float) $c->amount,
            'description' => $c->description, 'status' => $c->status, 'receipt' => $c->receipt_path ? route('claims.receipt', $c->id) : null,
        ];
    }
}
