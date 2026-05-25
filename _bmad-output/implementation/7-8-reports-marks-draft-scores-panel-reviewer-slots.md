# Story 7.8: Reports marks matrix — show draft scores and panel-scoped reviewer slots

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want the Rubric marks report to show every score that has been entered (including draft marks) and to use a fixed number of reviewer columns per rubric based on panel size (with each student’s reviewers named in those columns),
So that I can monitor in-progress marking without a wide matrix of every reviewer in the project and still see partial scores before reviewers freeze.

## Acceptance Criteria

### 1. Show draft / in-progress criterion scores

1. **Given** the Rubric marks tab with marks loaded for a review  
   **When** a student has a `pr_marks` row with a numeric `score` and `status = draft` (or `submitted`)  
   **Then** the matrix cell displays that **numeric score** (same formatting as submitted: `tabular-nums`, max 2 decimal places)  
   **And** the cell is visually distinct from submitted/frozen marks (e.g. muted text, and/or a small `Draft` label — reuse tone from `MarkCell` / `StatusChip`, not a blank or `—`).

2. **Given** a mark row exists with `status = draft` and `score = null` (e.g. absent student)  
   **When** the cell renders  
   **Then** show **—** or muted `draft` (no invented score).

3. **Given** no mark row for `(student, criterion, reviewer)`  
   **When** the cell renders  
   **Then** show **—**.

4. **And** `FlaggedMarkChip` still appears when `flagged` is true, regardless of draft vs submitted.

5. **And** the **Weighted review score** column remains **server-computed from submitted marks only** (`ScoreService::calculate_review_score`, `submitted_only = true`) — unchanged from 7.6; footer note stays accurate.

6. **And** sorting on score columns uses the **numeric score** when present (draft or submitted); null/missing still sorts last on asc (7.6 rules).

### 2. Fixed reviewer column count per rubric (panel-scoped)

7. **Given** a review with panels and per-review assignments (`pr_review_student_panels`, `pr_review_panel_reviewers`)  
   **When** the marks matrix columns are built  
   **Then** for **Rubric-first** layout, each rubric criterion group has exactly **`max_panel_reviewer_slots`** leaf columns, where:

   ```text
   max_panel_reviewer_slots = max over panels P of count(reviewers on P for this review_id)
   ```

   **And** panels with fewer reviewers leave trailing slot columns empty (—) for students on that panel.

8. **Given** a student enrolled on panel `P` with reviewers `[R1, R2, …]` (stable order: `user_id` ascending, same as `ReviewAssignmentRepository::list_panel_reviewers_for_panel`)  
   **When** building that student’s row  
   **Then** slot `0` maps to `R1`, slot `1` to `R2`, etc.  
   **And** score lookup uses `(criterion_id, reviewer_user_id)` for the reviewer in that slot.

9. **Given** Rubric-first layout  
   **When** the second header row renders  
   **Then** leaf labels are **generic slot labels** (`Reviewer 1`, `Reviewer 2`, …) **or** empty — **not** the union of all reviewer names across the project (which today creates excess columns).

10. **Given** each data cell in a reviewer slot column  
    **When** the student has a reviewer assigned in that slot  
    **Then** the cell shows the **reviewer display name** (from `reviewer_display_name` / panel roster) **and** the score (per AC §1) — name may be a second line, subtitle, or `title` tooltip; pick one pattern and use consistently in UI + export.

11. **Given** a student with no panel assignment (`panel_id` null / missing)  
    **When** the row renders  
    **Then** all reviewer slot cells show **—**  
    **And** `mark_status` / attendance behaviour unchanged.

### 3. Reviewer-first layout (narrow scope)

12. **Given** **Reviewer-first** layout toggle  
    **When** shipped in this story  
    **Then** either:
    - **(Preferred)** hide or disable Reviewer-first with helper text: “Use Rubric-first for panel reviewer columns”, **or**
    - rebuild Reviewer-first using the same slot model (groups = `Reviewer 1…N` with rubric leaves underneath) — only if trivial; **do not** block AC §1–§2 on full Reviewer-first parity.

    Default segmented control remains **Rubric-first**.

### 4. API contract (marks-grid)

13. **Given** `GET .../marks-grid`  
    **When** response is built  
    **Then** include top-level `max_panel_reviewer_slots` (int ≥ 0)  
    **And** each student includes `panel_id` (int|null) and `panel_reviewers`: ordered list `{ user_id, name, slot_index }` where `slot_index` is 0-based within that panel’s reviewer list.

14. **And** existing fields (`criteria`, `marks`, `mark_status`, `attendance_status`, `coordinator_marks_locked`) remain backward compatible for clients that ignore new keys.

### 5. Export parity

