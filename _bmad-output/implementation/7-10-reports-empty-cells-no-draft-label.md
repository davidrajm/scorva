# Story 7.10: Reports — empty score cells (no “draft” for absent / missing marks)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want report score cells to stay **blank** whenever there is no numeric score (including absent students),
So that exports and on-screen matrices do not show the word “draft” in place of a mark.

## Acceptance Criteria

### 1. Rubric marks tab — score cells only

1. **Given** the Reports **Rubric marks** live table or a CSV/Excel export from it  
   **When** a student has `attendance_status = absent` for that review  
   **Then** every criterion × reviewer **score** cell is **empty** on screen (`—` or blank) and **empty** in export (no literal `draft`, no `Draft` label in the score cell).

2. **Given** a present student with no mark row, or a mark row with `score = null` and no numeric value  
   **When** the score cell renders  
   **Then** show **—** on screen and **empty** in export (same as AC §1).

3. **Given** a present student with a numeric score and `status = draft` (in-progress marking)  
   **When** the score cell renders  
   **Then** show the **numeric score** with existing muted/draft **styling** (7.8 behaviour)  
   **And** do **not** show the word “draft” inside the score cell.

4. **And** `FlaggedMarkChip`, reviewer name columns, Attendance column, and **Weighted review score** behaviour unchanged.

5. **And** **Status** column (`mark_status` chip: Not started / Draft / In progress / Frozen / Locked) is **out of scope** for this story — only **score** columns change.

### 2. Overall scores tab — reviewer total cells

6. **Given** the Reports **Overall scores** tab (panel-slot matrix, 7.9)  
   **When** the student is **absent**  
   **Then** each reviewer **overall** score cell is **—** / empty (not `0.00`, not `draft`, not a Draft chip in the score cell).

7. **Given** a present student with in-progress marks and a computable reviewer total  
   **When** the overall cell renders  
   **Then** show the numeric total with muted styling when `draft: true` (7.8/7.9)  
   **And** no literal “draft” text in the cell.

8. **Given** no marks activity for that reviewer on that student  
   **When** the overall cell renders  
   **Then** **—** / empty.

### 3. Export parity (CSV, XLSX, client CSV)

9. **Given** marks-grid or scores-matrix export (`ReportsViewService` xlsx + `rowsToCsv` client path)  
   **When** a score cell has no numeric value (absent, null score, or no row)  
   **Then** the exported cell is **empty** (not the string `draft`).

10. **Given** a score cell has a numeric value (draft or submitted)  
    **When** export runs  
    **Then** export the **number** only (no `draft` suffix).

### 4. API / server shaping (recommended)

11. **Given** `GET .../marks-grid` builds per-cell data for export  
    **When** `attendance_status = absent` for that student  
    **Then** `score_cell_from_entry()` returns **`null`** (not `{ score: null, draft: true }`).

12. **Given** `GET .../scores-matrix` builds reviewer totals  
    **When** `attendance_status = absent`  
    **Then** `reviewer_total_cell_for_panel_report()` returns **`null`** (not `{ score: 0, draft: true }` from empty draft mark rows).

13. **And** marks-grid still returns underlying `pr_marks` rows for absent students if needed for audit; only **report presentation** treats them as empty.

### 5. Regression

14. **And** present students with draft numeric scores (7.8) still display and export those numbers.  
15. **And** weighted review score remains **submitted-only** (unchanged).  
16. **And** panel-head PDF (`PanelReportPdfService`) already shows **—** for absent — no regression.  
17. **And** `RestReportsTest` + export fixtures updated; `npm run build` + PHPUnit pass.

## Tasks / Subtasks

- [x] **PHP:** `ReportsViewService::score_cell_from_entry()` — if student absent → `null`; if `score === null` → `null` (drop `draft: true` placeholder for display)
- [x] **PHP:** `ReportsViewService::reviewer_total_cell_for_panel_report()` — short-circuit `null` when `get_attendance_status(...) === absent`
- [x] **PHP:** `marks_grid_export` / `scores_matrix_export` row builders — remove `line[] = 'draft'` branch; use empty string when no score
- [x] **JS:** `reportsMarksMatrixUtils.js` — `getScore()`: return `null` when `attendance_status === 'absent'` or `score == null` (no `{ draft: true }` object for empty cells); `formatCellForExport` / `formatExportScore` → `''` instead of `'draft'`
- [x] **JS:** `reportsScoresMatrixUtils.js` / `buildScoresRows` — propagate absent → null cells (server null totals should suffice)
- [x] **UI:** Confirm `ReportsMarksTable` `MarkCell` and `ReportsOverallScoresTable` `OverallCell` already render `—` for `null` (no code change unless a path still prints “draft”)
- [x] **Tests:** `RestReportsTest` — seed absent student with `ensure_absent_marks_draft` rows; assert export cells empty; assert present draft score still numeric in export
- [x] Run `./vendor/bin/phpunit` and `npm run build`

## Dev Notes

### User request (source)

> In the reports, no “draft” word wherever the student is absent (and wherever the score is not there) — leave it empty.

### Root cause

| Location | Current behaviour | User-visible problem |
|----------|-------------------|----------------------|
| `score_cell_from_entry()` | `score === null` → `{ score: null, draft: true }` | Export writes literal **`draft`** |
| `marks_grid_export` / scores export PHP | `elseif (!empty($cell['draft']))` → `'draft'` | XLSX/CSV shows **`draft`** |
| `formatCellForExport()` / `formatExportScore()` JS | `return 'draft'` when no score | Client CSV shows **`draft`** |
| `reviewer_total_cell_for_panel_report()` | Absent students have draft `pr_marks` rows → `{ score: 0, draft: true }` | Overall tab may show **0.00** muted, not the word draft — still wrong for absent |
| `MarkCell` / `OverallCell` UI | `score == null` → `—` | On-screen rubric cells often OK; export is the main “draft” string |

