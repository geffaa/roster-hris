<?php

namespace App\Services\Time;

use App\Exceptions\ClockException;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\WorkPattern;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Clock-in/out using the server clock in the company time zone. Device time is never trusted.
 */
class ClockService
{
    public function __construct(private WorkCalendar $calendar) {}

    public function now(Company $company): CarbonImmutable
    {
        return CarbonImmutable::now($company->timezone);
    }

    /**
     * The shift a moment belongs to: for a night shift (22:00–06:00), 02:00 still belongs to yesterday's shift.
     */
    public function workDate(?WorkPattern $pattern, CarbonImmutable $localNow): CarbonImmutable
    {
        if ($pattern?->crosses_midnight && $localNow->format('H:i:s') < $this->addHours($pattern->end_time, 4)) {
            return $localNow->subDay()->startOfDay();
        }

        return $localNow->startOfDay();
    }

    public function shiftStart(?WorkPattern $pattern, CarbonImmutable $workDate): ?CarbonImmutable
    {
        return $pattern ? $workDate->setTimeFromTimeString($pattern->start_time) : null;
    }

    public function shiftEnd(?WorkPattern $pattern, CarbonImmutable $workDate): ?CarbonImmutable
    {
        if (! $pattern) {
            return null;
        }
        $end = $workDate->setTimeFromTimeString($pattern->end_time);

        return $pattern->crosses_midnight ? $end->addDay() : $end;
    }

    public function clockIn(Employee $employee, ?float $lat, ?float $lng): AttendanceRecord
    {
        $company = Company::findOrFail($employee->company_id);
        $pattern = $this->calendar->patternFor($employee);
        $now = $this->now($company);
        $workDate = $this->workDate($pattern, $now);

        if ($holiday = $this->calendar->holidayOn($company, $workDate)) {
            throw new ClockException(__('Today is a public holiday (:name). If you are working, ask your manager to log overtime.', ['name' => $holiday]), 'holiday');
        }
        if ($pattern && ! in_array($workDate->isoWeekday(), $pattern->working_days, true)) {
            throw new ClockException(__('You have no shift today. If you are working, ask your manager to log overtime.'), 'day_off');
        }

        $existing = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->whereDate('work_date', $workDate)->first();
        if ($existing) {
            throw new ClockException($existing->clock_out_at
                ? __('You already clocked out for today.')
                : __('You clocked in at :time.', ['time' => $existing->clock_in_at->setTimezone($company->timezone)->format('H:i')]), 'duplicate');
        }

        $start = $this->shiftStart($pattern, $workDate);
        $end = $this->shiftEnd($pattern, $workDate);
        if ($start && $now->lessThan($start->subHours(2))) {
            throw new ClockException(__('Clock-in opens at :time, two hours before your shift.', ['time' => $start->subHours(2)->format('H:i')]), 'too_early');
        }
        if ($end && $now->greaterThanOrEqualTo($end)) {
            throw new ClockException(__('Your shift ended at :time. If you worked today, send a correction or request overtime.', ['time' => $end->format('H:i')]), 'too_late');
        }

        [$distance, $remote, $locationId] = $this->checkLocation($employee, $lat, $lng);

        $lateMin = 0;
        if ($start && $pattern && $now->greaterThan($start->addMinutes($pattern->grace_min))) {
            $lateMin = (int) $start->addMinutes($pattern->grace_min)->diffInMinutes($now);
        }

        try {
            return AttendanceRecord::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'work_date' => $workDate->toDateString(),
                'clock_in_at' => $now->utc(),
                'in_lat' => $lat,
                'in_lng' => $lng,
                'in_distance_m' => $distance,
                'location_id' => $locationId,
                'remote' => $remote,
                'status' => $lateMin > 0 ? 'late' : 'ok',
                'late_min' => $lateMin,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two taps within the same second: the first one won.
            throw new ClockException(__('You are already clocked in.'), 'duplicate');
        }
    }

    public function clockOut(Employee $employee, ?float $lat, ?float $lng): AttendanceRecord
    {
        $company = Company::findOrFail($employee->company_id);
        $now = $this->now($company);

        $record = AttendanceRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereNull('clock_out_at')
            ->whereDate('work_date', '>=', $now->subDay()->toDateString())
            ->latest('clock_in_at')
            ->first();

        if (! $record) {
            throw new ClockException(__('There is no open shift to clock out from.'), 'not_clocked_in');
        }

        $record->update(['clock_out_at' => $now->utc(), 'out_lat' => $lat, 'out_lng' => $lng]);

        return $record;
    }

    /**
     * @return array{0: ?int, 1: bool, 2: ?int} distance in metres, remote flag, location id
     */
    public function checkLocation(Employee $employee, ?float $lat, ?float $lng): array
    {
        $location = $employee->location_id ? Location::withoutGlobalScopes()->find($employee->location_id) : null;

        if (! $location || $location->allow_remote || $location->lat === null || $location->lng === null) {
            return [null, true, $location?->id];
        }
        if ($lat === null || $lng === null) {
            throw new ClockException(__('Turn on location so we can confirm you are at :place.', ['place' => $location->name]), 'no_location');
        }

        $distance = Geo::distance($lat, $lng, (float) $location->lat, (float) $location->lng);
        if ($distance > $location->radius_m) {
            throw new ClockException(__('You are :distance from :place. Clock-in works within :radius m.', ['distance' => Geo::human($distance), 'place' => $location->name, 'radius' => $location->radius_m]), 'outside');
        }

        return [$distance, false, $location->id];
    }

    /** 12 → "12 min", 135 → "2 h 15 min". */
    public static function humanMinutes(int $minutes): string
    {
        return $minutes < 60 ? "{$minutes} min" : sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60);
    }

    private function addHours(string $time, int $hours): string
    {
        return CarbonImmutable::createFromTimeString($time)->addHours($hours)->format('H:i:s');
    }
}