15. **Given** CSV or Excel export from 7.6 (`marks-grid/download`, client CSV)  
    **When** export runs after this story  
    **Then** column count and slot mapping match on-screen Rubric-first matrix  
    **And** draft scores export as numeric values (not the literal string `draft` when a score exists)  
    **And** cells with score null export empty or `draft` per existing export rules for missing scores.

16. **And** `ReportsViewService::marks_grid_export()` / `build_export_columns()` / `score_cell_from_entry()` stay in sync with client `reportsMarksMatrixUtils.js` (duplicate logic today — update **both**).

### 6. Regression

17. **And** `RestReportsTest`, marks-grid smoke, export layout tests, and `npm run build` + PHPUnit pass.  
18. **And** Overall scores tab, legacy `ReportCard` downloads, and coordinator lock banner unchanged.

## Tasks / Subtasks

- [x] **API:** Extend `ReportsViewService::marks_grid()` — compute `max_panel_reviewer_slots`, attach `panel_id` + `panel_reviewers` per student via `ReviewAssignmentRepository`
- [x] **Utils:** Refactor `reportsMarksMatrixUtils.js` — slot-based `leafKey(criterionId, slotIndex)`, `buildColumns` uses `max_panel_reviewer_slots`, `buildRows` maps panel reviewers to slots; update `getScore` to return score when `score != null` regardless of draft status
- [x] **UI:** `ReportsMarksTable.jsx` — cell shows name + score; draft styling when `status !== 'submitted'`
- [x] **Page:** `Reports.jsx` — pass `max_panel_reviewer_slots` / panel reviewer data into utils (stop using global `extractReviewers` for column width)
- [x] **Export PHP:** Mirror slot columns + draft score display in `ReportsViewService` export builders
- [x] **Tests:** `RestReportsTest` asserts new marks-grid fields; util/export fixture with 2 panels (2 vs 3 reviewers) and draft marks with scores
- [x] Run `./vendor/bin/phpunit` and `npm run build`

## Dev Notes

### User request (source)

1. Reports table should show **available marks even when student/mark status is draft** (today draft scores are hidden — cells show muted `draft` with no number).
2. **Too many reviewer columns** — headers list every reviewer name in the review; desired width = **max reviewers on any panel**, not union of all reviewers.
3. **Per student**, reviewer columns should carry **that student’s panel reviewer name** (panel-scoped), not global reviewer columns.

### Root cause (current 7.6 behaviour)

| Area | Current | Problem |
|------|---------|---------|
| `getScore()` / `score_cell_from_entry()` | Returns `draft: true`, `score: null` when `status !== 'submitted'` | Hides numeric draft scores |
| `extractReviewers()` | Union of all reviewers from `scores-matrix.reviewers` | Column count = all reviewers on review |
| `buildColumns()` rubric-first | Leaf label = global `reviewer.name` | Wrong headers for panel-scoped marking |
| Leaf key | `c{criterionId}_r{reviewerUserId}` | Works for lookup but drives one column per global reviewer |

### Target column model (Rubric-first)

```text
| Reg no | Student | Attendance | Status | [Rubric A × N slots] | [Rubric B × N slots] | Weighted review score |
|        |         |            |        | R1   R2   R3         | R1   R2   R3         |                       |
```

- `N = max_panel_reviewer_slots` from server.
- For student S on panel P: slot k → k-th reviewer on P (by `user_id` order).
- Cell content: `{ reviewerName, score, isDraft }`.

**Leaf key (new):** `c${criterionId}_s${slotIndex}` (do not embed `user_id` in column key — mapping is per row).

### Server: computing max slots

```php
// ReportsViewService::marks_grid — illustrative
$max_slots = 0;
foreach ($this->panels->list_by_session($session_id) as $panel) {
    $panel_id = (int) ($panel['id'] ?? 0);
    $count = count($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id));
    $max_slots = max($max_slots, $count);
}
```

Build `panel_id` map from `list_student_panels($review_id)`.

For each student, `panel_reviewers`:

```php
foreach ($this->assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $i => $row) {
    $payload[] = [
        'user_id' => (int) $row['user_id'],
        'name' => $this->reviewer_display_name($review_id, (int) $row['user_id']),
        'slot_index' => $i,
    ];
}
```

### Client: getScore change

```javascript
// After change — show score when present; flag draft separately
if (!hit) return null;
const isDraft = hit.status !== 'submitted';
if (hit.score == null) {
  return { score: null, draft: true, flagged: Boolean(hit.flagged), entry: hit };
}
return {
  score: Number(hit.score),
  draft: isDraft,
  flagged: Boolean(hit.flagged),
  reviewerName: hit.reviewer_name, // optional if not passed from row builder
  entry: hit,
};
```

