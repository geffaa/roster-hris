---
name: Roster
description: Multi-country HRIS with payroll driven by dated rule packs — calm, spacious workspace for HR teams and a phone app for employees.
colors:
  brand: "#0F6E66"
  brand-strong: "#0B5550"
  brand-soft: "#E5F2F0"
  brand-tint: "#F1F7F6"
  ground: "#F5F7F6"
  surface: "#FFFFFF"
  surface-2: "#FAFBFB"
  sidebar: "#FBFCFC"
  hover: "#EFF3F2"
  line: "#E2E8E6"
  line-strong: "#C9D3D0"
  text: "#13201E"
  text-2: "#33433F"
  text-3: "#56655F"
  text-4: "#5E6C65"
  success: "#12724F"
  success-soft: "#E4F3EC"
  warning: "#9A5A06"
  warning-soft: "#FCF3E1"
  danger: "#B42318"
  danger-soft: "#FCEBE9"
  info: "#1E5BB8"
  info-soft: "#E9F1FC"
  chart-late: "#E0A43B"
  chart-absent: "#D9594C"
  chart-leave: "#7FA7D9"
  note-success-border: "#BFE3D1"
  note-info-border: "#C8DBF6"
  note-warning-border: "#F2DCB0"
  note-danger-border: "#F5C7C2"
  on-brand-muted: "#CFE7E3"
  toast-icon: "#7DD3B8"
  avatar-1-bg: "#DFF1FB"
  avatar-1-fg: "#0B5A82"
  avatar-2-bg: "#FBE5F0"
  avatar-2-fg: "#97184E"
  avatar-3-bg: "#FCF0D4"
  avatar-3-fg: "#8A4B06"
  avatar-4-bg: "#DCF3E4"
  avatar-4-fg: "#17663A"
  avatar-5-bg: "#ECE7FC"
  avatar-5-fg: "#5321B0"
  avatar-6-bg: "#FDE4E6"
  avatar-6-fg: "#9C1238"
  avatar-7-bg: "#E1E8FC"
  avatar-7-fg: "#33389F"
typography:
  page-title:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "28px"
    fontWeight: 600
    lineHeight: 1.2
  card-title:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "17px"
    fontWeight: 600
    lineHeight: 1.3
  figure-large:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "30px"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.02em"
  body:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.55
  secondary:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  caption:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 600
    lineHeight: 1.4
  app-clock:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "52px"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "-0.03em"
  micro:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "10px"
    fontWeight: 700
    lineHeight: 1.2
  pill:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 600
    lineHeight: 1.2
  count:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 700
    lineHeight: 1.2
  emphasis:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 600
    lineHeight: 1.2
  app-action:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "18px"
    fontWeight: 700
    lineHeight: 1.2
  logo:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "19px"
    fontWeight: 700
    lineHeight: 1.2
  total:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "22px"
    fontWeight: 700
    lineHeight: 1.2
  app-title:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "24px"
    fontWeight: 700
    lineHeight: 1.2
  net:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "24px"
    fontWeight: 700
    lineHeight: 1.2
  profile-name:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "26px"
    fontWeight: 600
    lineHeight: 1.2
  index-title:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "32px"
    fontWeight: 600
    lineHeight: 1.2
  app-amount:
    fontFamily: "Source Sans 3, system-ui, sans-serif"
    fontSize: "40px"
    fontWeight: 700
    lineHeight: 1.2
rounded:
  xs: "4px"
  sm: "6px"
  md: "8px"
  tile: "10px"
  lg: "12px"
  xl: "16px"
  full: "999px"
spacing:
  s1: "4px"
  s2: "8px"
  s3: "12px"
  s4: "16px"
  s5: "20px"
  s6: "24px"
  s7: "32px"
  s8: "40px"
  s9: "48px"
components:
  button-primary:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    height: "40px"
    padding: "0 16px"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.md}"
    height: "40px"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "24px"
  nav-item-active:
    backgroundColor: "{colors.brand-soft}"
    textColor: "{colors.brand-strong}"
    rounded: "{rounded.md}"
  field-trigger:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.md}"
    height: "40px"
---

# Design System: Roster

## Overview

**Creative North Star: "A calm HR office"**

Roster is used by HR admins and payroll officers who are not technical, by managers approving requests between other work, and by employees on phones. The workspace is calm and spacious: a light grouped sidebar, centred content up to 1240px, white bordered cards with 24px padding and no resting shadows, generous line height, and one teal accent for actions and the current location. Every computed number shows its working beneath it. The employee app is a separate phone layout with a bottom tab bar, built like a banking app: the one number that matters first, then the breakdown.

