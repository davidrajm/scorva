# Story 5.11: Reviewer marking grid — mobile student cards

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer** marking on a phone or narrow viewport,
I want each student on my panel shown as a scannable card instead of a horizontally scrolled table,
So that I can see name, status, and criterion scores at a glance and open score entry without pinch-zooming a wide grid.

## Acceptance Criteria

1. **Responsive layout — cards below `lg`, grid at `lg+`**
   - **Given** the marking grid at `#/mark/:sessionId/:reviewId/:panelId` (`MarkingGrid.jsx`)
   - **When** the viewport is **below** the Tailwind `lg` breakpoint (&lt; 1024px)
   - **Then** students render as a **vertical stack of cards** (one card per student), not inside the horizontal-scroll grid wrapper
   - **And** there is **no** `overflow-x-auto` / `min-w-[640px]` constraint forcing horizontal scroll for the student list on mobile
   - **When** the viewport is **`lg` and wider** (≥ 1024px)
   - **Then** the existing CSS **grid table** layout is preserved (same columns, sticky student column, criterion columns, **Update score** column)
   - **And** desktop behaviour matches pre-change: `role="table"`, header row, `gridTemplateColumns` from `useMemo`, serial **No.** column, all chips and scores unchanged

2. **Mobile card content (per student)**
   - **Given** a student row in API order
   - **When** their card renders
   - **Then** the card shows at minimum:
     - **Index:** 1-based serial (`rowIndex + 1`) — same numbering as desktop **No.** column
     - **Identity:** student name (prominent) and reg no (`tabular-nums`, muted)
     - **Status row:** Attendance chip + mark status chip (reuse `attendanceStatusChip` / `studentStatusChip` — do not duplicate label logic)
     - **Scores:** each rubric criterion as a labelled row (criterion label + score + `FlaggedMarkChip` when flagged); absent students show em dash for scores (same as grid)
     - **Action:** full-width or card-footer **Update score** `Button` with `icon="pencil"`, same `disabled` rules as grid (`reviewFrozen || student.mark_status === 'frozen'`)
   - **And** cards use shared `Card` (`src/shared/components/Card.jsx`) or equivalent border/surface tokens (`border-border`, `bg-surface-raised`, `rounded-md`) consistent with `AssignmentCard` / Direction 1
   - **And** spacing is comfortable for touch (min ~44px tap targets on primary action; adequate padding)

3. **Shared behaviour — no API or routing changes**
   - **Given** any card or grid row
   - **When** the reviewer taps **Update score**
   - **Then** the same `openModal(student)` path runs (`?student=` deep link, `ScoreEntryModal`, `RubricForm`, freeze/unfreeze header actions unchanged)
   - **And** no new REST endpoints; no PHP changes required unless tests demand none
   - **And** freeze / request unfreeze / coordinator lock banners behave identically on all breakpoints

4. **Accessibility**
   - **Given** mobile card list
   - **When** assistive tech reads the page
   - **Then** cards live in a semantic list (`ul` / `li`) or grouped region with a visible or `sr-only` heading (e.g. “Students”)
   - **And** criterion score rows associate label with value (`<dl>` or explicit `aria-labelledby`)
   - **And** desktop grid keeps `role="table"` / `row` / `cell` / `columnheader` — do not apply table roles to the card list

5. **Regression and polish**
   - **And** empty student list: keep existing “No students on this panel.” message; no empty card chrome
   - **And** loading and error states unchanged
   - **And** many criteria (8+): cards scroll vertically only; criterion block may use compact two-column sub-grid inside card if needed — no horizontal page scroll
   - **And** manual QA at **375px** and **1280px** widths on marking grid with 3+ criteria
   - **And** run `npm run build`; `composer test` (no new tests required unless extracting pure helpers)

## Tasks / Subtasks

- [x] **Extract row helpers (optional but recommended):** Move `formatScore`, `scoreForCriterion`, `flaggedForCriterion`, chip helpers to stay in `MarkingGrid.jsx` or a small `markingGridUtils.js` — avoid duplicating score/absent logic between card and grid
- [x] **Mobile cards:** Add `MarkingGridStudentCard.jsx` (reviewer-local) or inline block with `lg:hidden`; map `students` with same `rowIndex` numbering
- [x] **Desktop grid:** Wrap existing grid in `hidden lg:block` (or equivalent); remove mobile horizontal-scroll wrapper from narrow viewports only
- [x] **Visual QA:** 375px, 768px, 1024px; verify sticky student column only on desktop; verify flagged chips and absent scores
- [x] Run `npm run build` and `composer test`

## Dev Notes

### User request (source)

> Reviewer page enhancements: students list is in a table with less mobile-first approach. Make it like **cards for each student on mobile**. Use the **same grid to look like a table on lg devices**.

### What exists today

`MarkingGrid.jsx` renders **one** responsive structure:

```295:438:src/reviewer/components/MarkingGrid.jsx
			<div className="overflow-x-auto rounded-md border border-border">
				<div
					className="min-w-[640px]"
					role="table"
					style={ {
						display: 'grid',
						gridTemplateColumns: gridTemplate,
					} }
				>
					{/* header + student rows via display:contents */ }
				</div>
			</div>
```

- `overflow-x-auto` + `min-w-[640px]` forces horizontal scroll on phones — **this is what we are fixing**
- Story **5.6** originally specified “horizontal scroll on small screens”; **5.11 supersedes that mobile behaviour** while keeping the grid for large screens
- Story **5.9** added **No.** column and icon buttons — preserve on both layouts

### Recommended implementation pattern

**Dual layout (clearest for dev agent):**

