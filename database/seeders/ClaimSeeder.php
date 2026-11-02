<?php

namespace Database\Seeders;

use App\Models\Claim;
use App\Models\ClaimCategory;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRuns;
use App\Services\Time\ApprovalService;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Claim categories and a few claims: one paid last month, one approved for this month's run, one waiting for the manager. */
class ClaimSeeder extends Seeder
{
    public function run(): void
    {
        $nk = Company::where('name', 'Nusantara Kreatif')->firstOrFail();
        app(CurrentCompany::class)->set($nk);
        $cat = fn (string $code, string $name, ?int $limit, bool $receipt = true) => ClaimCategory::create(['company_id' => $nk->id, 'code' => $code, 'name' => $name, 'monthly_limit' => $limit, 'receipt_required' => $receipt]);
        $medical = $cat('medical', 'Medical', 1_500_000);
        $transport = $cat('transport', 'Transport & parking', 750_000);
        $meals = $cat('client_meals', 'Client meals', 1_000_000);
        $cat('internet', 'Home internet', 300_000, false);

        $e = fn (string $no) => Employee::where('employee_no', $no)->firstOrFail();
        $open = PayrollRun::where('status', 'calculated')->orderByDesc('period_start')->firstOrFail();
        $last = PayrollRun::where('status', 'published')->orderByDesc('period_start')->firstOrFail();
        $start = CarbonImmutable::parse($open->period_start->toDateString());

        // Paid with last month's payroll.
        Claim::create(['company_id' => $nk->id, 'employee_id' => $e('NK-0024')->id, 'claim_category_id' => $transport->id, 'spent_on' => $start->subMonthNoOverflow()->setDay(9)->toDateString(),
            'amount' => 385_000, 'description' => 'Taxi to Batik Keraton pitch and back', 'status' => 'paid', 'payroll_run_id' => $last->id]);

        // Approved this month: appears in the open run as a non-taxable reimbursement.
        $approvals = app(ApprovalService::class);
        $approved = Claim::create(['company_id' => $nk->id, 'employee_id' => $e('NK-0013')->id, 'claim_category_id' => $meals->id, 'spent_on' => $start->toDateString(),
            'amount' => 642_000, 'description' => 'Lunch with Sukha Living team, kickoff']);
        $request = $approvals->submit('claim', $approved, $e('NK-0013'));
        $request->steps()->update(['status' => 'approved', 'decided_at' => now()]);
        $request->update(['status' => 'approved', 'decided_at' => now()]);
        $approved->update(['status' => 'approved']);

        // Waiting for the manager.
        $pending = Claim::create(['company_id' => $nk->id, 'employee_id' => $e('NK-0015')->id, 'claim_category_id' => $medical->id, 'spent_on' => CarbonImmutable::now($nk->timezone)->subDay()->toDateString(),
            'amount' => 475_000, 'description' => 'Dental check-up, Klinik Senyum Kuningan']);
        $approvals->submit('claim', $pending, $e('NK-0015'));

        app(PayrollRuns::class)->calculate($open);
        app(CurrentCompany::class)->forget();
    }
}
