# Story 7.9: Reports overall scores — panel-slot matrix (parity with rubric marks)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want the **Overall scores** report tab to use the same table structure as **Rubric marks** (fixed identity columns, panel reviewer name slots, sortable score columns, weighted review score) but with **one overall reviewer total per slot** instead of per-rubric criterion cells,
So that I can compare committee totals in the same layout I already use for criterion marks, without a wide matrix of every reviewer in the project.

## Table structure (canonical)

Mirror **Rubric marks** after story **7.8** (panel-scoped slots). **Remove** the rubric criterion dimension entirely — each reviewer slot gets **one** overall score cell (Level 1 `ScoreService::calculate_reviewer_total()`), not one cell per criterion.

### Header layout (two rows — same as `ReportsMarksTable`)

**Row 1** — fixed columns + reviewer **name** slots + one score **group** + trailing total:

| Reg no | Student | Attendance | Status | Reviewer 1 | Reviewer 2 | … | Reviewer N | **Reviewer overall** *(colSpan = N)* | **Weighted review score** |
|--------|---------|------------|--------|------------|------------|---|------------|--------------------------------------|---------------------------|
| rowSpan 2 | rowSpan 2 | rowSpan 2 | rowSpan 2 | rowSpan 2 | rowSpan 2 | … | rowSpan 2 | *(group only — no rowSpan)* | rowSpan 2 |

**Row 2** — leaf labels under the score group only (fixed + name columns have no second row):

| *(spanned)* | *(spanned)* | *(spanned)* | *(spanned)* | *(spanned)* | *(spanned)* | … | *(spanned)* | Reviewer 1 | Reviewer 2 | … | Reviewer N | *(spanned)* |

### Body row (per student)

| Reg no | Student | Attendance chip | Status chip | **Panel reviewer name** in slot 1 | … slot N | **Overall %** slot 1 | … slot N | **Weighted review score** |
|--------|---------|-----------------|-------------|-----------------------------------|----------|----------------------|----------|---------------------------|
| `reg_no` | `name` | present/absent | not_started / draft / frozen / locked | e.g. `Alice Chen` or `—` | … | e.g. `87.50` (draft styling if in progress) | … | from `calculate_review_score()` (submitted only) |

### ASCII wireframe (N = 3 reviewer slots)

```
┌────────┬─────────┬────────────┬────────┬────────────┬────────────┬────────────┬──────────────────────────────────────┬─────────────────────────┐
│ Reg no │ Student │ Attendance │ Status │ Reviewer 1 │ Reviewer 2 │ Reviewer 3 │      Reviewer overall (colSpan=3)    │ Weighted review score   │
├────────┼─────────┼────────────┼────────┼────────────┼────────────┼────────────┼──────────┬──────────┬──────────┼─────────────────────────┤
│        │         │            │        │  (names)   │            │            │ Rev 1    │ Rev 2    │ Rev 3    │                         │
├────────┼─────────┼────────────┼────────┼────────────┼────────────┼────────────┼──────────┼──────────┼──────────┼─────────────────────────┤
│ S001   │ Pat Lee │ Present    │ Draft  │ Alice      │ Bob        │ —          │  85.00   │  90.50   │    —     │         87.75           │
└────────┴─────────┴────────────┴────────┴────────────┴────────────┴────────────┴──────────┴──────────┴──────────┴─────────────────────────┘
```

### Contrast with Rubric marks (same shell, different score grid)

| Area | Rubric marks (`ReportsMarksTable`) | Overall scores (this story) |
|------|-----------------------------------|-----------------------------|
| Score groups | One group **per rubric criterion**; each group has **N** leaves (reviewer slots) | **One** group **Reviewer overall** with **N** leaves |
| Leaf cell | Criterion score for `(student, criterion, reviewer in slot)` | `calculate_reviewer_total()` for reviewer in slot |
| Row 2 leaf label | Criterion label (rubric-first) or rubric name (reviewer-first) | `Reviewer 1` … `Reviewer N` |
| Layout toggle | Rubric-first + Reviewer-first (7.6 / 7.8) | **Rubric-first only** (no criterion dimension; hide or disable Reviewer-first) |
| Weighted column | Same: `review_score` from scores-matrix, submitted only | Same |

### What we are **not** building

- No per-criterion columns on Overall scores.
- No union of all reviewers in the project as table columns (fix same as 7.8 — use `max_panel_reviewer_slots`).
- No change to legacy **Downloads** tab `ReportCard` exports (optional follow-up: dedicated `scores-matrix/download`).

## Acceptance Criteria

### 1. Table structure and UX parity

1. **Given** the Reports page **Overall scores** tab with a confirmed review selected  
   **When** the live table renders  
   **Then** it uses the **two-row header** pattern in the wireframe above (fixed cols + reviewer name slots + **Reviewer overall** group + weighted score)  
   **And** sticky header, horizontal scroll, sticky **Reg no** column, and row hover match `ReportsMarksTable` / `tableStyles`.

2. **Given** a student row  
   **When** cells render  
   **Then** reviewer **name** columns show that student’s panel reviewer for each slot (`panel_reviewers` / same mapping as marks-grid)  
   **And** **overall** score columns show Level 1 reviewer total for the reviewer in that slot (not criterion scores).

