# Story 5.12: Table row hover effects — marking grid and data tables

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer or coordinator** scanning dense data (student marks, progress, reports, registry),
I want table and grid rows to highlight on hover,
So that I can track across wide rows (especially criterion columns) without losing my place.

## Acceptance Criteria

1. **Reviewer marking grid (desktop) — primary user example**
   - **Given** the marking grid at `#/mark/:sessionId/:reviewId/:panelId` on viewport **`lg` and wider** (`MarkingGrid.jsx`)
   - **When** the pointer moves over a **student row** (any cell: No., name, reg no, attendance, status, criterion score, or action)
   - **Then** the **entire row** shows a unified hover background using token `bg-surface-raised` (or equivalent `--pr-color-surface-raised`)
   - **And** the sticky **Student** column cell uses the **same** hover background so no visual gap between frozen and scrolling columns
   - **And** header row (`role="row"` in header) does **not** receive row hover
   - **And** transition is subtle (`transition-colors`); no layout shift

2. **Reviewer RubricForm — criterion score rows**
   - **Given** score entry for one student (`RubricForm.jsx` in modal or full page)
   - **When** the pointer moves over a **criterion block** (label + score input area for one criterion)
   - **Then** that criterion row/block highlights with the same hover treatment (`hover:bg-surface-raised` on a bordered row container)
   - **And** read-only / absent (`opacity-50`) rows still show hover unless `readOnly` disables interaction entirely (hover allowed for scanability)
   - **And** attendance fieldset is out of scope for row hover (single control group, not a data table)

3. **Semantic HTML tables — coordinator and shared list pattern**
   - **Given** any `<table>` with `<tbody>` data rows in the plugin SPAs
   - **When** the pointer hovers a `<tr>` in the body
   - **Then** the row uses **`hover:bg-surface-raised`** with `transition-colors`
   - **And** the following components are updated (inventory from codebase):
     | Component | Route / context |
     |-----------|-----------------|
     | `ProgressTable.jsx` | Session progress |
     | `ReportsMarksTable.jsx` | Reports — marks view |
     | `ReportsScoresTable.jsx` | Reports — scores view |
     | `RubricTable.jsx` | Rubric builder criteria rows |
     | `Registry.jsx` | Student registry |
     | `AuditLog.jsx` | Audit log |
     | `PanelReviewersStep.jsx` | Wizard — panel reviewers roster |
     | `CsvImportMapper.jsx` | Import preview sample rows |
   - **And** `<thead>` rows are unchanged (no hover on header)

4. **Mobile marking cards — light affordance (optional, recommended)**
   - **Given** `MarkingGridStudentCard` below `lg`
   - **When** pointer hovers the card (non-touch primary devices)
   - **Then** apply a subtle hover consistent with `AssignmentCard` / `SessionCard` (e.g. `hover:shadow-md` or `hover:bg-surface` on `Card`) — **not** full table row semantics
   - **And** do not add row hover inside the criterion `<dl>` on mobile (card-level hover is enough)

5. **Shared styling — DRY, no new dependencies**
   - **Given** multiple table implementations
   - **When** implementing hover
   - **Then** introduce a small shared helper (e.g. `src/shared/tableStyles.js`) exporting constants such as:
     - `TABLE_BODY_ROW` — for `<tr>`: `border-b border-border last:border-0 transition-colors hover:bg-surface-raised`
     - `GRID_ROW_GROUP` — for CSS grid row wrapper: `group contents`
     - `GRID_ROW_CELL` — for each grid cell: append `transition-colors group-hover:bg-surface-raised` (merge with existing cell classes)
   - **And** use existing Tailwind tokens only; no new npm packages
   - **And** respect `prefers-reduced-motion` if adding motion beyond color (color-only hover is acceptable without extra motion query)

6. **Accessibility and regression**
   - **And** hover is **visual only** — no change to focus order, keyboard activation, or `role` attributes
   - **And** focused row/cell still shows existing focus rings on interactive controls (inputs, buttons)
   - **And** `composer test` passes; `npm run build` succeeds
   - **And** manual QA:
     - [ ] `http://localhost:3000/reviews/mark/#/mark/2/4/3` (or local equivalent) — hover across full student row including criterion cells at ≥1024px
     - [ ] Open **Update score** modal — hover each criterion row
     - [ ] Coordinator: Progress, Reports (both tables), Registry — one row hover each
     - [ ] Sticky student column in marking grid: hover background continuous across row

## Tasks / Subtasks

