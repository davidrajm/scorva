# Story 5.9: Reviewer page UI polish — identity, cards, icons, student index

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want to see who I am logged in as, clearer assignment cards, icon-labelled actions, and numbered rows on the student marking list,
So that the marking workspace feels professional, scannable, and unambiguous on shared lab machines.

## Acceptance Criteria

1. **Logged-in identity in reviewer top bar**
   - **Given** a reviewer is logged into WordPress and opens `/reviews/mark/`
   - **When** the app shell renders (`AppShell` variant `reviewer`)
   - **Then** the fixed top bar shows **Project Reviews** wordmark on the left and a **user identity block** on the right with:
     - Primary line: display name (`display_name`, fallback `user_login` if empty)
     - Secondary line (muted, smaller): email address
   - **And** identity data comes from `window.prAppData.currentUser` localized at enqueue time (no extra REST round-trip on load)
   - **And** `Routes::enqueue_app_assets()` adds to `prAppData`:
     ```php
     'currentUser' => [
         'id' => (int) $user->ID,
         'displayName' => (string) $user->display_name,
         'email' => (string) $user->user_email,
     ],
     ```
     only when `is_user_logged_in()` (reviewer/coordinator routes already redirect guests)
   - **And** the block uses semantic markup (`aria-label` e.g. “Signed in as {displayName}”) and truncates long email with `truncate` + `title` tooltip
   - **And** top bar layout uses `justify-content: space-between` (extend `assets/css/app-shell.css` — e.g. `.pr-topbar-inner` flex row) without breaking coordinator shell
   - **Optional parity:** show the same identity block on coordinator `AppShell` (shared component); not required if time-boxed

2. **Assignment cards — structured academic styling**
   - **Given** the assignments home (`MarkAssignments.jsx`) with markable assignments
   - **When** cards render
   - **Then** each assignment uses a dedicated **`AssignmentCard`** (new, reviewer-local or `src/shared/components/`) modelled on `SessionCard` patterns:
     - Session title as eyebrow (`text-xs uppercase tracking-wide text-muted`)
     - Review round as prominent `h3` title
     - Panel name on its own line with subtle icon (panel/users glyph via shared icon helper)
     - Visual affordance that the card is clickable: hover shadow, focus ring, trailing chevron or “Open marking →” hint
     - Optional `StatusChip` when useful (e.g. “Ready to mark”) — do not duplicate blocked-state cards (those stay in the Notice list below)
   - **And** grid remains responsive (`md:grid-cols-2`, `gap-4`)
   - **And** blocked assignments section styling is unchanged except spacing harmony with new cards
   - **And** no regression to deep links (`#/mark/:sessionId/:reviewId/:panelId`)

3. **Icon-labelled buttons on reviewer flows**
   - **Given** shared `Button` is used across coordinator and reviewer apps
   - **When** reviewer UI renders primary/secondary actions
   - **Then** extend `Button` with optional `icon` prop (string key) and/or `iconPosition` (`start` | `end`, default `start`) that renders an inline SVG before/after label text with `gap-2`, `aria-hidden` on the icon, label remains visible text (not icon-only except where noted)
   - **And** add reviewer action icons to the existing icon map (extend `NavIcon.jsx` → rename export to `Icon` or add `ActionIcon.jsx` reusing the same SVG path registry) with keys at minimum:
     | Key | Used on |
     |-----|---------|
     | `arrow-left` | Back to assignments link styled as button/ghost |
     | `pencil` | Update score |
     | `lock` | Freeze scores |
     | `unlock` | Request unfreeze |
     | `save` | RubricForm Save |
     | `chevron-right` | Assignment card affordance |
   - **And** apply icons on:
     - `MarkingGrid.jsx`: back link (optional text+icon), **Freeze scores**, **Request unfreeze**, **Update score**
     - `RubricForm.jsx`: **Save** (and **Cancel**/close if present)
     - `MarkAssignments.jsx`: none required on empty state; card affordance via `AssignmentCard`
   - **And** confirm dialogs keep text labels on confirm buttons; icons allowed on confirm when they match action (`lock` on freeze confirm)
   - **And** disabled/loading states preserve icon + “Loading…” / “Saving…” text
   - **Coordinator regression:** existing `Button` usages without `icon` prop render unchanged

