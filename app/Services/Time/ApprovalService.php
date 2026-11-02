<?php

namespace App\Services\Time;

use App\Enums\Permission;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\AttendanceCorrection;
use App\Models\BankAccountChange;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Notify;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One engine for every request type. Step 1 goes to the requester's manager (or their manager's
 * manager when the manager is on leave today, or the HR queue when there is nobody); an optional
 * step 2 goes to HR.
 */
class ApprovalService
{
    public function __construct(private Notify $notify) {}

    /** $hrOnly sends step 1 straight to the HR queue (used for bank account changes). */
    public function submit(string $type, Model $subject, Employee $requester, bool $hrStep = false, bool $hrOnly = false): ApprovalRequest
    {
        return DB::transaction(function () use ($type, $subject, $requester, $hrStep, $hrOnly) {
            $request = ApprovalRequest::create([
                'company_id' => $requester->company_id,
                'type' => $type,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'requester_id' => $requester->id,
            ]);

            [$approver, $delegatedFrom] = $hrOnly ? [null, null] : $this->approverFor($requester);
            $first = $request->steps()->create(['step' => 1, 'approver_id' => $approver?->id, 'delegated_from_id' => $delegatedFrom?->id, 'status' => 'pending']);

            if ($hrStep && $approver !== null) {
                $request->steps()->create(['step' => 2, 'approver_id' => null, 'status' => 'waiting']);
            }
            $this->notify->approvalNeeded($first, $request);

            return $request;
        });
    }

    /**
     * @return array{0: ?Employee, 1: ?Employee} approver (null = HR queue), and who they stand in for
     */
    public function approverFor(Employee $requester): array
    {
        $manager = $requester->manager_id ? Employee::withoutGlobalScopes()->find($requester->manager_id) : null;

        if (! $manager || $manager->status === 'resigned') {
            return [null, null];
        }

        if ($this->isOnLeaveToday($manager)) {
            $skip = $manager->manager_id ? Employee::withoutGlobalScopes()->find($manager->manager_id) : null;

            return [$skip && ! $this->isOnLeaveToday($skip) ? $skip : null, $manager];
        }

        return [$manager, null];
    }

    public function canAct(User $user, ApprovalStep $step): bool
    {
        if ($step->status !== 'pending') {
            return false;
        }
        $mine = $user->employee()->value('id');
        $request = $step->request;

        if ($mine !== null && $mine === $request->requester_id) {
            return false; // nobody approves their own request, not even HR
        }

        return $step->approver_id === null
            ? $user->can(Permission::EmployeesManage->value)
            : $step->approver_id === $mine;
    }

    public function decide(ApprovalStep $step, User $user, bool $approve, ?string $comment = null): ApprovalRequest
    {
        if (! $this->canAct($user, $step)) {
            throw new RuntimeException('You cannot decide this request.');
        }

        return DB::transaction(function () use ($step, $user, $approve, $comment) {
            $step->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $user->id, 'decided_at' => now(), 'comment' => $comment]);
            $request = $step->request;

            $next = $approve ? $request->steps()->where('step', '>', $step->step)->where('status', 'waiting')->first() : null;
            if ($next) {
                $next->update(['status' => 'pending']);
                $request->update(['current_step' => $next->step]);
                $this->notify->settle($request);
                $this->notify->approvalNeeded($next, $request);

                return $request;
            }

            $request->update(['status' => $approve ? 'approved' : 'rejected', 'decided_at' => now()]);
            $request->steps()->where('status', 'waiting')->update(['status' => 'skipped']);
            $this->finalise($request, $approve);
            $this->notify->decided($request, $approve, $comment);

            return $request;
        });
    }

    public function cancel(ApprovalRequest $request): void
    {
        if ($request->status !== 'pending') {
            return;
        }
        $request->update(['status' => 'cancelled', 'decided_at' => now()]);
        if ($request->subject instanceof Claim) {
            $request->subject->update(['status' => 'cancelled']);
        }
        if ($request->subject instanceof BankAccountChange) {
            $request->subject->update(['status' => 'cancelled']);
        }
        $request->steps()->whereIn('status', ['pending', 'waiting'])->update(['status' => 'skipped']);
    }

    private function finalise(ApprovalRequest $request, bool $approved): void
    {
        $subject = $request->subject;

        match (true) {
            $subject instanceof LeaveRequest, $subject instanceof OvertimeRequest => $subject->update(['status' => $approved ? 'approved' : 'rejected']),
            $subject instanceof BankAccountChange => $this->applyBankChange($subject, $approved),
            $subject instanceof Claim => $subject->update(['status' => $approved ? 'approved' : 'rejected']),
            default => $this->applyCorrection($subject, $approved),
        };
    }

    /** Payroll uses the new account from the next calculation; the old number stays only in the encrypted audit trail. */
    private function applyBankChange(BankAccountChange $change, bool $approved): void
    {
        $change->update(['status' => $approved ? 'approved' : 'rejected']);
        if ($approved) {
            $change->employee()->withoutGlobalScopes()->firstOrFail()->update(['bank_name' => $change->bank_name, 'bank_account' => $change->account_number]);
        }
    }

    /** The record takes the corrected times; the original times stay on the correction row and in the audit log. */
    private function applyCorrection(AttendanceCorrection $correction, bool $approved): void
    {
        $correction->update(['status' => $approved ? 'approved' : 'rejected']);

        if ($approved) {
            $correction->record()->withoutGlobalScopes()->firstOrFail()->update([
                'clock_in_at' => $correction->new_in,
                'clock_out_at' => $correction->new_out,
                'status' => 'corrected',
                'late_min' => 0,
            ]);
        }
    }

    private function isOnLeaveToday(Employee $employee): bool
    {
        $today = now()->toDateString();

        return LeaveRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists() || $employee->status === 'on_leave';
    }
}
