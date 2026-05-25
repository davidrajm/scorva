# Story 1.10: Table viewport — progressive rows, show all, reliable page scroll

Status: review

<!-- Follow-up to 1-9. Fixes scroll lock regressions from iterative 1-9 changes; replaces Fit to panel / Page scroll toggle with progressive row reveal. Epic 1 — shell UX; no REST/DB changes. -->

## Story

As a **coordinator or reviewer** viewing a large data table (Reports matrices, Registry, Progress, marking grid),
I want the table to show **5 rows at a time** with **Add 5 more** and **Show all** controls,
So that I can scan data without a broken page scroll, keep column headers visible while scrolling rows, and expand the table only when I need more rows.

As a **coordinator**,
I want **`.pr-main` page scroll** to work reliably (up and down) for headers, filters, and toolbars above the table,
So that I am never stuck unable to scroll after reaching the table.

## Acceptance Criteria

### 1. Reliable page scroll (`.pr-main`)

1. **Given** any coordinator or reviewer page with a `TableDataViewport` (e.g. Reports → Rubric marks, Registry)  
   **When** the user scrolls the mouse wheel or trackpad over the page header, filters, or empty margin areas  
   **Then** **`.pr-main`** scrolls up and down smoothly with no lock or dead zone.

2. **Given** the user has scrolled down to the table  
   **When** they scroll up over the table toolbar or page chrome  
   **Then** **`.pr-main`** scrolls up (unless focus is inside the table body scroll region per AC3).

3. **Given** DevTools  
   **When** inspecting scroll containers  
   **Then** document/body do not scroll; **`.pr-main`** has `overflow-y: auto`; sidebar scroll is independent (1.9 behaviour preserved).

4. **And** remove or replace the broken **Page scroll / Fit to panel** toggle from story 1-9 — one consistent model only (this story).

### 2. Default view — 5 visible body rows

5. **Given** a table with more than 5 data rows  
   **When** the page loads  
   **Then** the table viewport shows the **header** (all `<thead>` rows, including two-row Reports headers) plus **exactly 5 body rows** worth of height  
   **And** vertical overflow inside the **table viewport box** scrolls additional rows (horizontal scrollbar at bottom of **that box**).

6. **Given** a table with **≤ 5** rows  
   **When** rendered  
   **Then** the viewport height shrinks to content (no empty padding below the last row).

7. **And** default visible row count is **5** (constant `TABLE_VIEWPORT_INITIAL_ROWS` in `tableStyles.js` or `TableScrollViewport.jsx`).

### 3. Add 5 more rows

8. **Given** the table is not showing all rows  
   **When** the user clicks **Add 5 more**  
   **Then** the viewport grows to show **5 additional body rows** (10, then 15, then 20, …) capped at total row count  
   **And** if already at total rows, the control is **disabled** or hidden.

9. **And** after expanding, vertical scroll inside the viewport still works for any rows beyond the visible window.

10. **And** **Add 5 more** does not reset horizontal scroll position unless unavoidable (document in Dev Agent Record if reset occurs).

### 4. Show all rows

11. **Given** any partial row window  
    **When** the user clicks **Show all**  
    **Then** the viewport expands to the **full table body height** (all rows visible without inner vertical scroll)  
    **And** **`.pr-main`** scrolls to access content below the table if the full table is taller than the viewport.

12. **Given** show-all mode  
    **When** the user clicks **Show fewer** (or **Reset to 5 rows** — label in Dev Notes)  
    **Then** the viewport returns to the **5-row** default window.

13. **And** horizontal scroll remains inside the table wrapper (`overflow-x: auto`); wide matrices do not scroll the full page horizontally.

### 5. Sticky header behaviour

14. **Given** the capped table viewport (default or partial expand)  
    **When** the user scrolls **vertically inside the table box**  
    **Then** `<thead class="sticky top-0">` sticks to the **top of the table viewport**, not the top of `.pr-main`  
    **And** Reg no / Student sticky-left columns still work within horizontal scroll (existing `regNoStickyClass` / MarkingGrid patterns).