4. **Student list serial number (marking grid)**
   - **Given** the marking grid lists students in API order
   - **When** the grid header and rows render
   - **Then** the first data column is **#** (header `scope="col"`, visually “No.” acceptable) showing **1-based** index (`rowIndex + 1`) in display order
   - **And** the column is narrow (`minmax(2.5rem, auto)`), `tabular-nums`, muted text
   - **And** `gridTemplateColumns` in `useMemo` is updated to include the new column before **Student**
   - **And** serial numbers are **not** persisted IDs — they reflect sort order only; sorting changes would renumber (no API change)
   - **And** empty student list shows no phantom rows

5. **Tests and build**
   - **And** `RoutesTest::assertPrAppDataLocalized` (or new test) asserts `currentUser.displayName` and `email` present when user id is set in bootstrap
   - **And** no new REST endpoints required
   - **And** run `composer test` and `npm run build`

## Tasks / Subtasks

- [x] **Bootstrap user:** Add `currentUser` to `wp_localize_script` in `includes/routes.php`; document shape in `tests/RoutesTest.php`
- [x] **Shell:** Update `src/shared/AppShell.jsx` + `assets/css/app-shell.css` for topbar user block (reviewer; optional coordinator)
- [x] **Icons:** Extend icon registry + `Button` `icon` prop in `src/shared/components/Button.jsx`
- [x] **AssignmentCard:** New component; refactor `MarkAssignments.jsx` markable list
- [x] **Marking grid:** Add `#` column; wire icons on toolbar/row actions; style back link consistently
- [x] **RubricForm:** Save button icon
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

Reviewer workspace (`/reviews/mark/`) gaps:

1. No visible logged-in user (name, email)
2. Assignment cards look plain — need richer styling aligned with Direction 1
3. Page buttons lack icons
4. Student marking list needs serial numbers

### Architecture alignment

| Area | Current | Target |
|------|---------|--------|
| User context | `prAppData` = `restUrl`, `nonce`, CSV template URLs only | Add `currentUser` from WP user object |
| Top bar | Wordmark only (`AppShell.jsx` + `.pr-wordmark`) | Wordmark + signed-in identity (reviewer) |
| Assignment UI | Plain `Card` + `Link` text stack | `AssignmentCard` patterned on `SessionCard` |
| Buttons | Text-only `Button` | Optional leading/trailing SVG via shared icon map |
| Student grid | Columns: Student, Reg no, Attendance, Status, criteria…, Action | Leading **#** column (1-based) |

**Do not** add a `/me` REST endpoint unless `prAppData` is insufficient — localized bootstrap is the established pattern (`1-6-react-spas.md`).

**Do not** change mark persistence, freeze/unfreeze logic, or routing (stories 5.6–5.8).

### Critical files (touch list)

**PHP**

- `includes/routes.php` — `prAppData.currentUser`
- `tests/RoutesTest.php` — assert localized user fields

**CSS**

- `assets/css/app-shell.css` — topbar flex / user block spacing

**Frontend — shared**

- `src/shared/AppShell.jsx` — `UserIdentity` subcomponent or inline reviewer topbar slot
- `src/shared/components/Button.jsx` — `icon` prop
- `src/shared/components/NavIcon.jsx` — extend icon keys (or split `Icon.jsx`)

**Frontend — reviewer**

- `src/reviewer/pages/MarkAssignments.jsx` — `AssignmentCard`
- `src/reviewer/components/MarkingGrid.jsx` — `#` column + icons
- `src/reviewer/components/RubricForm.jsx` — Save icon
- Optional: `src/reviewer/components/AssignmentCard.jsx`

### UX references

- **UX-DR5:** Reviewer shell — top bar only, no sidebar; centered content on form (unchanged)
- **UX-DR94 / Direction 1:** Structured Academic — cards, chips, calm density
- **UX spec layout:** Top bar 56px (`--pr-layout-topbar-height`); reviewer list row height 48px — serial column supports scanning
- **SessionCard** (`src/shared/components/SessionCard.jsx`) — reuse flex header, chip, progress patterns where applicable (assignments have no progress bar unless you add optional completion later — **out of scope**)