Story **7.8** AC §2 intentionally allowed `draft` or `—` for null draft rows — **this story supersedes** that for report **score** display: **always empty / —**, never the word `draft`.

### Display rules (canonical after 7.10)

| Condition | On-screen score cell | CSV / XLSX |
|-----------|----------------------|------------|
| Absent student | `—` | empty |
| No mark / `score = null` | `—` | empty |
| Numeric score, `status = draft` | number, muted | number |
| Numeric score, submitted | number | number |

**Do not** add a `Draft` `StatusChip` inside score cells (legacy `ReportsScoresTable` with `showDraftTotals` is replaced by 7.9 matrix — confirm 7.9 table does not show draft label in score columns).

### Implementation hints

**`score_cell_from_entry` — pass attendance into helper or check before loop:**

```php
private function score_cell_from_entry(
    array $student,
    int $criterion_id,
    int $reviewer_user_id
): ?array {
    if (($student['attendance_status'] ?? '') === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
        return null;
    }
    // ... existing lookup; when score === null return null (not draft placeholder)
}
```

**`reviewer_total_cell_for_panel_report` — early exit:**

```php
if ($this->assignments->get_attendance_status($review_id, $student_id)
    === ReviewAssignmentRepository::ATTENDANCE_ABSENT) {
    return null;
}
```

**Export PHP (both marks and scores builders) — replace:**

```php
} elseif (!empty($cell['draft'])) {
    $line[] = 'draft';
```

with: only output when `$cell['score'] !== null`; else `$line[] = ''`.

**Client `getScore`:**

```javascript
if (student.attendance_status === 'absent') {
  return null;
}
if (!hit || hit.score == null) {
  return null;
}
// return { score, draft: hit.status !== 'submitted', ... }
```

Keep `draft` flag **only** for muted styling when a **number** exists.

### Files to touch

| File | Change |
|------|--------|
| `includes/services/ReportsViewService.php` | `score_cell_from_entry`, `reviewer_total_cell_for_panel_report`, export row builders |
| `src/coordinator/components/reportsMarksMatrixUtils.js` | `getScore`, `formatCellForExport`, `formatExportScore` |
| `src/coordinator/components/reportsScoresMatrixUtils.js` | Verify absent/null totals → null cells |
| `tests/RestReportsTest.php` | Absent + export empty; draft numeric still exports |
| `build/coordinator.js` | via `npm run build` |

### Out of scope

- Changing `mark_status` computation (`student_report_mark_status` may still return `draft` for absent students with mark rows — Status column only).
- Marking grid / reviewer UI (already shows `—` for absent).
- `ScoreService` weighted formulas.
- Legacy Downloads tab `ReportCard` exports (unless they reuse the same export helpers — then apply same empty rule).

### Previous story intelligence

- **7.8** — show numeric **draft** scores for present students; panel slots; introduced `draft` in export for null scores — **narrowed here**.
- **7.9** — overall scores matrix; uses `reviewer_total_cell_for_panel_report` — fix absent totals here.
- **5.7** — absent → `score = NULL` draft rows in `pr_marks`; reports must not surface those as the word `draft`.
- **11.1** PDF — already `—` for absent in `PanelReportPdfService::score_row_cells()` — no change.

### Anti-patterns (do not)

- Do **not** remove draft mark rows from the database for absent students.
- Do **not** show literal `draft` in any report **score** cell or export column.
- Do **not** change weighted review score to include draft marks.
- Do **not** break 7.8 numeric draft display for **present** students.

### Testing checklist

1. Enrol student absent on review; reviewers save absent → rubric marks cells all `—`; download CSV → score columns empty (not `draft`).
2. Present student, draft score 6.5 on one criterion → cell shows **6.5** (muted); CSV has `6.5`.
3. Overall scores tab: absent student → all reviewer overall cells `—`; present in-progress → numeric muted total.
4. Panel A student on 3-slot review: empty slot columns still `—`.
5. PHPUnit `RestReportsTest` documents absent export behaviour.

## Dev Agent Record

### Agent Model Used

Composer (Cursor agent)

### Debug Log References

### Completion Notes List

- Report score cells return `null` for absent students and null scores (PHP + client); numeric draft scores for present students unchanged with muted styling.
- XLSX/CSV export writes empty cells instead of literal `draft`; reviewer overall totals null for absent students.
- `MarkCell` / `OverallCell` already showed `—` for null — no UI component changes.
- Added `test_marks_grid_export_empty_cells_for_absent_no_draft_label`; full PHPUnit (276) and `npm run build` pass.

### File List

- includes/services/ReportsViewService.php
- src/coordinator/components/reportsMarksMatrixUtils.js
- src/coordinator/components/reportsScoresMatrixUtils.js
- tests/RestReportsTest.php
- build/coordinator.js
- build/coordinator.asset.php

## Change Log

- 2026-05-18: Reports score cells and exports empty when absent or no numeric score; no literal `draft` in score columns (7.10).

## References

- [Source: _bmad-output/implementation/7-8-reports-marks-draft-scores-panel-reviewer-slots.md]
- [Source: _bmad-output/implementation/7-9-reports-overall-scores-matrix.md]
- [Source: _bmad-output/implementation/5-7-student-attendance-marking.md]
- [Source: includes/services/ReportsViewService.php — score_cell_from_entry, reviewer_total_cell_for_panel_report, marks_grid_export]
- [Source: src/coordinator/components/reportsMarksMatrixUtils.js — getScore, formatCellForExport]
- [Source: includes/services/MarkService.php — ensure_absent_marks_draft]