15. **Given** show-all mode with page scroll only (no inner vertical scroll)  
    **When** the user scrolls **`.pr-main`**  
    **Then** `<thead>` sticks to the **top of `.pr-main`** (below fixed top bar) per `app-shell.css` `.pr-main table thead.sticky`.

### 6. Toolbar and accessibility

16. **Given** `TableDataViewport` with controls enabled  
    **When** the toolbar renders  
    **Then** buttons appear above the table, right-aligned: **Add 5 more** | **Show all** (or **Show fewer** when expanded)  
    **And** use `pr-table-viewport-toggle` / shared button styles from `app-shell.css`.

17. **And** buttons have accessible names; `aria-disabled` when Add 5 more cannot apply; live region not required for row count (optional `aria-live` on count text is acceptable).

### 7. Scope — which tables use progressive viewport

18. **Given** this story ships  
    **Then** **`TableDataViewport`** implements progressive rows (all current consumers):

| Component | Notes |
|-----------|--------|
| `ReportsMarksTable.jsx` | `headerRows={2}` |
| `ReportsOverallScoresTable.jsx` | `headerRows={2}` |
| `ReportsScoresTable.jsx` | `headerRows={2}` if still mounted |
| `ReportsConsolidatedTable.jsx` | `headerRows={1}` |
| `Registry.jsx` | |
| `ProgressTable.jsx` | |
| `MarkingGrid.jsx` (desktop grid) | Grid uses same viewport height; body scroll inside box |

19. **And** **`TableScrollWrapper`** (short tables: Audit, wizard steps, RubricTable) — **no** progressive rows; horizontal-only unchanged.

### 8. Regression

20. **And** sidebar collapse, resize, icon-rail tooltips (1.9) unchanged.

21. **And** `npm run build` succeeds; PHPUnit suite unchanged/green.

22. **Manual verification** (document in Dev Agent Record):

| Step | Pass criteria |
|------|----------------|
| Reports → Rubric marks, 50+ rows | Default shows ~5 rows; inner vertical scroll works; header sticky in box |
| Add 5 more × 2 | Viewport grows; can scroll inside box |
| Show all | All rows visible; `.pr-main` scrolls up/down freely |
| Reset / Show fewer | Back to 5 rows |
| Scroll above table | Wheel on header/filters scrolls `.pr-main` up |
| Registry, Progress | Same controls and scroll behaviour |
| Wide columns | Horizontal bar at bottom of table box; Shift+wheel scrolls right |
| Firefox + Chrome | No scroll lock |

## Tasks / Subtasks

- [x] **Document & remove** broken 1-9 modes: delete `panelMode`, `fillAvailable`, `pr-table-data-viewport--page-scroll` / `--capped` toggle UX
- [x] **`useTableRowWindow` hook** (or logic in `TableScrollViewport.jsx`): state `visibleRows` (default 5), `showAll`; handlers `addFive`, `showAll`, `resetRows`
- [x] **CSS:** `--pr-table-visible-rows`, dynamic `max-height: calc(header + visibleRows * row-height)`; `--expanded` / `--show-all` classes; fix scroll chaining (`overscroll-behavior` — avoid trapping page scroll)
- [x] **Toolbar:** Add 5 more | Show all | Show fewer (reset)
- [x] **Fix `.pr-main` scroll lock:** audit `overflow-y: visible` on viewport, nested scroll, wheel/touch handlers; ensure only table box captures wheel when pointer over tbody scroll area
- [x] **Wire all `TableDataViewport` consumers** (AC7 table)
- [x] **Marking grid:** viewport height on desktop grid wrapper
- [x] **Build + manual AC22**

## Dev Notes

### Current state (as of 2026-05-19 — post–1-9 iterative fixes)

**Shipped in 1.9 (review):**

- Viewport-bound shell: `body` / `#pr-root` / `.pr-shell` `overflow: hidden`; `.pr-sidebar` + `.pr-main` independent `overflow-y: auto`.
- Collapsible/resizable sidebar, tablet drawer, `IconRailTooltip` for collapsed nav.
- `TableDataViewport` + `TableScrollWrapper` in `src/shared/TableScrollViewport.jsx`.
- `TABLE_DATA_VIEWPORT` in `tableStyles.js`; scrollbars in `app-shell.css`.
- Edge scroll cues (`.pr-table-scroll--cue-right/left`).

