<?php

namespace App\Services\Time;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkPattern;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/** Which days an employee is expected to work: their shift pattern minus public holidays of the company's country. */
class WorkCalendar
{
    /** @var array<string, array<string, string>> */
    private array $holidayCache = [];

    public function patternFor(Employee $employee): ?WorkPattern
    {
        $patternId = $employee->contracts()->value('work_pattern_id');

        return ($patternId ? WorkPattern::withoutGlobalScopes()->find($patternId) : null)
            ?? WorkPattern::withoutGlobalScopes()->where('company_id', $employee->company_id)->where('is_default', true)->first();
    }

    /** @return array<string, string> date => holiday name */
    public function holidays(Company $company, CarbonInterface $from, CarbonInterface $to): array
    {
        $key = $company->country_code.$from->toDateString().$to->toDateString();

        return $this->holidayCache[$key] ??= Holiday::query()
            ->where('country_code', $company->country_code)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->date->toDateString() => $h->name])
            ->all();
    }

    public function holidayOn(Company $company, CarbonInterface $date): ?string
    {
        return $this->holidays($company, $date, $date)[$date->toDateString()] ?? null;
    }

    /** @return list<string> ISO dates the employee is expected to work between $from and $to inclusive. */
    public function workingDates(Employee $employee, CarbonInterface $from, CarbonInterface $to, ?WorkPattern $pattern = null): array
    {
        $pattern ??= $this->patternFor($employee);
        $days = $pattern === null ? [1, 2, 3, 4, 5] : $pattern->working_days;
        $company = Company::findOrFail($employee->company_id);
        $holidays = $this->holidays($company, $from, $to);

        $dates = [];
        foreach (CarbonPeriod::create(CarbonImmutable::parse($from->toDateString()), CarbonImmutable::parse($to->toDateString())) as $day) {
            if (in_array($day->isoWeekday(), $days, true) && ! isset($holidays[$day->toDateString()])) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }
}