Navigation follows the end-state information architecture in docs/IA.md: 18+ modules in 8 groups across phases, role-based visibility, so a grouped accordion sidebar (collapsible to a 72px icon rail on wide pages such as payroll review) rather than top tabs. Modules from later phases appear in the sidebar with a "Soon" pill.

**Key Characteristics:**
- Source Sans 3 at 15px; tabular figures on numbers.
- Light sidebar (#FBFCFC), accordion groups, current group expanded, counts as teal pills.
- Bordered cards, no resting shadows; soft lift only on hover and popovers.
- "Resolve before you approve" lists and disabled primary actions until issues are cleared.
- Custom form controls only (no native date pickers, selects, checkboxes or radios).

## Colors
Teal on cool neutrals; status colours carry meaning only.
- **Brand** #0F6E66 primary buttons, current nav item, selected rows, links; **brand-strong** #0B5550; **brand-soft** #E5F2F0 active backgrounds; **brand-tint** #F1F7F6 selected row and net-pay cell.
- **Neutrals:** ground #F5F7F6, surface #FFFFFF, surface-2 #FAFBFB, sidebar #FBFCFC, hover #EFF3F2, line #E2E8E6, line-strong #C9D3D0, text #13201E, text-2 #33433F, text-3 #56655F, text-4 #5E6C65 (all AA on ground and surface).
- **Status:** success #12724F/#E4F3EC, warning #9A5A06/#FCF3E1, danger #B42318/#FCEBE9, info #1E5BB8/#E9F1FC.
- **Chart series (attendance):** on time = brand, late #E0A43B, not clocked in #D9594C, on leave #7FA7D9, day off = line-strong.

## Typography
**Font:** Source Sans 3 (humanist, highly legible, tabular figures) for everything.
- Page title 28/600 · card title 17/600 · large figure 30/700 · body 15/400 · secondary 14 · caption 13/600.
- Supporting sizes: 10 (rail counts), 11 (Soon pills), 12 (counts, weekday labels), 16 (emphasis), 19 (logo), 22 (payroll totals), 24 (net pay), 26 (profile name).
- Phone app: title 24, main action 18, payslip amount 40, clock 52.

## Layout
Desktop: 264px sidebar (72px rail on wide pages) + main; 64px sticky top bar with search (⌘K), notifications, help. Content max 1240px with 32px gutters; two-column grids 2:1; 24px gaps; 32px between page header and content. Below 1100px the sidebar becomes a menu button and grids stack; at 390px gutters are 16px. Phone app: single column max 440px, 20px gutters, bottom tab bar.

## Elevation & Depth
Flat and bordered. Shadows only for popovers, menus, toasts and hovered action tiles (`0 12px 32px -8px rgba(19,32,30,.18)`).

## Shapes
Radius 6 (sub-nav, segmented), 8 (buttons, inputs, nav items), 12 (cards), 16 (phone cards), full (badges, avatars, switches).

## Components
- **Sidebar:** company switcher at top; Home and Inbox; accordion groups with chevrons; sub-items indented on a hairline; counts as teal pills; Settings, collapse and user at the bottom.
- **Module tabs** under the page header (e.g. Payroll runs · Claims · Rule packs · Loans Soon).
- **Buttons:** primary teal, secondary white with line-strong border, ghost; 40px (34px small).
- **Cards:** header (title, meta, actions), body 24px; dividers between list rows.
- **Tables:** grey header row, 14px cell padding, avatar + name + sub-line, selected row with 3px teal inset edge, right-aligned numbers, bold footer totals.
- **Key-value with working:** label left, value right, "why" line beneath.
- **Notes/callouts:** warning, danger, info, success with matching border.
- **Custom controls:** field trigger (date/select) with calendar popover or listbox, checkbox (incl. mixed), radio, switch, drop zone, toast. See mockups/components.html.
- **Phone app:** clock card with location status and 58px primary action, quick action tiles, week list, payslip hero card in brand colour.

## Do's and Don'ts
### Do:
- **Do** show what blocks an action and disable the action until it is resolved.
- **Do** show the working (base, rate, cap, rule version) under every computed number.
- **Do** mask personal IDs by default and say that revealing is logged.
- **Do** keep later-phase modules visible as "Soon" so the product map is clear.
### Don't:
- **Don't** use native browser or OS form controls.
- **Don't** put shadows on resting cards or use decorative gradients.
- **Don't** guess people's pronouns in copy; use names.
- **Don't** add top-level navigation items beyond the IA groups.