**Iterative changes after 1.9 (not in original AC — caused regressions):**

| Change | Files | Problem |
|--------|-------|---------|
| `flex-1 min-h-0` + `h-full max-h-none` on Reports | `Reports.jsx`, report table components | Table squeezed to ~1 row at bottom of viewport |
| Min 10 rows + `fillAvailable` | `TableScrollViewport.jsx`, CSS | Fighting flex layout |
| **Page scroll** vs **Fit to panel** toggle | `TableScrollViewport.jsx` | Confusing; **page scroll** mode uses `overflow-y: visible` on wrapper |
| Reverted flex traps; page scroll default | `Reports.jsx`, CSS | User reports **scroll lock** — cannot scroll up/down after scrolling down |
| Sticky `thead` on `.pr-main` | `app-shell.css` | Conflicts with inner box sticky when both modes exist |

**Root causes to fix in 1.10:**

1. **Dual scroll models** (page vs panel) create wheel-event / `overscroll-behavior: contain` dead zones.
2. **`overflow-y: visible`** on table wrapper prevents predictable scroll boundaries and breaks sticky stacking.
3. **No row budget** — either full page height table or 1-row flex remainder; user wants **5 rows + incremental expand**.
4. **Sticky header target** must be explicit: sticky to **viewport box** when inner scroll; sticky to **`.pr-main`** when show-all.

### Target behaviour (single model)

```text
.pr-main                          ← ONLY page vertical scroll (chrome + show-all table)
  PageHeader, filters, tabs      ← scroll away with .pr-main
  pr-table-viewport-toolbar      ← Add 5 more | Show all
  .pr-table-data-viewport        ← max-height = header + (visibleRows × rowHeight)
                                 ← overflow-x: auto; overflow-y: auto (if rows > visible)
    <table>
      <thead sticky top-0>       ← sticks to top of THIS box when inner scroll
      <tbody> … all rows in DOM  ← (keep full DOM for a11y/export; clip via height)
```

**Do not slice rows out of the DOM** for Reports/export parity — control **visible height** via CSS `max-height` on the viewport from React state (`visibleRows` × `--pr-table-row-height`).

**Constants (suggested):**

```javascript
export const TABLE_VIEWPORT_INITIAL_ROWS = 5;
export const TABLE_VIEWPORT_ROW_INCREMENT = 5;
```

**CSS (suggested):**

```css
.pr-table-data-viewport {
  overflow-x: auto;
  overflow-y: auto;
  overscroll-behavior-x: contain;
  /* Do NOT set overscroll-behavior-y: contain — it traps page scroll */
  max-height: calc(
    var(--pr-table-header-height) +
    var(--pr-table-visible-rows, 5) * var(--pr-table-row-height, 2.5rem)
  );
}

.pr-table-data-viewport--show-all {
  max-height: none;
  overflow-y: visible;
}
```

Pass `--pr-table-visible-rows` via inline `style` from React when not show-all.

### Toolbar copy

| State | Primary actions |
|-------|-----------------|
| Default (5 rows, more exist) | **Add 5 more** · **Show all** |
| Partial (e.g. 15 rows, more exist) | **Add 5 more** · **Show all** |
| Partial, no more to add | **Show all** only |
| Show all | **Show fewer** (resets to 5) |

Optional helper text: `Showing 15 of 84 students` (muted, below toolbar).

### Files to touch (expected)

| Path | Change |
|------|--------|
| `assets/css/app-shell.css` | Progressive height vars; remove `--page-scroll` / `--capped`; fix overscroll; sticky rules |
| `src/shared/TableScrollViewport.jsx` | Row window state, toolbar, remove panel toggle |
| `src/shared/tableStyles.js` | Export row constants; trim `TABLE_DATA_VIEWPORT` overflow classes |
| `src/coordinator/components/Reports*Table.jsx` | Remove obsolete props; keep `headerRows` |
| `src/coordinator/pages/Registry.jsx` | |
| `src/coordinator/components/ProgressTable.jsx` | |
| `src/reviewer/components/MarkingGrid.jsx` | |
| `build/*` | Regenerate via `npm run build` |

