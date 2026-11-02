<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkPattern;
use App\Services\Time\ApprovalService;
use App\Services\Time\LeaveService;
use App\Services\Time\OvertimeService;
use App\Services\Time\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Synthetic time data. Holiday dates are illustrative: Indonesian dates must be checked against
 * the official SKB 3 Menteri for the year, UK dates against gov.uk/bank-holidays.
 */
class TimeSeeder extends Seeder
{
    public function run(): void
    {
        $this->holidays();
        $nk = Company::where('name', 'Nusantara Kreatif')->firstOrFail();
        $nw = Company::where('name', 'Northwind Studio')->firstOrFail();

        $office = WorkPattern::create(['company_id' => $nk->id, 'name' => 'Office', 'start_time' => '08:30', 'end_time' => '17:30', 'grace_min' => 10, 'working_days' => [1, 2, 3, 4, 5], 'is_default' => true]);
        $release = WorkPattern::create(['company_id' => $nk->id, 'name' => 'Release support', 'start_time' => '09:00', 'end_time' => '18:00', 'grace_min' => 10, 'working_days' => [2, 3, 4, 5, 6]]);
        $night = WorkPattern::create(['company_id' => $nk->id, 'name' => 'Night support', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true, 'grace_min' => 10, 'working_days' => [1, 2, 3, 4, 5]]);
        WorkPattern::create(['company_id' => $nw->id, 'name' => 'Studio', 'start_time' => '09:00', 'end_time' => '17:30', 'grace_min' => 5, 'working_days' => [1, 2, 3, 4, 5], 'is_default' => true]);

        $this->assignPattern($nk, 'NK-0014', $release->id); // Dimas: Tue–Sat release rota
        $this->assignPattern($nk, 'NK-0022', $night->id);   // Joko: night IT support

        $this->leaveTypes($nk, $nw);
        $this->attendance($nk);
        $this->attendance($nw);
        $this->requests($nk);
        unset($office);

        // Nobody clocks in on a day of approved leave.
        LeaveRequest::withoutGlobalScopes()->where('status', 'approved')->get()->each(fn (LeaveRequest $l) => AttendanceRecord::withoutGlobalScopes()
            ->where('employee_id', $l->employee_id)->whereBetween('work_date', [$l->start_date->toDateString(), $l->end_date->toDateString()])->delete());
    }

    private function holidays(): void
    {
        $id = [
            ['2026-01-01', 'New Year\'s Day'], ['2026-01-16', 'Isra Mikraj'], ['2026-02-17', 'Chinese New Year'], ['2026-03-19', 'Nyepi'],
            ['2026-03-20', 'Idul Fitri'], ['2026-03-21', 'Idul Fitri'], ['2026-04-03', 'Good Friday'], ['2026-05-01', 'Labour Day'],
            ['2026-05-14', 'Ascension of Jesus'], ['2026-05-27', 'Idul Adha'], ['2026-05-31', 'Waisak'], ['2026-06-01', 'Pancasila Day'],
            ['2026-06-16', 'Islamic New Year'], ['2026-08-17', 'Independence Day'], ['2026-08-25', 'Prophet\'s Birthday'], ['2026-12-25', 'Christmas Day'],
            ['2027-01-01', 'New Year\'s Day'],
        ];
        $collective = [['2026-03-18', 'Cuti bersama Nyepi'], ['2026-03-23', 'Cuti bersama Idul Fitri'], ['2026-03-24', 'Cuti bersama Idul Fitri'], ['2026-12-24', 'Cuti bersama Natal']];
        $gb = [
            ['2026-01-01', 'New Year\'s Day'], ['2026-04-03', 'Good Friday'], ['2026-04-06', 'Easter Monday'], ['2026-05-04', 'Early May bank holiday'],
            ['2026-05-25', 'Spring bank holiday'], ['2026-08-31', 'Summer bank holiday'], ['2026-12-25', 'Christmas Day'], ['2026-12-28', 'Boxing Day (substitute)'],
            ['2027-01-01', 'New Year\'s Day'],
        ];
        foreach ($id as [$d, $n]) {
            Holiday::create(['country_code' => 'ID', 'date' => $d, 'name' => $n]);
        }
        foreach ($collective as [$d, $n]) {
            Holiday::create(['country_code' => 'ID', 'date' => $d, 'name' => $n, 'collective' => true]);
        }
        foreach ($gb as [$d, $n]) {
            Holiday::create(['country_code' => 'GB', 'region' => 'England & Wales', 'date' => $d, 'name' => $n]);
        }
    }

    private function assignPattern(Company $company, string $no, int $patternId): void
    {
        $employee = Employee::withoutGlobalScopes()->where('company_id', $company->id)->where('employee_no', $no)->firstOrFail();
        $employee->contracts()->update(['work_pattern_id' => $patternId]);
    }

