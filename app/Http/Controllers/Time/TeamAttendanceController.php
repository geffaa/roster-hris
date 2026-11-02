<?php

namespace App\Http\Controllers\Time;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Time\AttendanceBoard;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** S06 — who is in today: on time, late, missing, on leave; for HR (company) or managers (team). */
class TeamAttendanceController extends Controller
{
    public function __construct(private AttendanceBoard $board, private CurrentCompany $company) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('viewAny', Employee::class), 403);
        $company = $this->company->get();
        $tz = $company->timezone;
        $date = CarbonImmutable::parse($request->validate(['date' => ['nullable', 'date']])['date'] ?? CarbonImmutable::now($tz)->toDateString(), $tz)->startOfDay();

        ['rows' => $list, 'holiday' => $holiday] = $this->board->day($user, $company, $date);
        $rows = collect($list);

        return Inertia::render('time/Attendance', [
            'date' => $date->toDateString(),
            'today' => CarbonImmutable::now($tz)->toDateString(),
            'holiday' => $holiday,
            'rows' => $rows,
            'summary' => $rows->countBy('state'),
            'scope' => $user->can(Permission::EmployeesViewAll->value) ? 'company' : 'team',
        ]);
    }
}
