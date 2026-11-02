<?php

namespace App\Enums;

/**
 * Roles are global definitions; they are assigned per company (spatie "teams" keyed by company_id),
 * so one person can be HR Admin in one company and Auditor in another.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case HrAdmin = 'hr_admin';
    case PayrollOfficer = 'payroll_officer';
    case Manager = 'manager';
    case Employee = 'employee';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::HrAdmin => 'HR Admin',
            self::PayrollOfficer => 'Payroll Officer',
            self::Manager => 'Manager',
            self::Employee => 'Employee',
            self::Auditor => 'Auditor',
        };
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::HrAdmin => [
                Permission::EmployeesViewAll, Permission::EmployeesManage, Permission::IdentifiersReveal,
                Permission::CompensationView, Permission::CompensationManage,
                Permission::PayrollView, Permission::PayrollApprove, Permission::RulePacksManage, Permission::RulePacksApprove,
            ],
            self::PayrollOfficer => [
                Permission::EmployeesViewAll, Permission::IdentifiersReveal, Permission::CompensationView,
                Permission::PayrollView, Permission::PayrollRun, Permission::PayrollApprove,
            ],
            self::Manager => [Permission::EmployeesViewTeam],
            self::Employee => [],
            self::Auditor => [Permission::EmployeesViewAll, Permission::CompensationView, Permission::AuditView, Permission::PayrollView],
        };
    }

    /** Roles that can see salaries or personal identifiers must use TOTP. */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [self::SuperAdmin, self::HrAdmin, self::PayrollOfficer], true);
    }
}
