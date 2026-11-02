<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollRuns;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Calculates a run outside the request so the review screen can show progress for large companies. */
class CalculatePayrollRun implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $runId, public ?int $userId) {}

    public function handle(PayrollRuns $runs, CurrentCompany $current): void
    {
        $run = PayrollRun::withoutGlobalScopes()->findOrFail($this->runId);
        $current->set(Company::findOrFail($run->company_id));
        $runs->calculate($run, $this->userId ? User::find($this->userId) : null);
    }
}
