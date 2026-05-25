# Story 12.3: Reports — consolidated student-grain export (Excel + CSV)

Status: review

<!-- Depends on 12-1. Epic 12 order: 3 of 5. Full hierarchical marks — primary committee deliverable. -->

## Story

As a **project coordinator**,
I want one **consolidated workbook** with a single row per student and hierarchical columns for every review’s panel context, reviewers, rubric marks, review totals, and project combined score,
So that accreditation and exam boards receive one file instead of stitching per-review exports manually.

## Acceptance Criteria

### 1. Download

1. **Given** a project with one or more confirmed reviews  
   **When** the coordinator downloads **Consolidated student scores**  
   **Then** **Excel** and **flat CSV** are both available.

2. **Given** the export  
   **When** rows are built  
   **Then** grain is **one row per enrolled student** in the project.

3. **And** rows sorted **Reg no ASC** (default; optional `sort_key` query later — not required MVP).

### 2. Fixed leading columns (student + enrolment)

| Column | Notes |
|--------|--------|
| Reg no | |
| Student name | |
| Program | |
| Batch | |
| Guide emp. ID | enrolment |
| Guide name | enrolment |
| Combined score | Level 3 — `ScoreService` combined across reviews (submitted-only policy matches Progress) |

### 3. Per-review column groups (repeat for Review 1 … Review R)

For each review in `sort_order`, a **three-level header** in Excel (two header rows minimum; three for rubric depth):

**Level 0 (review group):** Review number (`label`)

**Level 1 (per review):**

| Sub-column | Content |
|------------|---------|
| Panel | panel name for that review |
| Panel coordinator | |
| Reviewers | comma-separated or first row only in CSV flat — **Excel:** single cell |

**Level 2 — for each reviewer slot (1 … max slots in that review):**

| Sub-column | Content |
|------------|---------|
| Reviewer N total | raw reviewer total (6-7 submitted-only for weighted display; export **numeric** or empty per 7.10) |
| Rubric 1 … Rubric K | one column per criterion mark for that reviewer slot (criterion label as header) |

**Level 1 footer columns (end of each review group):**

| Sub-column | Content |
|------------|---------|
| Review total | weighted review score (Level 2) |
| Review weight % | from `pr_review_weights` if configured; else blank or `100` — match `ScoreService` |

4. **Given** absent student for a review  
   **When** rubric and reviewer total cells export  
   **Then** empty (7.10).

5. **Given** CSV (flat)  
   **When** generated  
   **Then** column headers are **flattened** with path separators, e.g. `Review 2 | Panel`, `Review 2 | Reviewer 1 | Criterion A` (document delimiter in code).

6. **Given** Excel  
   **When** generated  
   **Then** `merge_plan` merges Level-0 review label row only where applicable; freeze panes row 3 if triple header; numeric format on score columns.

### 4. REST

7. **Given** `pr_view_reports`  
   **When** `GET /sessions/{session_id}/consolidated-student-scores/download?format=csv|xlsx`  
   **Then** valid binary export.

8. **And** large projects: stream or generate in PHP memory with PhpSpreadsheet (existing `ExportService` pattern) — document row limit in test if >500 students.

### 5. Live tab parity

9. **And** column semantics match **12-1 Consolidated** live tab for panel + review score columns (subset); full rubric depth is export-only acceptable.

### 6. Tests

10. **And** fixture with 2 reviews, 2 rubrics, 2 reviewer slots asserts flattened CSV column count formula.  
11. **And** `composer test` passes.

## Tasks / Subtasks

- [x] **PHP:** `ReportsViewService::consolidated_student_export()` building row matrix + merge plan
- [x] **REST:** session-scoped download route
- [x] **PHP:** Shared builder with `consolidated_scores()` from 12-1 where possible
- [x] **Tests:** export structure tests

## Dev Notes

### Header depth example (2 reviews, 2 criteria, 2 slots)

```
| Reg | Name | ... | Review 1 (span)     | ... | Review 2 (span) | Combined |
|     |      |     | Panel | Coord | R1 c1 | R1 c2 | R2 c1 | ... | Review total | ...
```

### Performance

- Prefer single SQL batch + in-memory indexing over per-student nested queries.
- Reuse `marks_grid` internal maps pattern from `ReportsViewService`.

### Out of scope

- Per-panel files.
- Audit log or progress %.

### References

- [Source: includes/services/ScoreService.php — three-level scoring]
- [Source: _bmad-output/implementation/6-7-validating-scores.md]
- [Source: _bmad-output/implementation/12-1-reports-panel-context-live-views.md]

## Dev Agent Record

### Agent Model Used

Composer

### Completion Notes List

- Added `ReportsViewService::consolidated_student_export()` with one row per enrolled student, fixed leading columns (reg through guide), trailing combined score, and per-review groups: panel context, reviewer-slot totals (submitted-only), rubric marks, review total, review weight % (when configured in `pr_review_weights`).
- CSV path delimiter: ` | ` (`CONSOLIDATED_STUDENT_CSV_PATH_DELIMITER`). Excel uses three header rows when any review has reviewer slots; freeze panes on header row count; numeric formatting on score columns.
- Reuses 12-1 helpers: `list_enrolled_students`, `panel_id_map_for_review`, `build_panel_reviewers_payload`, `panel_context_for_student`, `score_cell_for_slot`, `ScoreService` for totals; batched `marks_by_student_criterion_for_review` per review.
- REST: `GET /sessions/{id}/consolidated-student-scores/download?format=csv|xlsx` via `Rest_Binary_Response`.
- `ReviewRepository::has_review_weight()` for export weight column blank vs configured.
- Tests: CSV column-count formula (2 reviews × 2 criteria × 2 slots), absent student empty slot total, XLSX magic bytes. PHPUnit 285 OK.

### File List

- includes/services/ReportsViewService.php
- includes/repositories/ReviewRepository.php
- includes/rest/class-rest-reports.php
- tests/RestReportsTest.php
- _bmad-output/implementation/sprint-status.yaml

## Change Log

- 2026-05-18: Story 12-3 — full consolidated student-grain export (hierarchical XLSX + flat CSV).