Row builder sets `cells[leafKey(criterionId, slot)]` using `panel_reviewers[slot].user_id`.

### MarkCell / export display rules

| Condition | UI | CSV/XLSX |
|-----------|-----|----------|
| score present, draft | Show number + draft styling | Export number |
| score present, submitted | Show number + flag chip | Export number |
| score null, draft row | `draft` or — | `draft` or empty |
| no row | — | empty |

Optional: include reviewer name in export as `Name: 7.5` or extra sub-header row — **minimum**: numeric score column; name in cell text or `title` on web only is acceptable if export documents reviewer in adjacent convention.

### Files to touch

| File | Change |
|------|--------|
| `includes/services/ReportsViewService.php` | `marks_grid` payload; export column/row builders; `score_cell_from_entry` |
| `src/coordinator/components/reportsMarksMatrixUtils.js` | Slot columns, draft scores, row mapping |
| `src/coordinator/components/ReportsMarksTable.jsx` | Cell UI (name + score + draft) |
| `src/coordinator/pages/Reports.jsx` | Wire `max_panel_reviewer_slots`, drop global reviewer column list for rubric layout |
| `tests/RestReportsTest.php` | marks-grid structure + draft score in export |
| `build/coordinator.js` | via `npm run build` |

### Previous story intelligence (7.6, 7.7)

- **7.6** introduced dual layout, sort, export — this story **narrows** Rubric-first column semantics; keep sort/export plumbing.
- **7.7** (ready-for-dev) fixes binary REST export delivery — **no conflict**; implement 7.8 after or in parallel; if export route changes, retest xlsx download.
- **7.5** loaded marks-grid + scores-matrix together — keep parallel fetch; weighted column still from `scores-matrix`.
- **3.11** per-review panel assignments are authoritative — use `pr_review_panel_reviewers`, not session-wide `pr_panel_reviewers` alone.
- **5.6** freeze → `submitted`; draft marks are pre-freeze — coordinators need visibility without treating them as final.

### Anti-patterns (do not)

- Do **not** change `ScoreService` weighted review score to include draft marks.
- Do **not** add one column per unique `reviewer_user_id` across the whole review (regression to current bug).
- Do **not** add SheetJS / client xlsx libraries.
- Do **not** break leaf-key sort for export without updating `sort_key` query param handling on download route.

### Testing checklist

1. Panel A (2 reviewers), Panel B (3 reviewers) → rubric groups have **3** leaf columns; Panel A students have — in slot 3.
2. Student with draft score 6.5 on criterion → cell shows **6.5** (not blank `draft`).
3. Student frozen/submitted → cell shows score without draft styling.
4. Reviewer name in cell matches that student’s panel roster, not another panel’s reviewer.
5. Sort by score column orders draft scores numerically.
6. CSV/Excel column count matches UI; draft numeric scores in file.
7. Weighted review score unchanged when only draft marks exist (still 0 or — per server rules).

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Extended `marks_grid` with `max_panel_reviewer_slots`, per-student `panel_id` / `panel_reviewers`; mark status now scoped to each student's panel reviewers.
- Rubric-first matrix uses slot columns (`Reviewer 1…N`) with per-cell reviewer name + numeric draft/submitted scores; Reviewer-first toggle disabled with helper text.
- Export (CSV/XLSX) uses the same slot column model and exports numeric draft scores.
- Panel head report (`scores_matrix_for_panel`): reviewer totals include in-progress draft totals with `draft` flag; `ReportsScoresTable` + PDF show draft styling; weighted review score unchanged (submitted only).

### File List

- includes/services/ReportsViewService.php
- includes/services/PanelReportPdfService.php
- src/coordinator/components/reportsMarksMatrixUtils.js
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/components/ReportsScoresTable.jsx
- src/coordinator/pages/Reports.jsx
- src/reviewer/pages/PanelReportPage.jsx
- tests/RestReportsTest.php
- build/coordinator.js
- build/coordinator.css
- build/reviewer.js
- build/reviewer.css

### Change Log

- 2026-05-17: Story 7.8 — draft marks visibility, panel-scoped reviewer slots, export parity, panel head draft totals.

## References

- [Source: _bmad-output/implementation/7-6-reports-marks-matrix-layout-sort-export.md]
- [Source: _bmad-output/implementation/7-5-reports-page-live-views-and-lock.md]
- [Source: includes/services/ReportsViewService.php — marks_grid, marks_grid_export, score_cell_from_entry]
- [Source: src/coordinator/components/reportsMarksMatrixUtils.js — getScore, buildColumns, extractReviewers]
- [Source: includes/repositories/ReviewAssignmentRepository.php — list_panel_reviewers_for_panel, list_student_panels]
- [Source: _bmad-output/implementation/3-11-per-review-assignments-marking-active.md]
