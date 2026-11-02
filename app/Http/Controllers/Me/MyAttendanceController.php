<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\OvertimeRequest;
use App\Services\Time\ApprovalService;
use App\Services\Time\OvertimeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** own history, correction requests, overtime requests. */
class MyAttendanceController extends Controller
{
    public function __construct(private ApprovalService $approvals, private OvertimeService $overtime) {}

    public function index(Request $request): Response
    {
        $employee = TodayController::employeeOf($request);
        $tz = Company::findOrFail($employee->company_id)->timezone;

        $records = AttendanceRecord::with(['corrections' => fn ($q) => $q->latest()])
            ->where('employee_id', $employee->id)->orderByDesc('work_date')->limit(31)->get();

        return Inertia::render('me/Attendance', [
            'timezone' => $tz,
            'records' => $records->map(fn (AttendanceRecord $r) => [
                'id' => $r->id,
                'date' => $r->work_date->toDateString(),
                'in' => $r->clock_in_at->setTimezone($tz)->format('H:i'),
                'out' => $r->clock_out_at?->setTimezone($tz)->format('H:i'),
                'status' => $r->status,
                'late_min' => $r->late_min,
                'remote' => $r->remote,
                'hours' => $r->clock_out_at ? round($r->clock_in_at->diffInMinutes($r->clock_out_at) / 60, 1) : null,
                'correction' => ($c = $r->corrections->first()) ? ['status' => $c->status, 'reason' => $c->reason] : null,
            ]),
            'overtime' => OvertimeRequest::where('employee_id', $employee->id)->latest('work_date')->limit(10)->get()->map(fn (OvertimeRequest $o) => [
                'id' => $o->id, 'date' => $o->work_date->toDateString(), 'start' => substr($o->start_time, 0, 5), 'end' => substr($o->end_time, 0, 5),
                'minutes' => $o->minutes, 'status' => $o->status, 'reason' => $o->reason,
            ]),
        ]);
    }

    public function correct(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $employee = TodayController::employeeOf($request);
        abort_unless($record->employee_id === $employee->id, 404);
        $data = $request->validate([
            'in' => ['required', 'date_format:H:i'],
            'out' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        if ($record->corrections()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['reason' => __('A correction for this day is already waiting for approval.')]);
        }

        $tz = Company::findOrFail($employee->company_id)->timezone;
        $day = CarbonImmutable::parse($record->work_date->toDateString(), $tz);
        $in = $day->setTimeFromTimeString($data['in']);
        $out = $day->setTimeFromTimeString($data['out']);
        if ($out->lessThanOrEqualTo($in)) {
            $out = $out->addDay(); // night shift ending after midnight
        }
        if ($in->diffInHours($out) > 16) {
            throw ValidationException::withMessages(['out' => __('A shift cannot be longer than 16 hours.')]);
        }

        DB::transaction(function () use ($record, $employee, $in, $out, $data) {
            $correction = $record->corrections()->create([
                'requested_by' => $employee->id,
                'original_in' => $record->clock_in_at,
                'original_out' => $record->clock_out_at,
                'new_in' => $in->utc(),
                'new_out' => $out->utc(),
                'reason' => $data['reason'],
            ]);
            $this->approvals->submit('attendance_correction', $correction, $employee);
        });

        return back()->with('success', __('Correction sent to your manager.'));
    }

    public function overtime(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $o = $this->overtime->request(TodayController::employeeOf($request), CarbonImmutable::parse($data['work_date']), $data['start_time'], $data['end_time'], $data['reason']);

        return back()->with('success', __('Overtime of :h h :m min sent for approval.', ['h' => intdiv($o->minutes, 60), 'm' => sprintf('%02d', $o->minutes % 60)]));
    }
}