3. **Given** a slot has no assigned reviewer for that student’s panel  
   **When** name or score cells render  
   **Then** show **—**.

4. **And** **Attendance** and **Status** columns always show (parity with rubric marks tab — not optional like current `ReportsScoresTable`).

5. **And** **Weighted review score** remains server-computed from **submitted** marks only (`ScoreService::calculate_review_score()`); footer note matches rubric marks tone.

### 2. Draft / in-progress reviewer totals

6. **Given** a reviewer has entered marks but not frozen/submitted all criteria  
   **When** an overall total can be computed including in-progress marks  
   **Then** display the numeric total with **draft/muted** styling (reuse `reviewer_total_cell_for_panel_report` pattern from `ReportsViewService` / panel PDF)  
   **And** weighted review score column still uses submitted-only aggregate.

7. **Given** no marks for a reviewer on that student  
   **When** the overall cell renders  
   **Then** show **—**.

### 3. Panel-scoped columns (fix wide reviewer matrix)

8. **Given** a review with multiple panels of different sizes  
   **When** columns are built  
   **Then** use `max_panel_reviewer_slots` from marks-grid (or equivalent on scores-matrix) — **not** `scores_matrix.reviewers` union across the project.

9. **Given** slot index `k`  
   **When** resolving scores  
   **Then** map slot → `panel_reviewers[k].user_id` → `calculate_reviewer_total()` (same as today’s per-reviewer math, different column layout).

### 4. Sort and export

10. **Given** data loaded  
    **When** the coordinator clicks a sortable header (Reg no, Student, Attendance, Status, each reviewer **name** slot, each **overall** slot, Weighted review score)  
    **Then** rows sort **client-side** via shared sort rules (`compareSortValues`, nulls last on asc) — **no** extra REST round-trip.

11. **Given** the Overall scores toolbar  
    **When** export is used  
    **Then** **Download CSV** and **Download Excel** appear top-right (same placement as rubric marks)  
    **And** exported columns match on-screen table (two header rows for Excel).

12. **Excel:** add `GET .../scores-matrix/download?format=xlsx&sort_key=…&sort_dir=…` (permission `pr_view_reports`), mirroring `marks-grid/download` via `ExportService`.

13. **CSV:** client Blob from same row model as marks (`rowsToCsv` pattern — new util or shared helper).

14. **And** filename pattern: `{session}_{review}_scores.xlsx|csv`.

### 5. API contract

15. **Given** `GET .../scores-matrix`  
    **When** response is built  
    **Then** include per student: `attendance_status`, `mark_status`, `panel_id`, `panel_reviewers[]` (`user_id`, `name`, `slot_index`)  
    **And** top-level `max_panel_reviewer_slots`  
    **And** `reviewer_totals` keyed by slot **or** embed totals in `panel_reviewers` as `{ score, draft }` — document chosen shape; must support slot-based UI without global reviewer column explosion.

16. **And** existing consumers (`Reports.jsx` parallel fetch, panel head `scores_matrix_for_panel`) remain backward compatible (additive fields only).

### 6. Regression

17. **And** Rubric marks tab, coordinator lock banner, and Downloads catalog unchanged except shared fetch fields.  
18. **And** `RestReportsTest`, `npm run build`, and PHPUnit pass.

## Tasks / Subtasks

- [x] **API:** Extend `ReportsViewService::scores_matrix()` — `max_panel_reviewer_slots`, per-student panel roster, attendance/mark_status, draft-aware reviewer total cells (reuse `reviewer_total_cell_for_panel_report` logic)
- [x] **Utils:** `reportsScoresMatrixUtils.js` — `buildScoresColumns(maxSlots)`, `buildScoresRows(marksGrid|scoresMatrix)`, `sortScoresRows`, `scoresRowsToCsv` (or extend marks utils with `mode: 'overall'`)
- [x] **UI:** Refactor `ReportsScoresTable.jsx` → two-row header matrix OR new `ReportsOverallScoresTable.jsx` sharing sort/sticky patterns from `ReportsMarksTable`
- [x] **Page:** `Reports.jsx` — scores tab: sort state, export handlers, wire utils (reuse `marksGrid` for slots + `scoresMatrix` for totals)
- [x] **REST:** `scores-matrix/download` route + `ReportsViewService::scores_matrix_export()`
- [x] **Tests:** `RestReportsTest` scores-matrix shape + xlsx download; slot column count fixture (2-panel max reviewers)
- [x] Run `./vendor/bin/phpunit` and `npm run build`

## Dev Notes

### User request (source)

> Modify overall scores tab in reports. Follow similar to rubric marks. No rubric scores per reviewer. Columns from rubric marks structure except review marks are **only overall marks for each reviewer**. Show table structure.

### Current behaviour (replace)

`ReportsScoresTable` today:

- Single header row.
- Columns: Reg no, Student, **one column per reviewer in project union** (`scores_matrix.reviewers`), Review score.
- No Attendance/Status, no panel slots, no sort, no export, no two-row header.

