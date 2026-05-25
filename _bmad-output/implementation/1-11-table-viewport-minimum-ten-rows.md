# Story 1.11: Table viewport — minimum 10 visible rows when space allows

Status: review

<!-- Follow-up to 1-10. Users report capped tables (reviewer marking grid, reports matrices) showing only ~1 body row. Root causes: default 5-row budget + `--pr-table-row-height: 2.5rem` underestimating real row height (chips, buttons, py-2). Epic 1 — shell UX only; no REST/DB changes. -->

## Story

As a **coordinator or reviewer** working with student rosters and score matrices,
I want data tables to show **at least 10 body rows** when the page has enough viewport height,
So that I can scan assignments and marks without excessive clicking **Add 5 more** or squinting at a one-row strip.

As a **reviewer** on the marking grid (`#/mark/...`),
I want the desktop student list to use available vertical space up to a sensible cap,
So that marking a full panel feels like a real spreadsheet, not a postage-stamp table.

## Acceptance Criteria

### 1. Default visible row budget — 10 when data and space allow

1. **Given** a `TableDataViewport` with **more than 10** body rows  
   **When** the page loads on a **desktop viewport** (≥ 1024px wide, ≥ 768px tall below the top bar) with normal chrome (header, filters, toolbar above the table)  
   **Then** the viewport `max-height` budget shows **at least 10 body rows** (plus header row(s)) without requiring **Add 5 more**  
   **And** if total rows ≤ 10, height shrinks to content (no empty padding).

2. **Given** a table with **6–10** rows  
   **When** rendered  
   **Then** all rows are visible without inner vertical scroll (no artificial 5-row cap).

3. **Given** a table with **more than 10** rows and ample viewport height  
   **When** the user has not clicked **Add 5 more** or **Show all**  
   **Then** default visible body rows = **10** (not 5).

4. **And** constant `TABLE_VIEWPORT_INITIAL_ROWS` in `src/shared/tableStyles.js` is **10** (update tests and story 1-10 references).

5. **And** `TABLE_VIEWPORT_REGISTRY_INITIAL_ROWS` may be **removed or aligned to 10** — Registry/Faculty should not need a separate 10 override once the global default is 10.

### 2. Viewport-aware floor — use space when available, shrink gracefully

6. **Given** available vertical space in `.pr-main` below the table toolbar (measured from viewport host or table anchor to bottom of `.pr-main` client rect, minus safe padding) can fit **more than 10** rows at the calibrated row height  
   **When** `bodyRowCount` exceeds 10  
   **Then** initial visible rows = `min(bodyRowCount, floor(availableHeight / rowHeight) - headerRowCount)` capped at a **reasonable maximum** (e.g. 20 — document chosen cap in Dev Agent Record)  
   **And** never less than **10** when at least 10 rows exist and space fits 10.

7. **Given** a **short viewport** (e.g. laptop with many notices above the table, or height &lt; ~600px in the main region) where 10 rows cannot fit  
   **When** the table renders  
   **Then** visible rows = **maximum rows that fit** without clipping the toolbar (minimum **3** body rows if `bodyRowCount ≥ 3`, else all rows)  
   **And** **Add 5 more** / **Show all** still work.

8. **And** viewport measurement uses `ResizeObserver` (or equivalent) on the table viewport host / `.pr-main`; recalculate on window resize and sidebar collapse (1.9).

9. **And** do **not** reintroduce document/body scroll — `.pr-main` remains the page scroll container (1.9, 1.10).

### 3. Calibrated row height — fix “only one row visible” regression

10. **Given** the reviewer **MarkingGrid** desktop grid (`role="table"`, `py-2` cells, StatusChip, **Update score** button)  
    **When** the viewport applies `max-height: calc(header + N × --pr-table-row-height)`  
    **Then** **N visible body rows** actually correspond to **N fully visible student rows** (not ~1 row with the rest clipped inside the box).

