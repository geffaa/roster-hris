<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\RosterNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Who hears about what. Kept in one place so notification rules are easy to read and test. */
class Notify
{
    /** A step became pending: tell its approver, or everyone in the HR queue. */
    public function approvalNeeded(ApprovalStep $step, ApprovalRequest $request): void
    {
        $requester = Employee::withoutGlobalScopes()->findOrFail($request->requester_id);
        $users = $step->approver_id
            ? User::whereHas('employee', fn ($q) => $q->withoutGlobalScopes()->whereKey($step->approver_id))->get()
            : $this->withPermission($request->company_id, Permission::EmployeesManage)->reject(fn (User $u) => $u->id === $requester->user_id);

        Notification::send($users, new RosterNotification(
            'approval_needed',
            "{$requester->full_name}: ".$this->label($request->type).' to review',
            ($step->step > 1 ? 'Approved by the manager; ' : '').'waiting for your decision in Roster.',
            route('inbox', absolute: false),
            $request->company_id,
            extra: ['step_id' => $step->id],
        ));
    }

    public function decided(ApprovalRequest $request, bool $approved, ?string $comment): void
    {
        $this->settle($request);
        $user = User::whereHas('employee', fn ($q) => $q->withoutGlobalScopes()->whereKey($request->requester_id))->first();
        if (! $user) {
            return;
        }
        $label = $this->label($request->type);
        $user->notify(new RosterNotification(
            $approved ? 'request_approved' : 'request_rejected',
            ucfirst($label).($approved ? ' approved' : ' not approved'),
            $approved ? "Your {$label} was approved." : "Your {$label} was not approved: ".($comment ?: 'no reason given').'.',
            match ($request->type) {
                'leave' => route('me.time-off', absolute: false),
                'bank_change' => route('me.profile', absolute: false),
                'claim' => route('me.claims', absolute: false),
                default => route('me.attendance', absolute: false),
            },
            $request->company_id,
        ));
    }

    /** @param Collection<int, Payslip> $payslips */
    public function payslipsPublished(Collection $payslips, string $month): void
    {
        $byEmployee = $payslips->keyBy('employee_id');
        $users = User::with(['employee' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereHas('employee', fn ($q) => $q->withoutGlobalScopes()->whereIn('id', $byEmployee->keys()))->get();
        foreach ($users as $user) {
            $slip = $byEmployee[$user->employee->id];
            $user->notify(new RosterNotification('payslip', "Your {$month} payslip is ready", 'Open it in Roster to see the breakdown or download the PDF.', route('me.payslip', $slip, false), $slip->company_id));
        }
    }

    /** Once a step is decided, its "to review" notifications are no longer news for anyone in the queue. */
    public function settle(ApprovalRequest $request): void
    {
        $steps = $request->steps()->where('status', '!=', 'pending')->pluck('id');
        DatabaseNotification::whereIn('data->step_id', $steps->all())->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** @return Collection<int, User> */
    private function withPermission(int $companyId, Permission $permission): Collection
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($companyId);
        $users = User::whereHas('companies', fn ($q) => $q->whereKey($companyId))->get()
            ->each(fn (User $u) => $u->unsetRelation('roles')->unsetRelation('permissions'))
            ->filter(fn (User $u) => $u->hasPermissionTo($permission->value))->values();
        setPermissionsTeamId($previous);

        return $users;
    }

    private function label(string $type): string
    {
        return match ($type) {
            'leave' => 'leave request',
            'overtime' => 'overtime request',
            'attendance_correction' => 'attendance correction',
            'bank_change' => 'bank account change',
            'claim' => 'expense claim',
            default => str_replace('_', ' ', $type),
        };
    }
}