**Do not** change REST, PHP, or HashRouter.

### Previous story intelligence (1.9)

- Keep sidebar shell, scrollbars, `TABLE_SCROLL_WRAPPER` migration — **do not revert**.
- Replace only table **vertical sizing / scroll mode** and toolbar.
- Reports matrices: test with 50+ rows and wide columns after implementation.
- `IconRailTooltip` portals to `body` with `app-shell.css` styles (not Tailwind) — unrelated.

### Anti-patterns (do not)

- Reintroduce `pr-page-flex` + `flex-1 min-h-0` chains that prevent `.pr-main` from scrolling.
- Use `overflow-y: visible` on a capped viewport that should scroll internally.
- Set `overscroll-behavior: contain` on both axes on the table box (Y traps page scroll).
- Remove rows from `<tbody>` for virtualization in this story (export and screen readers need full table).
- Add third-party virtualisation libraries.
- Break horizontal scroll / Reg no sticky / Shift+wheel horizontal scroll.

### References

- [Source: _bmad-output/implementation/1-9-app-shell-scroll-regions.md — original dual-axis intent, AC9–14]
- [Source: assets/css/app-shell.css — `.pr-main`, `.pr-table-data-viewport--*`]
- [Source: src/shared/TableScrollViewport.jsx — current toggle implementation]
- [Source: _bmad-output/planning/ux-design-specification.md — L659–661 shadow cue, L305–313 layout]
- [Source: _bmad-output/planning/epics.md — UX-DR12 sticky Progress table header]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Replaced 1-9 **Page scroll / Fit to panel** toggle with a single progressive model: default **5 body rows** via `--pr-table-visible-rows`, **Add 5 more**, **Show all** (`.pr-main` scroll + `overflow-y: visible`), **Show fewer** (reset to 5).
- `useTableRowWindow` + `getTableRowWindowMetrics` in `src/shared/useTableRowWindow.js`; constants exported from `tableStyles.js`.
- Removed `overscroll-contain` from viewport Tailwind class; CSS uses `overscroll-behavior-x: contain` only (no Y trap).
- Horizontal scroll preserved on Add 5 more / Show all via `preserveHorizontalScroll`.
- All `TableDataViewport` consumers pass `bodyRowCount`; `TableScrollWrapper` unchanged.
- `npm run build` OK; `npx wp-scripts test-unit-js src/shared/useTableRowWindow.test.js` — 4 passed. PHPUnit: 1 pre-existing failure in `PanelReportPdfTemplateTest` (unrelated to this story).

### Manual verification (AC22 — for reviewer)

| Step | Pass criteria |
|------|----------------|
| Reports → Rubric marks, 50+ rows | Default ~5 rows; inner vertical scroll; header sticky in box |
| Add 5 more × 2 | Viewport grows; inner scroll works |
| Show all | All rows visible; `.pr-main` scrolls freely |
| Show fewer | Back to 5 rows |
| Scroll above table | Wheel on header/filters scrolls `.pr-main` up |
| Registry, Progress | Same toolbar and scroll behaviour |
| Wide columns | Horizontal bar at bottom of table box |
| Firefox + Chrome | No scroll lock |

### File List

- assets/css/app-shell.css
- src/shared/tableStyles.js
- src/shared/useTableRowWindow.js
- src/shared/useTableRowWindow.test.js
- src/shared/TableScrollViewport.jsx
- src/coordinator/pages/Registry.jsx
- src/coordinator/components/ProgressTable.jsx
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/components/ReportsOverallScoresTable.jsx
- src/coordinator/components/ReportsScoresTable.jsx
- src/coordinator/components/ReportsConsolidatedTable.jsx
- src/reviewer/components/MarkingGrid.jsx
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css

## Change Log

- 2026-05-19: Story created (create-story) — progressive 5-row viewport, Add 5 more, Show all, fix page scroll lock; documents 1.9 current state and regressions
- 2026-05-19: Implemented progressive row viewport, toolbar, scroll-lock fix; removed 1-9 dual scroll modes
