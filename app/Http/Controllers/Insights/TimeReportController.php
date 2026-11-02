<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Time\TimeReports;
use App\Support\Csv;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Insights → Reports: monthly attendance summary and leave balances, on screen and as CSV. */
class TimeReportController extends Controller
{
    public function __construct(private TimeReports $reports, private CurrentCompany $company) {}

    public function index(Request $request): Response
    {
        [$report, $period, $today] = $this->params($request);
        $people = $this->reports->people($request->user(), $today);

        return Inertia::render('insights/Reports', [
            'report' => $report,
            'period' => $period,
            'today' => $today->toDateString(),
            'scope' => $request->user()->can('employees.view_all') ? 'company' : 'team',
            'attendance' => $report === 'attendance' ? $this->reports->attendance($people, CarbonImmutable::parse("$period-01"), $today) : null,
            'leave' => $report === 'leave' ? $this->reports->leaveBalances($people, (int) $period) : null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$report, $period, $today] = $this->params($request);
        $people = $this->reports->people($request->user(), $today);
        $slug = Str::slug($this->company->get()->name);

        if ($report === 'attendance') {
            $header = ['Employee no', 'Name', 'Department', 'Working days', 'Days present', 'Late arrivals', 'Late minutes', 'Missing clock-outs', 'Leave days', 'Days with no record', 'Attendance rate %'];
            $lines = array_map(fn ($r) => [$r['employee_no'], $r['name'], $r['department'], $r['expected'], $r['present'], $r['late'], $r['late_min'], $r['incomplete'], $r['leave'], $r['absent'], $r['rate']],
                $this->reports->attendance($people, CarbonImmutable::parse("$period-01"), $today));
        } else {
            $data = $this->reports->leaveBalances($people, (int) $period);
            $header = ['Employee no', 'Name', 'Department', 'Leave type', 'Entitled', 'Carried over', 'Adjustment', 'Used', 'Pending', 'Available'];
            $lines = [];
            foreach ($data['rows'] as $r) {
                foreach ($data['types'] as $t) {
                    $b = $r['balances'][$t['id']];
                    $lines[] = [$r['employee_no'], $r['name'], $r['department'], $t['name'], $b['eligible'] ? $b['entitled'] : 'not yet eligible', $b['carried'], $b['adjustment'], $b['used'], $b['pending'], $b['available']];
                }
            }
        }

        return Csv::download("roster-{$slug}-{$report}-{$period}.csv", $header, $lines);
    }

    /** @return array{0: string, 1: string, 2: CarbonImmutable} */
    private function params(Request $request): array
    {
        abort_unless($request->user()->can('viewAny', Employee::class), 403);
        $today = CarbonImmutable::now($this->company->get()->timezone)->startOfDay();
        $data = $request->validate([
            'report' => ['nullable', 'in:attendance,leave'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}(-(0[1-9]|1[0-2]))?$/'],
        ]);
        $report = $data['report'] ?? 'attendance';
        $period = $data['period'] ?? null;

        if ($report === 'attendance') {
            $period = $period !== null && strlen($period) === 7 ? $period : $today->subDay()->format('Y-m');
            abort_if($period > $today->format('Y-m'), 422, 'That month has not started yet.');
        } else {
            $period = $period !== null ? substr($period, 0, 4) : (string) $today->year;
        }

        return [$report, $period, $today];
    }
}
