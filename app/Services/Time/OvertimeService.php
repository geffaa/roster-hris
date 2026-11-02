<?php

namespace App\Services\Time;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeService
{
    public function __construct(private ApprovalService $approvals) {}

    public function request(Employee $employee, CarbonImmutable $date, string $start, string $end, string $reason): OvertimeRequest
    {
        $from = $date->setTimeFromTimeString($start);
        $to = $date->setTimeFromTimeString($end);
        if ($to->lessThanOrEqualTo($from)) {
            $to = $to->addDay(); // e.g. 20:00–01:00
        }
        $minutes = (int) $from->diffInMinutes($to);

        if ($minutes < 30 || $minutes > 12 * 60) {
            throw ValidationException::withMessages(['end_time' => __('Overtime must be between 30 minutes and 12 hours.')]);
        }
        if ($date->greaterThan(CarbonImmutable::today()->addDays(30))) {
            throw ValidationException::withMessages(['work_date' => __('Overtime can be requested up to 30 days ahead.')]);
        }

        return DB::transaction(function () use ($employee, $date, $start, $end, $minutes, $reason) {
            $overtime = OvertimeRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'work_date' => $date->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'minutes' => $minutes,
                'reason' => $reason,
            ]);
            $this->approvals->submit('overtime', $overtime, $employee);

            return $overtime;
        });
    }
}