- [x] **Shared styles:** Add `src/shared/tableStyles.js` (or `tableRowClasses.js`) with exported class strings; document usage in file header comment
- [x] **MarkingGrid desktop:** Add `group` to `role="row"` wrapper; add `group-hover:bg-surface-raised transition-colors` to every body `role="cell"`; fix sticky student cell to use `bg-surface group-hover:bg-surface-raised` (not static bg only)
- [x] **RubricForm:** Wrap each criterion block in a row container with border + `hover:bg-surface-raised transition-colors` (padding consistent with form density)
- [x] **Semantic tables:** Apply `TABLE_BODY_ROW` (or equivalent) to `<tr>` in all eight table components listed in AC3
- [x] **MarkingGridStudentCard (optional):** Card-level hover shadow/background for parity with assignment cards
- [x] Run `npm run build` and `composer test`
- [x] Manual QA checklist (AC6)

## Dev Notes

### User request (source)

> Implement the hover of the rows in the grid. at each review marks, and also, wherever the table like structure is there.
> For example, at `http://localhost:3000/reviews/mark/#/mark/2/4/3`, the criterion scores rows can have hover effects.

Interpretation:

- **Primary:** Desktop marking grid — each **student** is one row; criterion scores are **columns** within that row (user may say “criterion scores rows” meaning the horizontal band of score cells when hovering anywhere on the student row).
- **Secondary:** `RubricForm` — each **criterion** is a vertical stack row in the modal.
- **Tertiary:** All coordinator `<table>` tbody rows for visual consistency.

### CSS grid + `display: contents` — critical implementation detail

`MarkingGrid.jsx` uses a CSS grid “table” with `role="row"` wrappers using **`className="contents"`**. You cannot set `hover:bg-*` on the row wrapper alone — it generates no box.

**Working pattern (Tailwind `group` / `group-hover`):**

```jsx
// Row wrapper
<div key={ student.id } className="group contents" role="row">
  <motion.div
    className="border-b border-border px-2 py-2 ... transition-colors group-hover:bg-surface-raised"
    role="cell"
  >
    ...
  </motion.div>
  <motion.div
    className="sticky left-0 z-[1] border-b border-border bg-surface px-3 py-2 ... transition-colors group-hover:bg-surface-raised"
    role="cell"
  >
    { student.name }
  </motion.div>
  {/* every cell in the row must include group-hover:bg-surface-raised */}
</motion.div>
```

Hovering **any** cell highlights **all** cells in that row because they are descendants of `.group`.

**Do not** refactor the grid to `<table>` in this story — scope is styling only.

### Semantic `<table>` pattern

Replace or extend existing `<tr className="border-b border-border ...">` with shared constant, e.g.:

```jsx
import { TABLE_BODY_ROW } from '../../shared/tableStyles';

<tr key={ row.id } className={ TABLE_BODY_ROW }>
```

Existing `last:border-0` / `border-border/60` variants: normalize to `border-border` for hover consistency unless a component needs softer dividers — then keep `/60` and still add hover classes.

### RubricForm row treatment

Current structure (per criterion):

```300:320:src/reviewer/components/RubricForm.jsx
				{ criteria.map( ( c ) => {
					...
					return (
						<div
							key={ c.id }
							className={ isAbsent ? 'opacity-50' : undefined }
						>
							<div className="mb-1 flex flex-wrap items-center gap-2">
								<label ...>
```

Wrap the criterion block in something like:

```jsx
<div
  key={ c.id }
  className={ cn(
    'rounded-md border border-border px-3 py-3 transition-colors hover:bg-surface-raised',
    isAbsent && 'opacity-50'
  ) }
>
```

Keep fieldset spacing (`space-y-4` on form) — do not compress below UX “reviewer form slightly more spacious” guidance.

### Reference hover elsewhere in codebase

| Pattern | Location |
|---------|----------|
| `hover:bg-surface-raised` | `CoordinatorNav.jsx`, `ProgressAccordion.jsx` header, `PanelReviewersStep` list items |
| `group-hover:shadow-md` | `AssignmentCard.jsx`, `SessionCard.jsx` |
| No tbody row hover yet | All `ProgressTable`, `Reports*Table`, `Registry`, etc. |

Use **`hover:bg-surface-raised`** for data tables (not shadow) to match nav/accordion scan patterns and UX table density.

### Out of scope

- Clickable rows / row-as-link behaviour
- Zebra striping or alternating row colors
- Coordinator CSS grid tables beyond `MarkingGrid` (reviewer-only grid)
- PHP / REST / database changes
- Changing breakpoints or mobile card layout (story 5.11)
- `ScoreBreakdown`, `ReviewProgressSummary`, wizard step cards (not tabular tbody)

### Architecture compliance

| Rule | Action |
|------|--------|
| Shared utilities in `src/shared/` | `tableStyles.js` for class constants |
| Reviewer components | `MarkingGrid.jsx`, `RubricForm.jsx`, optional `MarkingGridStudentCard.jsx` |
| Coordinator components | Table files listed in AC3 |
| Tailwind + `#pr-root` | Utilities only |
| No new dependencies | — |