    private function leaveTypes(Company $nk, Company $nw): void
    {
        $types = [
            [$nk, 'annual', 'Annual leave', true, 12, 'upfront', 12, 0, false, true, false, '#3A9D8F'],
            [$nk, 'sick', 'Sick leave', true, null, 'upfront', 0, 0, false, true, false, '#D9594C'],
            [$nk, 'marriage', 'Marriage leave', true, 3, 'upfront', 0, 0, false, false, false, '#8A5CD6'],
            [$nk, 'maternity', 'Maternity leave', true, 65, 'upfront', 0, 0, false, false, true, '#D46A9F'],
            [$nk, 'unpaid', 'Unpaid leave', false, null, 'upfront', 0, 0, false, false, true, '#8C9A96'],
            [$nw, 'annual', 'Annual leave', true, 25, 'monthly', 0, 5, false, true, false, '#3A9D8F'],
            [$nw, 'sick', 'Sick leave', true, null, 'upfront', 0, 0, false, true, false, '#D9594C'],
            [$nw, 'compassionate', 'Compassionate leave', true, 5, 'upfront', 0, 0, false, true, false, '#7FA7D9'],
            [$nw, 'unpaid', 'Unpaid leave', false, null, 'upfront', 0, 0, false, false, true, '#8C9A96'],
        ];
        foreach ($types as [$c, $code, $name, $paid, $days, $accrual, $min, $carry, $neg, $half, $second, $color]) {
            LeaveType::create([
                'company_id' => $c->id, 'code' => $code, 'name' => $name, 'paid' => $paid, 'annual_days' => $days, 'accrual' => $accrual,
                'min_service_months' => $min, 'max_carry_over' => $carry, 'allow_negative' => $neg, 'half_day_allowed' => $half,
                'requires_second_approval' => $second, 'color' => $color,
                'event_based' => in_array($code, ['marriage', 'maternity', 'compassionate'], true),
            ]);
        }
    }

    /** History from the 1st of last month up to yesterday: mostly on time, some late, one forgotten clock-out per company. */
    private function attendance(Company $company): void
    {
        mt_srand(4200 + $company->id);
        $calendar = app(WorkCalendar::class);
        $today = CarbonImmutable::now($company->timezone)->startOfDay();
        $people = Employee::withoutGlobalScopes()->where('company_id', $company->id)->where('status', '!=', 'resigned')->get();

        foreach ($people as $e) {
            $pattern = $calendar->patternFor($e);
            $from = max($today->subMonthNoOverflow()->startOfMonth(), CarbonImmutable::parse($e->hire_date, $company->timezone));
            $dates = $calendar->workingDates($e, $from, $today->subDay(), $pattern);

            foreach ($dates as $i => $date) {
                if (mt_rand(1, 100) === 1 && $i < count($dates) - 3) {
                    continue; // the odd unexplained absence, so reports have something to flag
                }
                $day = CarbonImmutable::parse($date, $company->timezone);
                $start = $day->setTimeFromTimeString($pattern->start_time);
                $late = mt_rand(1, 100) <= 8;
                $in = $late ? $start->addMinutes(mt_rand($pattern->grace_min + 2, 45)) : $start->subMinutes(mt_rand(0, 20))->addMinutes(mt_rand(0, $pattern->grace_min));
                $end = $pattern->crosses_midnight ? $day->addDay()->setTimeFromTimeString($pattern->end_time) : $day->setTimeFromTimeString($pattern->end_time);
                $out = $end->addMinutes(mt_rand(-5, 50));
                $forgot = ($e->employee_no === 'NK-0014' || $e->employee_no === 'NK-0015' || $e->employee_no === 'NW-004') && $i === count($dates) - 2;
                $lateMin = $late ? (int) $start->addMinutes($pattern->grace_min)->diffInMinutes($in) : 0;

                AttendanceRecord::create([
                    'company_id' => $company->id, 'employee_id' => $e->id, 'work_date' => $date,
                    'clock_in_at' => $in->utc(), 'clock_out_at' => $forgot ? null : $out->utc(),
                    'location_id' => $e->location_id, 'remote' => $e->location_id === null,
                    'in_distance_m' => mt_rand(4, 90),
                    'status' => $forgot ? 'incomplete' : ($lateMin > 0 ? 'late' : 'ok'), 'late_min' => $lateMin,
                ]);
            }
        }

        $this->today($company, $calendar, $today, $people);
    }

    /**
     * Today so far, by the real clock: most people are in, a couple are late, a few have not clocked in.
     * Nobody has clocked out before their shift ends.
     *
     * @param  Collection<int, Employee>  $people
     */
    private function today(Company $company, WorkCalendar $calendar, CarbonImmutable $today, $people): void
    {
        $now = CarbonImmutable::now($company->timezone);
        foreach ($people as $i => $e) {
            $pattern = $calendar->patternFor($e);
            if ($pattern->crosses_midnight || $calendar->workingDates($e, $today, $today, $pattern) === []) {
                continue;
            }
            $start = $today->setTimeFromTimeString($pattern->start_time);
            $end = $today->setTimeFromTimeString($pattern->end_time);
            if ($now->lessThan($start->subMinutes(20)) || $i % 11 === 3) {
                continue; // not in yet
            }
            $late = $i % 9 === 4;
            $in = $late ? $start->addMinutes($pattern->grace_min + 6 + $i % 20) : $start->subMinutes(5 + $i % 18);
            if ($in->greaterThan($now)) {
                continue;
            }
            $out = $now->greaterThan($end->addMinutes(40)) ? $end->addMinutes(3 + $i % 35) : null;
            AttendanceRecord::create([
                'company_id' => $company->id, 'employee_id' => $e->id, 'work_date' => $today->toDateString(),
                'clock_in_at' => $in->utc(), 'clock_out_at' => $out?->utc(), 'location_id' => $e->location_id, 'remote' => $e->location_id === null,
                'in_distance_m' => 6 + $i % 70, 'status' => $late ? 'late' : 'ok', 'late_min' => $late ? (int) $start->addMinutes($pattern->grace_min)->diffInMinutes($in) : 0,
            ]);
        }
    }

