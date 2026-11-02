---
version: 1
slug: "mockups-payroll-run-html"
primary_target: "mockups/payroll-run.html"
related_targets: ["mockups/rule-pack.html","mockups/clock-in.html"]
---

# Surface brief: Roster mockups (payroll run review, rule pack editor, clock-in PWA)

Scope: three Operate-mode screens as static HTML mockups (mockups/payroll-run.html desktop, mockups/rule-pack.html desktop, mockups/clock-in.html phone 390). Audience judging them: non-technical Upwork clients (HR managers, business owners) who must read "serious, clear, global HR system" at a glance. Must not feel artsy, intimidating/spreadsheet-dense, or local/small. UI language English; Indonesian pack data (IDR, PPh 21, BPJS) shown as one country pack. All data synthetic, rates labelled illustrative.

## Direction contract
THESIS: Every payroll fact is pinned to a date and read like the calendar on the office wall. Refuses the category default of a grey side-nav, metric cards, and a pill-badged table.
OWN-WORLD: Deep calendar green masthead (#0E5A4F) as the shell, holiday red (#D2232A) only for non-working days and flagged items, near-white paper ground (#FBFBF8), one stepped neutral scale. Condensed numeral face (Barlow Condensed) for dates and figures headlines, Public Sans for UI text; tabular figures everywhere; square cells, 1px rules, no radius except circled flags.
STORY: The HR manager sees which month this is, which days were paid, what changed versus last month, and why each number is what it is; then approves.
FIRST VIEWPORT: Green masthead with nav and company; the month sheet as page header (month name large left, 31-day strip with weekends/holidays, exact-length spans for joins/leave/rule versions, run id + two approval boxes right); below, employee table left with totals row and change vs last month, working column right with base, rate, cap, rule version per line; primary action "Mark reviewed" / approve in the working column.
FORM: Office Wall Calendar, grounded candidate 5 of 7. Raises: exact-length spans (Labanotation), margin working column (centre rail), sequential document numbers (Saville), one stepped tonal scale (darkroom), persistent ticked/circled review marks (doujin). seed:1e25df4fa5b236d5
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved
Dark theme tokens (needed for app, optional for mockups). Second country pack visuals.
