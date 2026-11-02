# Case study: Roster, a multi-country HRIS with a rule-pack payroll engine

**Role:** solo full-stack developer (product, design system, backend, frontend, tests)
**Stack:** Laravel 12, PHP 8.5, PostgreSQL 17, Inertia + Vue 3 (TypeScript), Pest, Playwright, Groq (OpenAI-compatible API)
**Scope:** everything below is in the repository, with tests and synthetic demo data.

## The problem
Growing companies in Indonesia run HR on spreadsheets and a payroll vendor. Leave balances live in chat threads, attendance is a shared sheet, and nobody can say why a payslip changed. The hard part is payroll: Indonesian income tax (PPh 21) moved to monthly effective rates (TER) in 2024 with a year-end recalculation, BPJS contributions have wage caps that change every March, and every number has to be explainable to an employee and to an auditor.

## What I built
- **People records** with company isolation (records from another company resolve to 404), per-company roles, mandatory TOTP for anyone who sees salaries, encrypted identifiers revealed only with an audit entry, and spreadsheet import with row-by-row validation.
- **Attendance:** clock-in from the phone, checked against the office geofence (haversine) and always on the server clock. Night shifts belong to the day they started. Missing clock-outs are corrected through an approval, and the original times are kept.
- **Leave:** only working days are counted. Allowances are prorated with a service waiting period, requests split across years, half days are supported, and cancelling leave already in progress returns only the days not yet taken.
- **Approvals:** one engine for leave, overtime, corrections, bank account changes and expense claims. A request goes to the manager, or to the skip-level manager when the manager is on leave, or to an HR queue when there is no manager. Leave types can require a second approval by HR. Nobody approves their own request.
- **Payroll engine:** a pure calculator (integer money, no database, no clock) driven by dated rule-pack versions.
  - Indonesia: TER A/B/C tables from PP 58/2023, Article 17 year-end true-up (including refunds for people leaving mid-year), BPJS with wage caps, overtime at 1/173 with workday and rest-day multipliers, THR with proration, unpaid leave.
  - A generic progressive pack for companies outside Indonesia.
- **Payroll workflow:**
  - Calculate (queued, with progress), then review. Changes above 20% need a reviewer's tick, and negative net pay blocks approval.
  - Two different approvers, then lock, then send payslips (in the app and as a PDF), the bank transfer file and reports.
  - Locked items keep their own copy of every number, so a later rate change never rewrites a paid month.
- **Rule pack editor:** rate changes are new dated versions. Each changed number needs an updated source, a second person approves, and a version can never start inside a locked period.
- **Employee app:**
  - Installable to the home screen, in English and Bahasa Indonesia, with push notifications.
  - Covers clock-in, time off, attendance corrections, overtime, expense claims with receipt photos, payslips, and bank account changes that HR approves.
- **Ask HR:** an assistant that answers only from the company's policies and the asker's own HR data, with numbered sources. Answers are written by a Groq-hosted model (gpt-oss-120b) when an API key is set. Without a key, the closest policy passages are shown and labelled. Payroll officers also get a briefing on what changed in a run before they approve it.
- **Operations:**
  - Home page by role, attendance board, leave calendar.
  - Payroll and contribution reports that add up to the payslips, with Excel export.
  - Audit log, command palette search, and HR settings for work patterns, leave types and policies.

## Decisions worth noting
- **Calculator separate from the database.** It made 19 hand-worked golden cases possible: December true-up, refunds, mid-year leavers, the JP cap change in March, THR proration and overtime tiers.
- **Rule-pack versions start on the 1st of a month.** No payroll period ever mixes two rate tables, which removes a whole class of bugs.
- **Full-text search instead of embeddings** for policy retrieval. Policies are short and structured, Postgres handles both English and Indonesian with the `simple` configuration, and 20 questions with known sources all retrieve the right policy in the top three.
- **The assistant only ever receives the asker's own data**, built on the server. Leaking another employee's data is impossible by construction, and a test proves it.
- **The service worker never stores actions offline.** Clock-in must be verified against the server clock and the geofence, so offline means a clear "try again", not a silent queue.

## Quality
- 185 Pest tests (unit and feature) and 3 Playwright end-to-end flows on their own database.
- Larastan level 5 clean, Pint, strict TypeScript.
- Every screen was checked at 1440 px and 390 px.
- Every regulatory number in the rule pack carries its source and the date it was checked. The one figure confirmed only by news reports (the 2026 BPJS Kesehatan wage cap) is flagged "verify" in the app.

## Demo
- Video walkthrough: [`docs/demo.mp4`](demo.mp4) (about 90 seconds).
- Local setup and demo logins: [README](../README.md).
- All names, salaries and policies are synthetic.