11. **And** either:
    - increase `--pr-table-row-height` globally to match measured semantic `<tr>` height (e.g. `py-3` registry rows), **or**
    - introduce `--pr-table-row-height-dense` vs `--pr-table-row-height-comfortable` and pass the appropriate token from `TableDataViewport` / `MarkingGrid`, **or**
    - measure first body row height once via `ResizeObserver` and set `--pr-table-row-row-height` inline per instance.

12. **And** two-row report headers (`headerRows={2}`) continue to use `HEADER_HEIGHT_DOUBLE` (4.75rem) in `TableScrollViewport.jsx` — include both header rows in “header budget” when computing fit.

### 4. Scroll and progressive controls (1.10 preserved)

13. **Given** more rows than the visible window  
    **When** the user scrolls inside `.pr-table-data-viewport`  
    **Then** vertical scroll stays **inside the table box**; horizontal scrollbar at bottom of box; sticky `<thead>` sticks to top of **box** (not `.pr-main`) unless **Show all** mode.

14. **Given** partial window  
    **When** the user clicks **Add 5 more**  
    **Then** viewport grows by **5** rows up to total (unchanged increment).

15. **Given** **Show all**  
    **When** activated  
    **Then** `pr-table-data-viewport--show-all` — full table height, `.pr-main` scrolls for content below (1.10).

16. **And** remove broken patterns from 1-9/1-10 regressions: no `flex-1 min-h-0` + `h-full max-h-none` on report table parents that squeeze viewport to ~1 row; audit `Reports.jsx` and report tab layouts.

### 5. Scope — tables to audit and fix

17. **Given** this story ships  
    **Then** verify/fix **every `TableDataViewport` consumer**:

| Component | Route / context | Notes |
|-----------|-----------------|-------|
| `MarkingGrid.jsx` | Reviewer `#/mark/...` desktop grid | **Primary user report** — grid row height |
| `ReportsMarksTable.jsx` | Coordinator reports | `headerRows={2}` |
| `ReportsOverallScoresTable.jsx` | Coordinator reports | `headerRows={2}` |
| `ReportsScoresTable.jsx` | Panel head report | `headerRows={2}` |
| `ReportsConsolidatedTable.jsx` | Consolidated export preview | |
| `Registry.jsx` | Student registry | Remove redundant `initialRows={10}` if global default is 10 |
| `FacultyAccounts.jsx` | Faculty pool | Same |
| `ProgressTable.jsx` | Session progress | |

18. **Given** `TableScrollWrapper` tables (no progressive cap today)  
    **When** page content is long and roster has 10+ rows (wizard panel reviewers, session wizard enrolment, audit log, assignments step)  
    **Then** either they scroll naturally with `.pr-main` **or** (if QA finds clipped/squashed layout) wrap with `TableDataViewport` using the same 10-row / viewport-aware rules — **document decision per table in Dev Agent Record**.

19. **And** mobile marking **cards** (`lg:hidden`) unchanged — this story targets desktop/table viewports.

### 6. Regression

20. **And** sidebar collapse, resize, styled scrollbars (1.9) unchanged.

21. **And** `npm run build` succeeds; `composer test` passes; update `src/shared/useTableRowWindow.test.js` for initial rows = 10.

22. **Manual verification** (document in Dev Agent Record):

| Step | Pass criteria |
|------|----------------|
| Reviewer marking grid, 15+ students, 1280×900 | ≥ 10 student rows visible without **Add 5 more**; inner scroll for row 11+ |
| Reviewer assignments → open panel | Grid not reduced to ~1 row |
| Reports → Rubric marks, 50+ rows | ≥ 10 rows default; header sticky in box |
| Registry, 20+ students | ≥ 10 rows default (same as marks) |
| Short window (~500px main height) | Fewer rows fit, no toolbar overlap, still scrollable |
| **Add 5 more** / **Show all** / **Show fewer** | Same behaviour as 1.10 |
| `.pr-main` wheel over page header | Page scrolls (no trap) |

