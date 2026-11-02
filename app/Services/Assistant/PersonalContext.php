<?php

namespace App\Services\Assistant;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Services\Time\LeaveService;
use App\Services\Time\WorkCalendar;
use Carbon\CarbonImmutable;

/**
 * The signed-in employee's own HR facts, built on the server for this person only.
 * The assistant never receives anyone else's data, so it cannot leak it.
 */
class PersonalContext
{
    public function __construct(private LeaveService $leave, private WorkCalendar $calendar) {}

    public function for(Employee $e): string
    {
        $company = Company::findOrFail($e->company_id);
        $today = CarbonImmutable::now($company->timezone);
        $lines = ["Name: {$e->full_name}", "Job: {$e->job_title}", 'Joined: '.$e->hire_date->toDateString(), 'Today: '.$today->toDateString()];

        $pattern = $this->calendar->patternFor($e);
        if ($pattern) {
            $lines[] = 'Work pattern: '.$pattern->label();
        }
        foreach (LeaveType::orderBy('id')->get() as $t) {
            $b = $this->leave->balance($e, $t, $today->year);
            $lines[] = $t->isLimited() && ! $t->event_based
                ? sprintf('%s %d: %s days available (entitled %s, used %s, pending %s)', $t->name, $today->year, $this->n($b['available']), $this->n($b['entitled']), $this->n($b['used']), $this->n($b['pending']))
                : sprintf('%s %d: %s days taken%s', $t->name, $today->year, $this->n($b['used']), $t->event_based && $b['entitled'] ? ', '.$this->n($b['entitled']).' days per event' : '');
        }
        $pending = LeaveRequest::with('type:id,name')->where('employee_id', $e->id)->where('status', 'pending')->orderBy('start_date')->get();
        foreach ($pending as $p) {
            $lines[] = "Pending request: {$p->type->name} {$p->start_date->toDateString()} to {$p->end_date->toDateString()} ({$this->n((float) $p->days)} days)";
        }
        $slip = Payslip::with('item.run:id,period_start,pay_date')->where('employee_id', $e->id)->latest('published_at')->first();
        if ($slip) {
            $i = $slip->item;
            $lines[] = sprintf('Latest payslip: %s, paid %s, gross %s, tax %s, employee contributions %s, net %s %s', $i->run->period_start->format('F Y'), $i->run->pay_date->toDateString(),
                number_format((float) $i->gross), number_format((float) $i->tax), number_format((float) $i->employee_contributions), $i->currency, number_format((float) $i->net));
        }

        return implode("\n", $lines);
    }

    private function n(float|int|null $v): string
    {
        return $v === null ? 'unlimited' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    }
}
