<?php

namespace App\Http\Controllers\Insights;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollReports;
use App\Services\Time\TimeReports;
use App\Support\CurrentCompany;
use App\Support\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** S14 — Insights → Reports: attendance, leave balances, payroll summary, contributions; on screen, CSV and XLSX. */
class ReportController extends Controller
{
    private const TIME = ['attendance', 'leave'];

    private const PAY = ['payroll', 'contributions'];

    public function __construct(private TimeReports $time, private PayrollReports $pay, private CurrentCompany $company) {}

    public function index(Request $request): Response
    {
        [$report, $period, $today, $run] = $this->params($request);
        $user = $request->user();
        $people = in_array($report, self::TIME, true) ? $this->time->people($user, $today) : collect();

        return Inertia::render('insights/Reports', [
            'report' => $report,
            'period' => $period,
            'today' => $today->toDateString(),
            'scope' => $user->can(Permission::EmployeesViewAll->value) ? 'company' : 'team',
            'available' => [
                'time' => $user->can('viewAny', Employee::class),
                'pay' => $user->can(Permission::PayrollView->value),
            ],
            'months' => $user->can(Permission::PayrollView->value) ? $this->pay->finalRuns()->map(fn (PayrollRun $r) => $r->period_start->format('Y-m'))->values() : [],
            'currency' => $this->company->get()->base_currency,
            'attendance' => $report === 'attendance' ? $this->time->attendance($people, CarbonImmutable::parse("$period-01"), $today) : null,
            'leave' => $report === 'leave' ? $this->time->leaveBalances($people, (int) $period) : null,
            'payroll' => $report === 'payroll' && $run ? $this->pay->summary($run) + ['reference' => $run->reference, 'status' => $run->status] : null,
            'contributions' => $report === 'contributions' && $run ? $this->pay->contributions($run) + ['reference' => $run->reference] : null,
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        [$report, $period, $today, $run] = $this->params($request);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $base = 'roster-'.Str::slug($this->company->get()->name)."-{$report}-{$period}";

        if ($report === 'attendance') {
            $rows = array_map(fn ($r) => [$r['employee_no'], $r['name'], $r['department'], $r['expected'], $r['present'], $r['late'], $r['late_min'], $r['incomplete'], $r['leave'], $r['absent'], $r['rate']],
                $this->time->attendance($this->time->people($request->user(), $today), CarbonImmutable::parse("$period-01"), $today));

            return Spreadsheet::download($base, $format, ['Employee no', 'Name', 'Department', 'Working days', 'Days present', 'Late arrivals', 'Late minutes', 'Missing clock-outs', 'Leave days', 'Days with no record', 'Attendance rate %'], $rows);
        }
        if ($report === 'leave') {
            $data = $this->time->leaveBalances($this->time->people($request->user(), $today), (int) $period);
            $rows = [];
            foreach ($data['rows'] as $r) {
                foreach ($data['types'] as $t) {
                    $b = $r['balances'][$t['id']];
                    $rows[] = [$r['employee_no'], $r['name'], $r['department'], $t['name'], $b['eligible'] ? $b['entitled'] : 'not yet eligible', $b['carried'], $b['adjustment'], $b['used'], $b['pending'], $b['available']];
                }
            }

            return Spreadsheet::download($base, $format, ['Employee no', 'Name', 'Department', 'Leave type', 'Entitled', 'Carried over', 'Adjustment', 'Used', 'Pending', 'Available'], $rows);
        }
        abort_unless($run !== null, 404);
        if ($report === 'payroll') {
            $s = $this->pay->summary($run);
            $rows = array_map(fn ($r) => array_values($r), [...$s['rows'], $s['total']]);

            return Spreadsheet::download($base, $format, ['Department', 'People', 'Gross', 'Tax', 'Employee contributions', 'Other deductions', 'Net', 'Employer contributions', 'Employer cost'], $rows);
        }
        $c = $this->pay->contributions($run);
        $header = ['Employee no', 'Name'];
        foreach ($c['codes'] as $code) {
            array_push($header, "{$code['name']} (employee)", "{$code['name']} (employer)");
        }
        $rows = array_map(function ($p) use ($c) {
            $row = [$p['employee_no'], $p['name']];
            foreach ($c['codes'] as $code) {
                array_push($row, $p["{$code['code']}_employee"] ?? 0, $p["{$code['code']}_employer"] ?? 0);
            }

            return $row;
        }, $c['people']);

        return Spreadsheet::download($base, $format, $header, $rows);
    }

    /** @return array{0: string, 1: string, 2: CarbonImmutable, 3: ?PayrollRun} */
    private function params(Request $request): array
    {
        $user = $request->user();
        $canTime = $user->can('viewAny', Employee::class);
        $canPay = $user->can(Permission::PayrollView->value);
        abort_unless($canTime || $canPay, 403);

        $today = CarbonImmutable::now($this->company->get()->timezone)->startOfDay();
        $data = $request->validate([
            'report' => ['nullable', 'in:attendance,leave,payroll,contributions'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}(-(0[1-9]|1[0-2]))?$/'],
        ]);
        $report = $data['report'] ?? ($canTime ? 'attendance' : 'payroll');
        abort_if(in_array($report, self::TIME, true) && ! $canTime, 403);
        abort_if(in_array($report, self::PAY, true) && ! $canPay, 403);
        $period = $data['period'] ?? null;
        $run = null;

        if ($report === 'attendance') {
            $period = $period !== null && strlen($period) === 7 ? $period : $today->subDay()->format('Y-m');
            abort_if($period > $today->format('Y-m'), 422, 'That month has not started yet.');
        } elseif ($report === 'leave') {
            $period = $period !== null ? substr($period, 0, 4) : (string) $today->year;
        } else {
            $runs = $this->pay->finalRuns();
            $run = $period !== null && strlen($period) === 7 ? $runs->first(fn ($r) => $r->period_start->format('Y-m') === $period) : $runs->first();
            abort_if($period !== null && strlen($period) === 7 && ! $run, 404, 'No locked payroll for that month.');
            $period = $run?->period_start->format('Y-m') ?? $today->format('Y-m');
        }

        return [$report, $period, $today, $run];
    }
}
