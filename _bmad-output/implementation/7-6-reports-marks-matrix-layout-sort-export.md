# Story 7.6: Reports rubric matrix — dual layouts, weighted final score, sort, export

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want the Rubric marks report for a chosen review to show reviewer and rubric scores in a sortable matrix with a weighted final score and one-click Excel/CSV export matching what I see on screen,
So that I can scan committee data by reviewer or by rubric, rank students by any column, and download the same layout without reformatting spreadsheets.

## Acceptance Criteria

### 1. Layout options (both required — segmented control)

1. **Given** the Reports page **Rubric marks** tab with a confirmed review selected  
   **When** the live marks table renders  
   **Then** a segmented control offers **two layouts** (persist choice in component state for the session; default **Rubric-first** to match current mental model):

   | ID | UI label | Subtitle (helper text under control) | Column hierarchy |
   |----|----------|--------------------------------------|------------------|
   | `reviewer` | **Reviewer-first** | Reviewers → rubric marks | Level 0: reviewer name · Level 1: each rubric criterion for that reviewer |
   | `rubric` | **Rubric-first** | Rubrics → reviewer scores | Level 0: rubric criterion label · Level 1: each reviewer for that criterion |

2. **Given** either layout  
   **When** the table header renders  
   **Then** `<thead>` uses **two header rows** with `colSpan` group cells on row 1 and leaf column labels on row 2  
   **And** sticky header behaviour matches `ReportsMarksTable` / Progress tables (`sticky top-0`, `bg-surface`, horizontal scroll).

3. **Given** a rubric criterion label longer than **32 characters**  
   **When** shown in a header cell  
   **Then** text is truncated with ellipsis (`truncate` / `max-w-*`)  
   **And** the full label is available on hover via native `title` attribute (no tooltip library).

4. **Given** a mark cell  
   **When** `status !== 'submitted'` or score is null  
   **Then** display **—** or muted `draft` (same rules as current `MarkCell`)  
   **And** `FlaggedMarkChip` when `flagged` on submitted marks.

### 2. Row model and weighted final score

5. **Given** marks grid and scores matrix loaded for the review (`Reports.jsx` already fetches both in parallel)  
   **When** building the matrix in React  
   **Then** rows are enrolled students (`reg_no`, `name`, `student_id`)  
   **And** each leaf column resolves to one `(reviewer_user_id, criterion_id)` score from `marks-grid` payload  
   **And** a trailing column **Weighted review score** shows `student.review_score` from `scores-matrix` (server `ScoreService::calculate_review_score()` — Level 2 weighted % across reviewers; **not** client-calculated).

6. **And** the footer note on the marks tab states totals are server-computed from submitted marks only (reuse tone from `ReportsScoresTable`).

### 3. Client state, sorting (all columns)

7. **Given** normalized matrix data in React  
   **When** the coordinator clicks any sortable column header (Reg no, Student, each score leaf column, Weighted review score)  
   **Then** rows re-order **client-side** via `useState` for `sortKey` + `sortDirection` (`asc` | `desc`) and `useMemo` for sorted rows — **no extra REST round-trip** for sort.

8. **And** clicking the same column toggles direction; clicking a different column sorts by that column ascending first.

9. **And** sort behaviour:
   - Reg no / Student: locale-aware string compare on `reg_no` / `name`.
   - Numeric score columns: numeric compare; null / draft / missing sorts **last** in ascending, **first** in descending (document in util).
   - Header shows sort affordance (▲/▼ or `aria-sort`) on active column only.

10. **And** initial sort remains **reg no ascending** (parity with enrolled student order from API).

### 4. Export — top right, matches on-screen matrix

11. **Given** the Rubric marks tab with data loaded  
    **When** the coordinator uses the toolbar  
    **Then** **Download Excel** and **Download CSV** appear **top right** of the marks panel (same row as layout segmented control or directly above table — align with review selector row on `sm+` breakpoints).

12. **When** export runs  
    **Then** the file reflects the **current layout** (`reviewer` | `rubric`), **current sort order**, and **same columns** as the visible table (including **Weighted review score** column)  
    **And** buttons disable while generating; errors use `Notice` pattern.

13. **CSV:** build from the same `useMemo` row/column model in the browser (Blob download) — **no new npm dependency**.

14. **Excel:** add read-only REST  
    `GET /project-reviews/v1/sessions/{session_id}/reviews/{review_id}/marks-grid/download?format=xlsx&layout=reviewer|rubric&sort_key=…&sort_dir=asc|desc`  
    **Or** POST body with sort/layout if query string too long — permission `pr_view_reports`.  
    Server builds two header rows + data rows + horizontal `merge_plan` for group headers via `ExportService::to_xlsx()` (reuse PhpSpreadsheet path from 7.1; **do not** add SheetJS to frontend).

15. **And** filename pattern: `{session-slug-or-id}_{review-label-or-id}_marks_{layout}.xlsx|csv`.