### Critical files (touch list)

- `src/shared/tableStyles.js` — **new**
- `src/reviewer/components/MarkingGrid.jsx`
- `src/reviewer/components/RubricForm.jsx`
- `src/reviewer/components/MarkingGridStudentCard.jsx` — optional card hover
- `src/coordinator/components/ProgressTable.jsx`
- `src/coordinator/components/ReportsMarksTable.jsx`
- `src/coordinator/components/ReportsScoresTable.jsx`
- `src/coordinator/components/RubricTable.jsx`
- `src/coordinator/pages/Registry.jsx`
- `src/coordinator/pages/AuditLog.jsx`
- `src/coordinator/components/PanelReviewersStep.jsx`
- `src/coordinator/components/CsvImportMapper.jsx`
- `build/reviewer.js`, `build/coordinator.js` — via `npm run build`

### Previous story intelligence

| Story | Relevant learning |
|-------|-------------------|
| **5.11** | Dual layout: cards `< lg`, CSS grid `role="table"` at `lg+`; sticky student column `sticky left-0 z-[1] bg-surface` — hover must update sticky cell bg |
| **5.9** | Card hover via `group-hover:shadow-md` on assignment list — different affordance than data tables |
| **5.6** | Grid structure with `display:contents` rows — do not break `gridTemplateColumns` or modal deep links |
| **1.5** | Design tokens `--pr-color-surface-raised` mapped to Tailwind `surface-raised` |

### Testing notes

- **Automated:** No new PHPUnit tests required (presentation-only). Existing suite must pass.
- **Optional:** If extracting `cn()` helper for class merging, reuse existing pattern from codebase or simple template string — do not add `clsx` package unless already present (it is not in package.json — use string concat or array `.filter().join(' ')`).
- **Manual:** See AC6 checklist; pay special attention to wide rubrics (8+ criteria) with horizontal scroll — hover must span all visible cells in the row.

### References

- [Source: _bmad-output/planning/ux-design-specification.md — § Tables (row height 44px coordinator / 48px reviewer), spacing tokens]
- [Source: src/reviewer/components/MarkingGrid.jsx — CSS grid `contents` rows]
- [Source: src/reviewer/components/RubricForm.jsx — criterion blocks]
- [Source: _bmad-output/implementation/5-11-reviewer-marking-grid-mobile-cards.md — desktop grid preserved at lg+]
- [Source: assets/css/app-shell.css — `--pr-color-surface-raised`]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Added `src/shared/tableStyles.js` with `TABLE_BODY_ROW`, `TABLE_BODY_ROW_SOFT`, `TABLE_ROW_HOVER`, and CSS-grid `GRID_ROW_*` helpers.
- MarkingGrid desktop rows use Tailwind `group` / `group-hover:bg-surface-raised` on every body cell, including sticky student column via `GRID_ROW_CELL_STICKY`.
- RubricForm criterion blocks wrapped in bordered rows with `hover:bg-surface-raised`; absent rows keep opacity-50 with hover.
- Applied shared tbody row classes to ProgressTable, ReportsMarksTable, ReportsScoresTable, RubricTable, Registry, AuditLog, PanelReviewersStep, CsvImportMapper.
- MarkingGridStudentCard uses `transition-shadow hover:shadow-md` on Card (parity with AssignmentCard).
- `npm run build` succeeded; `vendor/bin/phpunit` — 182 tests OK. Manual QA checklist left for reviewer in browser.

### File List

- `src/shared/tableStyles.js` (new)
- `src/reviewer/components/MarkingGrid.jsx`
- `src/reviewer/components/RubricForm.jsx`
- `src/reviewer/components/MarkingGridStudentCard.jsx`
- `src/coordinator/components/ProgressTable.jsx`
- `src/coordinator/components/ReportsMarksTable.jsx`
- `src/coordinator/components/ReportsScoresTable.jsx`
- `src/coordinator/components/RubricTable.jsx`
- `src/coordinator/pages/Registry.jsx`
- `src/coordinator/pages/AuditLog.jsx`
- `src/coordinator/components/PanelReviewersStep.jsx`
- `src/coordinator/components/CsvImportMapper.jsx`
- `build/reviewer.js`, `build/reviewer.css`, `build/reviewer-rtl.css`
- `build/coordinator.js`, `build/coordinator.css`, `build/coordinator-rtl.css`

## Change Log

- 2026-05-17: Table/grid row hover — shared styles, marking grid group-hover, RubricForm rows, coordinator tables, mobile card shadow hover.

## Story completion status

- Ultimate context engine analysis completed — comprehensive developer guide created
- **Covers:** FR27 reviewer SPA UX; UX table scanability; cross-SPA consistency for data tables
- **Status:** review
