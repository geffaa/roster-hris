# Roster — multi-country HRIS

Laravel 12 · PostgreSQL · Inertia + Vue 3 · Pest. Product, design and plan: [BREAKDOWN.md](BREAKDOWN.md), [docs/](docs/), [DESIGN.md](DESIGN.md).

## Milestone 1 (built)
- **Multi-company isolation**: every company-owned model uses `BelongsToCompany` (global scope + auto-fill). The active company is resolved per request by `SetCurrentCompany` after verifying membership. Records of another company resolve to **404**.
- **Roles per company**: spatie/laravel-permission with teams keyed by `company_id`. Roles: Super Admin, HR Admin, Payroll Officer, Manager, Employee, Auditor (`app/Enums/Role.php`). Access decisions in `EmployeePolicy`.
- **Two-factor (TOTP)** is mandatory for roles that can see salaries or identifiers, with 8 single-use recovery codes and one challenge per session.
- **Personal identifiers** (NIK, NPWP, BPJS, NI) are encrypted at rest; only the last four digits are stored in clear. Revealing a full number is permission-checked, rate-limited and written to the audit log.
- **Audit log** (owen-it/laravel-auditing, with `company_id`) for employees, identifiers and salary history; personal data and identifier values never enter the log.
- **Effective-dated salary history**: changes append rows; the current row is the latest on or before today, future rows show as "Scheduled".
- **Org chart**: reporting lines from each profile's manager, collapsible, department highlight; managers see only their subtree.
- **Spreadsheet import** (CSV/XLSX, up to 1,000 rows): every row is validated against the company first (duplicates, dates, managers, locations, salaries) and the import is blocked until all rows pass; managers listed later in the file are linked after insert.
- **Documents**: PDF/JPG/PNG on the private disk, optional expiry, served only through a permission check, audited.
- **Screens**: directory, org chart, import preview, employee profile (overview, job & pay, documents, activity), 2FA and password pages, all on custom components (no native selects or date pickers).

## Milestone 2 (built): attendance, leave, approvals
- **Clock-in/out** (phone-first `/me/today`): the server clock in the company time zone, never the device's. A haversine geofence checks the office radius, with remote work allowed only when the location says so. Clock-in opens 2 hours before the shift and closes when it ends. Late minutes are counted after the grace period.
- **Work patterns**, including night shifts that cross midnight: a 22:00–06:00 shift belongs to the date it started. Public holidays are kept per country.
- **Missing clock-outs**: `attendance:close-incomplete` runs hourly and flags them. The employee fixes one by sending a correction; the original times are kept.
- **Leave**:
  - Counts working days only, using the person's pattern and the holidays.
  - Splits a request across years.
  - Supports upfront or monthly accrual, prorated for joiners, with a service waiting period.
  - Allows half days and stops overlapping requests.
  - Event-based types (marriage, maternity) are granted per event.
  - Cancelling leave already in progress gives back only the days not yet taken.
- **Approval engine** (leave, overtime, corrections):
  - Goes to the direct manager.
  - If the manager is on leave that day, goes to their manager.
  - With no manager, goes to the HR queue.
  - Some leave types need a second HR approval.
  - Nobody approves their own request.
  - Rejecting requires a comment.
- **Screens**:
  - Employee app: Today, Time off, My attendance.
  - Inbox, showing balance-after and who else is off.
  - Daily attendance board.
  - Monthly leave calendar.
  - **Reports**: monthly attendance summary and yearly leave balances, with CSV export protected against formula injection.
- Managers see their direct reports; HR sees the whole company.

## Milestone 3 (built): payroll engine, rule packs, payslips
- **Pure calculator** (`app/Payroll`): integer money, no database or clock. Golden tests worked out by hand: 14 Indonesia, 5 Generic.
  - **Earnings:** prorated base (calendar or working days, split when the salary changes mid-month), fixed and non-fixed allowances, unpaid leave, overtime at 1/173 with workday and rest-day multipliers, THR (pro rata under 12 months), one-off pay and deductions.
  - **Contributions:** BPJS on base + fixed allowances, with wage caps (JP, Kesehatan).
  - **Indonesia tax:** PPh 21 by monthly TER (PP 58/2023) January–November. December, or the month someone leaves, recalculates the year with Article 17 and withholds the difference; a negative difference is refunded.
  - **Generic pack:** illustrative progressive bands for companies without a country pack.
- **Rule packs:** dated versions, never deleted; the version used is the latest approved one in force at the period end.
  - Drafts change numbers only. The method and TER tables stay fixed.
  - Every changed number needs an updated source, and someone other than the author approves.
  - A version starts on the 1st of a month and never inside a locked period.
- **Payroll runs:** draft → calculate (queued, with progress) → review → two different approvers → lock → send payslips.
  - Net pay moving more than 20% needs a reviewer's tick; negative net pay blocks approval.
  - Locked numbers are copies, so later salary or rate changes never alter them.
  - Months run in order, one open run at a time.
- **Outputs:** payslips in the employee app plus a PDF. A bank transfer CSV for locked runs only, with the export written to the audit log. A summary CSV with every contribution.
- **Sources:** each number in `database/data/rule-packs/*.php` carries its source and check date. The BPJS Kesehatan cap for 2026 is marked "verify".

## Run locally
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# set DB_* in .env (PostgreSQL), then:
php artisan migrate --seed
npm run build && php artisan serve
```
Demo logins (password `password`): `hr@roster.test` and `payroll@roster.test` (both approve payroll; asks for 2FA setup), `manager@roster.test`, `employee@roster.test`, `auditor@roster.test`, `payroll@roster.test`, `super@roster.test` (two companies), `uk.hr@roster.test`.

## Checks
```bash
./vendor/bin/pest          # feature tests on PostgreSQL (roster_test)
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
npx vue-tsc --noEmit
```

All names, IDs and salaries in the seed are synthetic.
