<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Services\Time\ClockService;
use App\Services\Time\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Marks shifts with no clock-out as incomplete four hours after the shift should have ended. */
class CloseIncompleteAttendance extends Command
{
    protected $signature = 'attendance:close-incomplete';

    protected $description = 'Flag open attendance records whose shift ended more than four hours ago';

    public function handle(ClockService $clock, WorkCalendar $calendar): int
    {
        $flagged = 0;

        AttendanceRecord::withoutGlobalScopes()->whereNull('clock_out_at')->where('status', '!=', 'incomplete')->chunkById(200, function ($records) use ($clock, $calendar, &$flagged) {
            foreach ($records as $record) {
                $employee = Employee::withoutGlobalScopes()->find($record->employee_id);
                $company = Company::find($record->company_id);
                if (! $employee || ! $company) {
                    continue;
                }
                $pattern = $calendar->patternFor($employee);
                $workDate = CarbonImmutable::parse($record->work_date->toDateString(), $company->timezone);
                $end = $clock->shiftEnd($pattern, $workDate) ?? $workDate->endOfDay();

                if (CarbonImmutable::now($company->timezone)->greaterThan($end->addHours(4))) {
                    $record->update(['status' => 'incomplete']);
                    $flagged++;
                }
            }
        });

        $this->info("Flagged {$flagged} incomplete records.");

        return self::SUCCESS;
    }
}
