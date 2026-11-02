<?php

namespace App\Enums;

enum Permission: string
{
    case EmployeesViewAll = 'employees.view_all';
    case EmployeesViewTeam = 'employees.view_team';
    case EmployeesManage = 'employees.manage';
    case IdentifiersReveal = 'identifiers.reveal';
    case CompensationView = 'compensation.view';
    case CompensationManage = 'compensation.manage';
    case AuditView = 'audit.view';
    case CompanyManage = 'company.manage';
    case PayrollView = 'payroll.view';
    case PayrollRun = 'payroll.run';
    case PayrollApprove = 'payroll.approve';
    case RulePacksManage = 'rule_packs.manage';
    case RulePacksApprove = 'rule_packs.approve';
}
