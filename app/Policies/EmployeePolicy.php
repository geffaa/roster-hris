<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;

/**
 * Company isolation is enforced earlier by the global scope (other companies resolve to 404).
 * This policy decides what a member of the same company may see or do.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EmployeesViewAll->value) || $user->can(Permission::EmployeesViewTeam->value);
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->can(Permission::EmployeesViewAll->value) || $this->isSelf($user, $employee)) {
            return true;
        }

        return $user->can(Permission::EmployeesViewTeam->value) && $this->manages($user, $employee);
    }

    /** Creating employees (including spreadsheet import). */
    public function create(User $user): bool
    {
        return $user->can(Permission::EmployeesManage->value);
    }

    /** Contracts and ID scans: HR, payroll, auditors and the employee themself; not managers. */
    public function viewDocuments(User $user, Employee $employee): bool
    {
        return $user->can(Permission::EmployeesViewAll->value) || $this->isSelf($user, $employee);
    }

    public function manageDocuments(User $user, Employee $employee): bool
    {
        return $user->can(Permission::EmployeesManage->value);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can(Permission::EmployeesManage->value);
    }

    public function revealIdentifiers(User $user, Employee $employee): bool
    {
        return $user->can(Permission::IdentifiersReveal->value);
    }

    public function viewCompensation(User $user, Employee $employee): bool
    {
        return $user->can(Permission::CompensationView->value) || $this->isSelf($user, $employee);
    }

    public function manageCompensation(User $user, Employee $employee): bool
    {
        return $user->can(Permission::CompensationManage->value);
    }

    public function viewActivity(User $user, Employee $employee): bool
    {
        return $user->can(Permission::AuditView->value) || $user->can(Permission::EmployeesManage->value);
    }

    private function isSelf(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }

    /** Managers see their direct reports. */
    private function manages(User $user, Employee $employee): bool
    {
        $mine = $user->employee?->id;

        return $mine !== null && $employee->manager_id === $mine;
    }
}