## Tasks / Subtasks

- [x] **Constants:** Set `TABLE_VIEWPORT_INITIAL_ROWS = 10`; align/remove `TABLE_VIEWPORT_REGISTRY_INITIAL_ROWS`; update CSS fallback in `app-shell.css` (`--pr-table-visible-rows: 10`)
- [x] **Row height calibration:** Fix underestimation so 10 budget rows = 10 visible rows (MarkingGrid grid + semantic `<tr>`); document chosen approach in Dev Agent Record
- [x] **`useTableViewportCapacity` (or extend `useTableRowWindow`):** Measure available height; compute `initialVisibleRows`; integrate into `TableDataViewport` default state
- [x] **Layout audit:** Reports tab wrappers, marking page — remove flex squeeze that caps viewport below CSS `max-height`
- [x] **Consumers:** Pass `bodyRowCount` everywhere; drop redundant per-page `initialRows={10}` overrides
- [x] **Tests:** Update `useTableRowWindow.test.js`; add tests for viewport capacity helper (pure functions, jsdom-friendly)
- [x] **Optional:** Wrap dense `TableScrollWrapper` rosters if manual QA shows clipping
- [x] Run `npm run build` and `composer test`

## Dev Notes

### Problem diagnosis (from user + codebase)

- Story **1-10** capped tables at **5 body rows** via `--pr-table-visible-rows` and `TABLE_VIEWPORT_INITIAL_ROWS = 5`.
- **Registry** already overrides to 10 — inconsistent UX.
- **Marking grid** cells use `py-2`, chips, and buttons; real row height ≈ **3.5–4rem**, but CSS uses **`--pr-table-row-height: 2.5rem`**. A 5-row budget (~12.5rem body) may show **only 1–2 actual rows**, matching user report.
- User goal: **minimum 10 rows when viewport height allows**, while keeping **inner table scroll** and **`.pr-main` page scroll** (1.9, 1.10).

### Architecture — do not change

- Viewport-bound shell: `body` / `#pr-root` / `.pr-shell` `overflow: hidden`; `.pr-main` `overflow-y: auto`.
- **Do not slice DOM rows** — height budget only (1.10).
- Progressive toolbar: **Add 5 more** | **Remove 5** | **Show all** | **Show fewer**.

### Suggested implementation sketch

```text
pr-table-viewport-host
  toolbar
  .pr-table-data-viewport  ← max-height = header + visibleRows × rowHeight (calibrated)
    table / grid
```

1. **`getViewportRowCapacity(availablePx, headerRows, rowHeightPx, minRows, maxRows, totalRows)`** — pure function; unit test.
2. **`useTableViewportCapacity(ref, { headerRows, totalRows, minRows: 10, maxRows: 20 })`** — observes host + `.pr-main`; returns `suggestedInitialRows`.
3. **`useTableRowWindow`** — seed `visibleRows` from `max(initialRows constant, suggestedInitialRows)` on mount/resize when not show-all.
4. **Row height:** Prefer measuring first tbody/grid row once after paint; fallback `3rem` for comfortable, `2.5rem` for dense audit tables if split tokens.

### Files (expected touch)

| File | Change |
|------|--------|
| `src/shared/tableStyles.js` | `TABLE_VIEWPORT_INITIAL_ROWS = 10` |
| `src/shared/useTableRowWindow.js` | Optional capacity integration |
| `src/shared/useTableViewportCapacity.js` | **New** — measurement hook |
| `src/shared/TableScrollViewport.jsx` | Wire capacity; row height style vars |
| `assets/css/app-shell.css` | Default `--pr-table-visible-rows: 10`; optional second row-height token |
| `src/reviewer/components/MarkingGrid.jsx` | `rowHeightVariant` or measured grid rows |
| `src/coordinator/pages/Registry.jsx`, `FacultyAccounts.jsx` | Remove duplicate `initialRows` if redundant |
| `src/coordinator/pages/Reports.jsx` (and children) | Layout audit — no flex squeeze |
| `src/shared/useTableRowWindow.test.js` | Expect 10-row default |