| Viewport | Container | Component |
|----------|-----------|-----------|
| `< lg` | `ul` with `gap-3`, `lg:hidden` | `MarkingGridStudentCard` per student |
| `≥ lg` | `hidden lg:block overflow-x-auto` + grid `role="table"` | Existing grid markup (minimal diff) |

Extract a single inner render for criterion scores if copy-paste grows:

```jsx
// Pseudocode — not prescriptive
function CriterionScores({ student, criteria, isAbsent }) { ... }

// Mobile
<ul className="flex flex-col gap-3 lg:hidden">
  {students.map((student, i) => (
    <li key={student.id}>
      <MarkingGridStudentCard rowIndex={i} student={student} ... />
    </li>
  ))}
</ul>

// Desktop
<div className="hidden lg:block overflow-x-auto rounded-md border border-border">
  {/* existing grid */}
</div>
```

**Do not** try to make CSS `display:grid` + `contents` morph into cards with media queries alone — maintenance cost is high.

### Mobile card layout (Direction 1)

Mirror `AssignmentCard` hierarchy:

- Top: `No. {n}` eyebrow or badge + name (`text-base font-semibold`)
- Reg no on same row or below (`text-sm text-muted tabular-nums`)
- Chip row: `flex flex-wrap gap-2`
- Criteria: `dl` with `grid grid-cols-2 gap-x-3 gap-y-2 text-sm` — label truncated with `title={c.label}` for long rubric names
- Footer: `Button` `variant="secondary"` `icon="pencil"` width `w-full sm:w-auto`

Use `Card` without `onClick` (action is explicit button, not whole-card click).

### Breakpoint choice

- User asked for **`lg`** — Tailwind default `lg` = **1024px** (aligns with UX spec “desktop-first 1024px+ primary”)
- Do **not** switch to `md` (768px) unless product asks — tablets in portrait may still see cards until 1024px

### Out of scope

- Assignment list cards (`MarkAssignments` / `AssignmentCard`) — already card-based
- Coordinator `ProgressTable`, reports tables
- Changing criterion column abbreviations on desktop
- New sorting/filtering of students
- Backend or `gridTemplate` algorithm changes

### Architecture compliance

| Rule | Action |
|------|--------|
| Reviewer code in `src/reviewer/` | New card component under `src/reviewer/components/` |
| Shared UI only via `src/shared/components` | Reuse `Card`, `Button`, `StatusChip`, `FlaggedMarkChip` |
| Tailwind + `#pr-root` important | Use utility classes; no new npm packages |
| HashRouter / deep links | Unchanged |
| No REST changes | UI-only story |

### Critical files (touch list)

- `src/reviewer/components/MarkingGrid.jsx` — responsive split, wrap grid
- `src/reviewer/components/MarkingGridStudentCard.jsx` — **new** (preferred)
- `build/reviewer.js` / CSS — via `npm run build`

### Previous story intelligence

| Story | Relevant learning |
|-------|-------------------|
| **5.6** | Grid + modal funnel; `?student=` param; save-only drafts |
| **5.7** | `attendance_status` / absent → null scores in cells |
| **5.8** | Unfreeze UI in header; row disabled when frozen |
| **5.9** | Serial column, icons on actions, `AssignmentCard` as card styling reference |
| **5.10** | Half-point scores in modal only — display `formatScore` unchanged |

### Testing notes

- **Automated:** Existing PHPUnit suite should pass unchanged
- **Manual checklist:**
  - [ ] 375px: vertical cards, no horizontal scroll on page
  - [ ] 1280px: table grid, sticky student column, horizontal scroll only if many criteria exceed viewport
  - [ ] Update score → modal → save → card and grid both refresh after `load()`
  - [ ] Frozen review: buttons disabled on card and grid
  - [ ] Flagged criterion visible on card
  - [ ] Deep link `?student={id}` opens modal from either layout

### References

- [Source: _bmad-output/implementation/5-6-reviewer-marking-grid-freeze.md — grid + mobile note superseded for &lt; lg]
- [Source: _bmad-output/implementation/5-9-reviewer-page-ui-polish.md — card patterns, serial numbers, icons]
- [Source: _bmad-output/planning/ux-design-specification.md — § responsive 375/1024, reviewer list row height, Direction 1]
- [Source: src/reviewer/components/AssignmentCard.jsx — card visual reference]
- [Source: src/reviewer/components/MarkingGrid.jsx — current grid implementation]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Extracted shared helpers to `markingGridUtils.js` (`formatScore`, criterion score/flag lookups, status chips, `isStudentRowFrozen`).
- Added `MarkingGridStudentCard.jsx`: `Card` layout with serial no., name/reg, attendance + mark chips, criterion `<dl>` with `aria-labelledby`, full-width **Update score** (`min-h-11`).
- `MarkingGrid.jsx`: dual layout — `ul` + cards below `lg` (`lg:hidden`); desktop grid unchanged inside `hidden lg:block` with `overflow-x-auto` + `min-w-[640px]` only at `lg+`.
- Empty panel: message only, no card/table chrome.
- `./vendor/bin/phpunit`: 182 tests OK. `npm run build`: success.

### File List

- src/reviewer/components/markingGridUtils.js (new)
- src/reviewer/components/MarkingGridStudentCard.jsx (new)
- src/reviewer/components/MarkingGrid.jsx (modified)
- build/reviewer.js (built)
- build/reviewer.css (built)
- build/reviewer-rtl.css (built)

## Change Log

- 2026-05-17: Responsive marking grid — mobile student cards below `lg`, desktop table at `lg+`.

## Story completion status

- Ultimate context engine analysis completed — comprehensive developer guide created
- **Covers:** FR27 reviewer SPA UX; UX-DR5, UX-DR94; mobile-first enhancement on Epic 5 marking grid (additive story 5.11)
- **Status:** review