16. **Out of scope:** changing the seven legacy `ReportCard` catalog exports; coordinator lock UX; Overall scores tab layout (may reuse sort/export patterns later — not required in this story).

### 5. Regression

17. **And** existing `GET marks-grid`, `GET scores-matrix`, lock-marks, and `RestReportsTest` catalog downloads still pass.  
18. **And** `npm run build` + PHPUnit green; add tests for new export route and matrix util edge cases where practical.

## Tasks / Subtasks

- [x] **Utils:** `reportsMarksMatrixUtils.js` — `buildColumns(layout, criteria, reviewers)`, `buildRows(students, marks, reviewScores)`, `sortRows(rows, sortKey, sortDir)`, `rowsToCsv(rows, columns)`, truncate helper
- [x] **UI:** Refactor `ReportsMarksTable.jsx` — layout prop, two-row `<thead>`, sortable headers, weighted score column, truncation + `title`
- [x] **Page:** `Reports.jsx` — layout + sort state, pass `scoresMatrix` into marks table, export buttons + handlers
- [x] **REST:** `marks-grid/download` route; `ReportsViewService::marks_grid_export()` or dedicated builder mirroring client column order
- [x] **Tests:** `RestReportsTest` xlsx download with layout param; JS util unit tests if project has pattern (optional PHPUnit-only acceptable)
- [x] Run `./vendor/bin/phpunit` and `npm run build`

## Dev Notes

### User request (source)

Enhance Reports **Rubric marks** tab for selected review:

- Multi-level column headers including reviewers (today reviewers are stacked inside each rubric cell).
- Two layout options with clear titles (see AC §1).
- Truncate long rubric labels; full text on hover (`title`).
- **Weighted review score** column (weighted average per `ScoreService`).
- Sort all columns in React (`useState` + `useMemo`).
- Top-right **Download Excel** + **Download CSV** matching current view.

### Layout wireframes (column headers)

**Reviewer-first** (`layout=reviewer`):

```
| Reg no | Student | [Reviewer A — colspan=n]     | [Reviewer B — colspan=n]     | Weighted review score |
|        |         | Rubric 1 | Rubric 2 | ...   | Rubric 1 | Rubric 2 | ...   |                       |
```

**Rubric-first** (`layout=rubric`):

```
| Reg no | Student | [Rubric 1 — colspan=m]           | [Rubric 2 — colspan=m]           | Weighted review score |
|        |         | Rev A | Rev B | ...            | Rev A | Rev B | ...                |                       |
```

### Data shaping (React — required pattern)

```javascript
// Reports.jsx — illustrative; implement in utils module
const [ layout, setLayout ] = useState( 'rubric' ); // 'reviewer' | 'rubric'
const [ sortKey, setSortKey ] = useState( 'reg_no' );
const [ sortDir, setSortDir ] = useState( 'asc' );

const reviewers = useMemo(
  () => extractReviewers( marksGrid ),
  [ marksGrid ]
);
const columns = useMemo(
  () => buildColumns( layout, marksGrid?.criteria, reviewers ),
  [ layout, marksGrid, reviewers ]
);
const rows = useMemo(
  () => buildRows( marksGrid?.students, scoresMatrix?.students ),
  [ marksGrid, scoresMatrix ]
);
const sortedRows = useMemo(
  () => sortRows( rows, columns, sortKey, sortDir ),
  [ rows, columns, sortKey, sortDir ]
);
```

**Extract reviewers:** union of `reviewer_user_id` from all `student.marks[*]` entries; stable sort by `reviewer_name` (match server order in `scores-matrix.reviewers` when present).

**Cell lookup:**

```javascript
function getScore( student, criterionId, reviewerUserId ) {
  const entries = student.marks?.[ String( criterionId ) ] ?? [];
  const hit = entries.find( ( e ) => e.reviewer_user_id === reviewerUserId );
  if ( ! hit || hit.status !== 'submitted' || hit.score == null ) return null;
  return hit.score;
}
```

**Review score map:** `scoresMatrix.students` → `{ [student_id]: review_score }`.

### Weighted review score (do not reimplement)

| Level | Method | Used for |
|-------|--------|----------|
| 1 | `calculate_reviewer_total()` | Leaf cells are raw criterion scores; reviewer column groups are not totals |
| 2 | `calculate_review_score()` | **Weighted review score** column |

Criterion weights apply inside reviewer total; reviewer weights apply inside review score (`includes/services/ScoreService.php`).

### API — existing payloads (no schema change required)

`GET .../marks-grid` and `GET .../scores-matrix` already return everything needed. **Do not** add per-column score fields server-side unless export builder needs a dedicated DTO.

Optional: include `criteria[].weight` in marks-grid if useful for tooltips — not required for AC.

### Export implementation notes

**CSV (client):** Map `sortedRows` + `columns` to header rows (flatten group + leaf into two CSV lines, or single line with composite headers like `Reviewer A / Design` — **prefer two header rows** for Excel parity).