### Previous story intelligence (1-10)

- **Worked:** Progressive model, `--pr-table-visible-rows` inline style, sticky header in box, show-all → `.pr-main` scroll.
- **Avoid:** `overscroll-contain` on Y; `overflow-y: visible` on capped viewport; Page scroll / Fit to panel toggle; `flex-1 min-h-0` + `h-full max-h-none` on Reports parents.
- **1-10 default was 5** — this story **supersedes** AC #5–7 default row count for new work.

### References

- [Source: _bmad-output/implementation/1-10-table-viewport-progressive-rows.md]
- [Source: _bmad-output/implementation/1-9-app-shell-scroll-regions.md]
- [Source: assets/css/app-shell.css — `.pr-table-data-viewport`, `--pr-table-row-height`]
- [Source: src/shared/TableScrollViewport.jsx]
- [Source: src/shared/tableStyles.js]
- [Source: src/reviewer/components/MarkingGrid.jsx — `TableDataViewport`, grid `py-2` cells]
- [Source: _bmad-output/planning/ux-design-specification.md — desktop-first dense tables]

## Dev Agent Record

### Agent Model Used

Composer (dev-story)

### Debug Log References

### Completion Notes List

- **Row height:** `useMeasuredTableRowHeight` measures the first semantic `tbody tr` or the first grid body row (`[role="row"].group`) via `ResizeObserver`, and sets `--pr-table-row-height` inline on the viewport. Fallbacks: 3rem comfortable, 3.5rem dense (`MarkingGrid` uses `rowHeightVariant="dense"`).
- **Viewport capacity:** `getViewportRowCapacity` + `useTableViewportCapacity` observe `.pr-main` and the table host; initial rows = min(total, fit, max 20), floor 10 when space allows, floor 3 on short viewports. Anchor is `.pr-table-data-viewport` top (below toolbar).
- **Constants:** `TABLE_VIEWPORT_INITIAL_ROWS = 10`; removed `TABLE_VIEWPORT_REGISTRY_INITIAL_ROWS`; CSS `--pr-table-visible-rows: 10` and comfortable/dense row-height tokens.
- **Layout audit:** No `flex-1 min-h-0` / `h-full max-h-none` squeeze on Reports or marking parents; `TableScrollWrapper` rosters unchanged (scroll with `.pr-main`).
- **Tests:** `npx wp-scripts test-unit-js src/shared/useTableRowWindow.test.js src/shared/getViewportRowCapacity.test.js` — 11 passed; `npm run build` OK; `composer test` — 337 OK.

### Manual verification (pending in browser)

| Step | Pass criteria |
|------|----------------|
| Reviewer marking grid, 15+ students, 1280×900 | ≥ 10 student rows visible without **Add 5 more** |
| Reports → Rubric marks, 50+ rows | ≥ 10 rows default; sticky header in box |
| Registry, 20+ students | ≥ 10 rows default |
| Short window (~500px main height) | Fewer rows fit, controls still work |

### File List

- assets/css/app-shell.css
- src/shared/tableStyles.js
- src/shared/getViewportRowCapacity.js
- src/shared/getViewportRowCapacity.test.js
- src/shared/useTableViewportCapacity.js
- src/shared/useMeasuredTableRowHeight.js
- src/shared/TableScrollViewport.jsx
- src/shared/useTableRowWindow.test.js
- src/reviewer/components/MarkingGrid.jsx
- src/coordinator/pages/Registry.jsx
- src/coordinator/pages/FacultyAccounts.jsx

## Change Log

- 2026-05-22: Story created (create-story) — minimum 10 visible rows, viewport-aware capacity, row-height calibration for marking grid and all `TableDataViewport` consumers
- 2026-05-22: Implemented 10-row default, measured row height, viewport-aware capacity (max 20), removed registry override; tests and build pass
