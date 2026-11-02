# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack
Laravel + PostgreSQL + Inertia (Vue 3); PWA for the employee surfaces. Design mockups are static HTML. Decided with the user (Laravel is their main skill); details in docs/TECH-SPEC.md.

## Users
- HR Admin and Payroll Officer at a 50–500 person company, on desktop, daily and intensely at month end: maintain employee records, run, review, and lock payroll.
- Managers, mostly on phones: approve leave and attendance corrections.
- Employees, on phones several times a day: clock in/out at the work location, request leave, download payslips.
- Auditors and a Super Admin: read change history; maintain companies and country rule packs.
- Buyer of the portfolio: Upwork clients commissioning a custom HRMS or an internal HRIS.

## Product Purpose
A multi-company, multi-country HRIS whose payroll is driven by versioned country rule packs (tax, social contributions, mandatory allowances, leave, overtime) stored as data with effective dates. Success: correct, traceable payroll per country; adding a country means adding data, not rewriting code. It is a portfolio product, not a commercial SaaS.

## Positioning
Every payroll number can be traced to the rule and rule-pack version that produced it, and rules change through an approved, dated edit instead of a deploy. Indonesia ships as a complete pack (PPh 21 TER with December true-up, BPJS, THR); a Generic pack proves the engine is country-neutral.

## Operating Context
Monthly payroll cycle: draft, calculate, review differences against last month, two-person approval, lock, publish payslips, export bank file and contribution lists. Daily attendance by geofenced clock-in from phones. Leave requests with approval chains and country holiday calendars. Rate changes happen a few times a year and carry high consequence.

## Capabilities and Constraints
- MVP: multi-company + RBAC + audit log + admin 2FA; employee records with effective-dated pay; attendance with geofence and corrections; leave with accrual; payroll engine + rule pack editor; employee self-service PWA; reports.
- No offline clock-in; server time is authoritative.
- Does not pay salaries, file taxes, or give tax advice.
- Regulatory numbers are unverified starting points (assumption) and must be verified against official sources before any demo.
- Second real country pack undecided; UK is the candidate (assumption).

## Evidence on Hand
None real. All demo companies, people, and figures are synthetic (e.g. "PT Sinar Laut Logistik") and must be labelled as such. No customers, testimonials, or benchmarks exist; do not fabricate them.

## Product Principles
1. Every number explains itself: show the base, rate, cap, and rule version behind it.
2. Rules are data with dates; history is never overwritten.
3. Two people stand behind every irreversible step (locking payroll, activating a rule version).
4. The employee path is one tap on a phone; the admin path is dense and exact.
5. Sensitive personal data is masked by default and every full view is logged.

## Accessibility & Inclusion
WCAG 2.2 AA. Employee PWA usable one-handed at 390px wide. UI and payslips in Indonesian and English.