### Icon implementation guardrails

- Reuse inline SVG stroke style from `NavIcon` (24×24 viewBox, `strokeWidth="1.75"`) for visual consistency
- Heroicons-style paths; no new npm icon package
- `Button` with icon: `inline-flex items-center gap-2`; icon `shrink-0 h-4 w-4` (slightly smaller than nav)

### Assignment card content mapping

From `GET /reviewer/assignments` item:

| Field | Card placement |
|-------|----------------|
| `session_title` | Eyebrow |
| `review_label` | Title |
| `panel_name` | Subtitle with panel icon |
| `markable` | Only render in markable grid (blocked stays in Notice list) |

### Marking grid column order (after change)

`#` | Student | Reg no | Attendance | Status | …criteria… | Action

Update sticky column: only **Student** stays sticky left (serial column scrolls with grid on mobile — acceptable).

### Previous story intelligence (5.6–5.8)

- Reviewer app uses **HashRouter**; assignment links `#/mark/...` — preserve
- **MarkingGrid** is the student list (funnel step collapsed into grid per 5.6); serial numbers apply here, not a separate `StudentList` page
- **Freeze / Request unfreeze** buttons live in `PageHeader` actions — add icons without changing handlers
- **Save-only** rubric flow (5.6) — icon on Save only

### Testing notes

- PHPUnit: extend localized script assertion with fake user in bootstrap (`pr_test_current_user_id` + user meta stubs if needed)
- Manual: log in as provisioned reviewer → see name/email → open assignment → grid shows 1…n → buttons show icons at 375px width
- Visual: run `npm run build`; verify coordinator dashboard buttons still look correct without icons

### References

- [Source: _bmad-output/planning/epics.md — Epic 5, UX-DR5, UX-DR23]
- [Source: _bmad-output/planning/ux-design-specification.md — Layout, reviewer funnel, Direction 1]
- [Source: _bmad-output/implementation/5-3-reviewer-assignments-ui.md]
- [Source: _bmad-output/implementation/5-6-reviewer-marking-grid-freeze.md]
- [Source: _bmad-output/implementation/1-6-react-spas.md — prAppData pattern]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Added `prAppData.currentUser` (id, displayName, email) from `wp_get_current_user()` when logged in; PHPUnit asserts shape in `RoutesTest`.
- `AppShell` shows signed-in identity block (display name + truncated email) for both reviewer and coordinator shells when `currentUser` is present.
- Extended `NavIcon` with `Icon` export and action keys (`arrow-left`, `pencil`, `lock`, `unlock`, `save`, `chevron-right`, `panel`); `Button` supports optional `icon` / `iconPosition`.
- New `AssignmentCard` with SessionCard-style hierarchy, panel icon, hover affordance, and “Ready to mark” chip; `MarkAssignments` refactored.
- `MarkingGrid`: 1-based **No.** column, icon-labelled freeze/unfreeze/update/back actions; freeze confirm uses lock icon.
- `RubricForm` Save button uses save icon; preserves “Saving…” label when busy.
- `./vendor/bin/phpunit` — 178 tests OK; `npm run build` — success.

### File List

- includes/routes.php
- tests/bootstrap.php
- tests/RoutesTest.php
- assets/css/app-shell.css
- src/shared/AppShell.jsx
- src/shared/components/NavIcon.jsx
- src/shared/components/Button.jsx
- src/shared/components/ConfirmDialog.jsx
- src/reviewer/components/AssignmentCard.jsx
- src/reviewer/pages/MarkAssignments.jsx
- src/reviewer/components/MarkingGrid.jsx
- src/reviewer/components/RubricForm.jsx
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css

## Change Log

- 2026-05-17: Reviewer UI polish — identity topbar, assignment cards, button icons, student row numbers (story 5.9).

## Story completion status

- Ultimate context engine analysis completed — comprehensive developer guide created
- **Covers:** FR27 (reviewer SPA UX); UX-DR5, UX-DR94; additive polish on Epic 5 reviewer surfaces (not in original epics split — tracked as 5.9)
- Implementation complete — status **review**