```69:93:src/coordinator/components/ReportsScoresTable.jsx
				<table className="w-max min-w-full text-sm">
					<thead className="sticky top-0 z-10 bg-surface shadow-sm">
						<tr className="border-b border-border text-left text-muted">
							<th ...>Reg no</th>
							<th ...>Student</th>
							{ showStatusColumn ? ( Attendance, Marks status ) : null }
							{ ( reviewers || [] ).map( ( reviewer ) => (
								<th key={ reviewer.user_id }>...</th>
							) ) }
							<th ...>Review score</th>
```

### Reuse map (do not reinvent)

| Asset | Reuse |
|-------|--------|
| `reportsMarksMatrixUtils.js` | `compareSortValues`, sticky patterns, CSV quoting — extend or sibling module |
| `ReportsMarksTable.jsx` | `RegNoStickySortableTh`, `SortIndicator`, `PlainSortableTh`, export toolbar layout |
| `ReportsViewService::marks_grid()` | Source of `max_panel_reviewer_slots`, `panel_reviewers` (scores tab already loads marks-grid in parallel) |
| `reviewer_total_cell_for_panel_report()` | Draft vs submitted overall per reviewer |
| `marks_grid_export()` / `ExportService` | Excel two-row headers + merge plan |

### Data flow (recommended)

```text
Reports.jsx (tab=scores)
  ├─ GET marks-grid  → max_panel_reviewer_slots, panel_reviewers, attendance, mark_status
  └─ GET scores-matrix → review_score per student, reviewer totals (extended)
        ↓
  reportsScoresMatrixUtils.buildColumns / buildRows / sortRows
        ↓
  ReportsOverallScoresTable (or refactored ReportsScoresTable)
```

Client may build rows **without** new REST fields by merging marks-grid roster + scores-matrix totals, but **server** should still expose slot-safe totals for export and panel-head parity — prefer extending `scores_matrix()` either way.

### Score semantics

| Column | Source | Submitted only? |
|--------|--------|-----------------|
| Reviewer overall (per slot) | `calculate_reviewer_total(..., submitted_only: false)` when draft marks exist; frozen → submitted | Display draft styling when any contributing mark not submitted/frozen |
| Weighted review score | `calculate_review_score()` | **Yes** (unchanged) |

### Panel head / PDF

`scores_matrix_for_panel()` and `PanelReportPage` / PDF (11-1) already use panel-scoped reviewers — **do not break**. Coordinator tab catches up to the same slot model as marks-grid.

### Out of scope

- Rubric marks layout toggle on Overall scores (Reviewer-first with one leaf is redundant).
- Changing seven legacy catalog exports.
- Combined-scores / multi-review reports (different report types in Downloads tab).

### Previous story intelligence

- **7.6** — matrix sort/export patterns; weighted column from scores-matrix.
- **7.8** — panel slots, `max_panel_reviewer_slots`, draft criterion scores; **apply same slot rules to overall totals**.
- **7.7** — binary export REST delivery for xlsx.
- **11-1** — panel `ReportsScoresTable` with `showStatusColumn`; after this story, consider aligning panel head live table to new matrix component.

### Project Structure Notes

- PHP: `includes/services/ReportsViewService.php`, `includes/rest/class-rest-reports.php`
- JS: `src/coordinator/components/ReportsScoresTable.jsx` (refactor or split), new utils beside `reportsMarksMatrixUtils.js`
- Tests: `tests/RestReportsTest.php`

### References

- [Source: src/coordinator/components/ReportsMarksTable.jsx — two-row thead]
- [Source: src/coordinator/components/reportsMarksMatrixUtils.js — slot columns]
- [Source: includes/services/ReportsViewService.php — scores_matrix, marks_grid, reviewer_total_cell_for_panel_report]
- [Source: _bmad-output/implementation/7-6-reports-marks-matrix-layout-sort-export.md]
- [Source: _bmad-output/implementation/7-8-reports-marks-draft-scores-panel-reviewer-slots.md]
- [Source: includes/services/ScoreService.php — calculate_reviewer_total, calculate_review_score]

## Dev Agent Record

### Agent Model Used

Composer (Cursor agent)

### Debug Log References

### Completion Notes List

- Extended `scores_matrix()` with panel slots, attendance/status, draft-aware `panel_reviewers[].total`; kept `reviewers` + `reviewer_totals` for panel-head backward compatibility.
- Added `ReportsOverallScoresTable` (two-row header, sort, CSV/Excel export) and `reportsScoresMatrixUtils.js`; panel head still uses legacy `ReportsScoresTable`.
- REST `scores-matrix/download` + `scores_matrix_export()` with two-row Excel layout matching on-screen matrix.
- PHPUnit 253 OK; `npm run build` OK.

### File List

- includes/services/ReportsViewService.php
- includes/rest/class-rest-reports.php
- src/coordinator/components/reportsScoresMatrixUtils.js
- src/coordinator/components/ReportsOverallScoresTable.jsx
- src/coordinator/pages/Reports.jsx
- tests/RestReportsTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php

### Change Log

- 2026-05-17: Story 7.9 — Overall scores tab panel-slot matrix parity with rubric marks (API, UI, export, tests).