    private function requests(Company $nk): void
    {
        $leave = app(LeaveService::class);
        $approvals = app(ApprovalService::class);
        $overtime = app(OvertimeService::class);
        $e = fn (string $no) => Employee::withoutGlobalScopes()->where('company_id', $nk->id)->where('employee_no', $no)->firstOrFail();
        $type = fn (string $code) => LeaveType::withoutGlobalScopes()->where('company_id', $nk->id)->where('code', $code)->firstOrFail();
        $d = fn (string $date) => CarbonImmutable::parse($date);
        $approveAll = function (LeaveRequest $l) use ($approvals) {
            $request = ApprovalRequest::withoutGlobalScopes()->where('subject_type', LeaveRequest::class)->where('subject_id', $l->id)->firstOrFail();
            foreach ($request->steps as $step) {
                $approver = $step->approver_id ? User::find(Employee::withoutGlobalScopes()->find($step->approver_id)?->user_id) : User::where('email', 'hr@roster.test')->first();
                if ($approver) {
                    setPermissionsTeamId($l->company_id);
                    $approvals->decide($step->fresh(), $approver, true);
                } else {
                    $step->update(['status' => 'approved', 'decided_at' => now()]);
                    $request->update(['status' => 'approved', 'decided_at' => now()]);
                    $l->update(['status' => 'approved']);
                }
            }
        };

        // Approved history and current absences
        $approveAll($leave->request($e('NK-0012'), $type('sick'), $d('2026-10-19'), $d('2026-11-06'), 'none', 'Recovering from surgery; doctor\'s note on file.'));
        $approveAll($leave->request($e('NK-0015'), $type('annual'), $d('2026-11-09'), $d('2026-11-10'), 'none', 'Family visit in Cirebon'));
        $approveAll($leave->request($e('NK-0014'), $type('annual'), $d('2026-12-29'), $d('2026-12-31'), 'none', 'Year-end trip'));
        $approveAll($leave->request($e('NK-0018'), $type('annual'), $d('2026-10-12'), $d('2026-10-16'), 'none', 'Bali'));

        // Waiting for Bima (manager demo login)
        $leave->request($e('NK-0011'), $type('annual'), $d('2026-11-23'), $d('2026-11-27'), 'none', 'Wedding of a close friend in Medan');
        $leave->request($e('NK-0013'), $type('annual'), $d('2026-12-21'), $d('2027-01-04'), 'none', 'Christmas with family in Manado');
        $overtime->request($e('NK-0013'), $d('2026-10-29'), '18:00', '21:30', 'Hotfix for checkout bug before the 1 Nov campaign');

        $ilham = $e('NK-0015');
        $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $ilham->id)->where('status', 'incomplete')->first();
        if ($record) {
            $tz = $nk->timezone;
            $day = CarbonImmutable::parse($record->work_date->toDateString(), $tz);
            $correction = $record->corrections()->create([
                'requested_by' => $ilham->id, 'original_in' => $record->clock_in_at, 'original_out' => null,
                'new_in' => $record->clock_in_at, 'new_out' => $day->setTime(17, 42)->utc(),
                'reason' => 'Phone battery died before I could clock out. Left at 17:42 with Putri.',
            ]);
            $approvals->submit('attendance_correction', $correction, $ilham);
        }

        // Waiting for HR (Dewi): Managing Director has no manager; unpaid leave needs a second approval
        $leave->request($e('NK-0001'), $type('annual'), $d('2026-12-14'), $d('2026-12-18'), 'none', 'Board retreat followed by family time');
        $wulan = $leave->request($e('NK-0025'), $type('unpaid'), $d('2026-11-16'), $d('2026-11-20'), 'none', 'Moving house to Bandung');
        $step = ApprovalRequest::withoutGlobalScopes()->where('subject_type', LeaveRequest::class)->where('subject_id', $wulan->id)->firstOrFail()->steps()->where('step', 1)->first();
        $step->update(['status' => 'approved', 'decided_at' => now()->subHours(3), 'comment' => 'Fine by me; Andi covers her accounts.']);
        $step->request->steps()->where('step', 2)->update(['status' => 'pending']);
        $step->request->update(['current_step' => 2]);
    }
}
