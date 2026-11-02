<?php

namespace Database\Seeders;

use App\Models\BankAccountChange;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeIdentifier;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollRuns;
use App\Services\Time\ApprovalService;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Models\Audit;

/**
 * A few real actions done as real demo users, with auditing on, so the audit log, the HR inbox
 * and the payroll approvals start from a believable state instead of empty.
 */
class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        config(['audit.console' => true]);
        $nk = Company::where('name', 'Nusantara Kreatif')->firstOrFail();
        app(CurrentCompany::class)->set($nk);
        $hr = User::where('email', 'hr@roster.test')->firstOrFail();
        $payroll = User::where('email', 'payroll@roster.test')->firstOrFail();
        $dimasUser = User::where('email', 'employee@roster.test')->firstOrFail();
        $dimas = Employee::where('employee_no', 'NK-0014')->firstOrFail();

        // HR: a promotion with a raise that starts in January.
        Auth::setUser($hr);
        $dimas->update(['job_title' => 'Senior Mobile Engineer']);
        $dimas->compensations()->create(['effective_from' => '2027-01-01', 'base_salary' => 22_500_000, 'currency' => 'IDR', 'tax_status' => 'TK/0', 'reason' => 'Promotion to Senior Mobile Engineer', 'created_by' => $hr->id]);

        // Payroll officer: looked at a full NIK to fix a BPJS registration.
        Auth::setUser($payroll);
        $agus = Employee::where('employee_no', 'NK-0005')->firstOrFail();
        $nik = EmployeeIdentifier::where('employee_id', $agus->id)->where('type', 'nik')->firstOrFail();
        Audit::create([
            'company_id' => $nk->id, 'user_type' => User::class, 'user_id' => $payroll->id, 'event' => 'revealed',
            'auditable_type' => EmployeeIdentifier::class, 'auditable_id' => $nik->id, 'old_values' => [], 'new_values' => ['type' => 'nik'],
            'url' => url('/employees/'.$agus->id), 'ip_address' => '10.20.4.17', 'user_agent' => 'Mozilla/5.0 (Macintosh)', 'tags' => 'pii',
        ]);

        // Employee: asks to be paid into a new account; it waits in the HR queue.
        Auth::setUser($dimasUser);
        $change = BankAccountChange::create(['company_id' => $nk->id, 'employee_id' => $dimas->id, 'bank_name' => 'BCA', 'account_number' => '8730 1122 4409', 'holder_name' => 'Dimas Arya']);
        app(ApprovalService::class)->submit('bank_change', $change, $dimas, hrOnly: true);

        // Payroll officer: reviewed the flagged bonus and gave the first approval for the month in progress.
        Auth::setUser($payroll);
        $runs = app(PayrollRuns::class);
        $open = PayrollRun::where('status', 'calculated')->orderByDesc('period_start')->firstOrFail();
        foreach ($open->items()->get() as $item) {
            if ($item->needsReview()) {
                $runs->review($item, $payroll);
            }
        }
        $runs->approve($open->refresh(), $payroll);

        Auth::forgetUser();
        app(CurrentCompany::class)->forget();
        config(['audit.console' => false]);
    }
}