**Excel (server):** Example row builder:

```php
// Row 0: Reg no | Student | Reviewer A (merged) | ... | Weighted review score
// Row 1: empty  | empty   | crit labels...      | ... | empty
// Row 2+: data
```

`merge_plan`: horizontal merges on row 1 for each group (`ExportService::merge_plan_for_columns` may need extension for **column-span** merges on a fixed row — check `ExportService` merge API; add helper `merge_plan_for_header_groups()` if needed).

Pass `sort_key` / `sort_dir` query params so export matches UI sort without re-fetching client state.

### Files to touch

| File | Change |
|------|--------|
| `src/coordinator/components/reportsMarksMatrixUtils.js` | **new** — column/row/sort/export helpers |
| `src/coordinator/components/ReportsMarksTable.jsx` | Two-row headers, sort, layout, final column |
| `src/coordinator/pages/Reports.jsx` | State, toolbar exports, wire scores into marks tab |
| `includes/services/ReportsViewService.php` | `marks_grid_export()` |
| `includes/rest/class-rest-reports.php` | download route |
| `tests/RestReportsTest.php` | layout + xlsx smoke |
| `build/coordinator.js` | via `npm run build` |

### UX references

- Tables: sticky header, `tabular-nums`, horizontal scroll (`ux-design-specification.md` — Data tables).
- Downloads: paired Excel/CSV (UX-DR31) — this story adds **contextual** export for live matrix; keep `ReportCard` grid on Downloads tab unchanged.
- Truncation + `title`: accessibility-friendly; no new tooltip dependency.

### Previous story intelligence (7.5)

- `Reports.jsx` loads `marks-grid` + `scores-matrix` together — reuse; do not duplicate fetch per sort.
- `ReportsMarksTable` `MarkCell` stacking pattern is **replaced** by leaf columns per layout — delete stacking UI when matrix ships.
- `coordinator_marks_locked` banner unchanged.
- Overall scores tab (`ReportsScoresTable`) unchanged unless time permits sort-only follow-up.

### Anti-patterns (do not)

- Do **not** compute weighted review score in the browser (drift from `ScoreService`).
- Do **not** add `xlsx` / `exceljs` to `package.json` for coordinator bundle.
- Do **not** remove or break seven legacy report downloads.
- Do **not** require layout choice in REST marks-grid JSON — layout is UI/export concern.
- Do **not** implement server-side sort unless export endpoint needs it — list endpoint stays reg-no ordered.

### Testing checklist

1. Toggle **Reviewer-first** / **Rubric-first** — column groups swap; scores unchanged.
2. Long rubric label truncates; hover shows full `title`.
3. Sort every column asc/desc; draft/nulls sort last on asc.
4. Weighted review score matches Progress / `ScoreServiceTest` fixture for same student.
5. CSV download column order matches screen; sorted order preserved.
6. Excel download opens with two header rows and merged group cells.
7. Lock marks + Downloads tab regression.

## Dev Agent Record

### Agent Model Used

Composer (Cursor agent)

### Debug Log References

### Completion Notes List

- Added `reportsMarksMatrixUtils.js` for column/row shaping, client-side sort (nulls last on asc), and CSV export with two header rows.
- Refactored `ReportsMarksTable` with Rubric-first / Reviewer-first segmented control, two-row grouped headers, sortable columns, weighted review score from server, truncation + `title` on long labels.
- Wired `Reports.jsx` with layout/sort state, parallel marks-grid + scores-matrix data, top-right CSV (client Blob) and Excel (REST) downloads matching current view.
- Added `GET .../marks-grid/download` with `layout`, `sort_key`, `sort_dir`; `ReportsViewService::marks_grid_export()` mirrors UI matrix; extended `ExportService` for horizontal header merges and multi-row header styling.
- PHPUnit: 187 tests OK; `ExportServiceTest` horizontal merge; `RestReportsTest` xlsx smoke + invalid layout.

### File List

- src/coordinator/components/reportsMarksMatrixUtils.js (new)
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/pages/Reports.jsx
- includes/services/ReportsViewService.php
- includes/services/ExportService.php
- includes/rest/class-rest-reports.php
- tests/RestReportsTest.php
- tests/ExportServiceTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css

### Change Log

- 2026-05-17: Story 7.6 — reports marks matrix dual layout, sort, weighted score column, CSV/Excel export.

## References

- [Source: _bmad-output/implementation/7-5-reports-page-live-views-and-lock.md]
- [Source: _bmad-output/implementation/7-3-report-download-ui.md]
- [Source: includes/services/ReportsViewService.php — marks_grid, scores_matrix]
- [Source: includes/services/ScoreService.php — calculate_review_score, calculate_reviewer_total]
- [Source: src/coordinator/components/ReportsMarksTable.jsx — current criterion×stacked reviewers]
- [Source: includes/services/ExportService.php — CSV/XLSX + merge_plan]
