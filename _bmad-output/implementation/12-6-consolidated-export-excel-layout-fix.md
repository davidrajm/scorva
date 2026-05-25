# Story 12.6: Consolidated export — Excel column alignment, hierarchy, project header

Status: review

<!-- Follow-up to 12-3. Fixes XLSX column/data misalignment and adds a project metadata block. CSV flat export semantics unchanged unless noted. -->

## Story

As a **project coordinator**,
I want the **Consolidated student scores** Excel download to have a clear project summary at the top and a correctly aligned hierarchical grid (review → reviewer → rubric marks, with totals at each level),
So that accreditation committees can open one workbook where headers line up with scores and context is obvious without the live Reports UI.

## Acceptance Criteria

### 1. Row grain (unchanged)

1. **Given** a project with confirmed reviews and enrolled students  
   **When** the coordinator downloads **Consolidated student scores** (Excel or CSV)  
   **Then** grain remains **one row per enrolled student**, sorted **Reg no ASC** (same as 12-3).

2. **And** REST route unchanged: `GET /sessions/{session_id}/consolidated-student-scores/download?format=csv|xlsx`.

### 2. Excel — project details block (new)

3. **Given** format is `xlsx`  
   **When** the workbook is generated  
   **Then** rows **above** the score table present a **project metadata block** (not merged into score header rows):

   | Label | Value source |
   |-------|----------------|
   | Report title | Fixed: `Consolidated student scores` |
   | Project | `pr_sessions.title` |
   | Project status | `pr_sessions.status` (human-readable label OK) |
   | Generated | Site-local datetime (`wp_date` preferred) |
   | Enrolled students | Count from `SessionRepository::count_enrolled` |
   | Reviews included | Comma-separated confirmed review `label`s in `sort_order` |
   | Program | `SessionPanelReportSettings::get()['report']['program_name']` when non-empty |
   | Semester | `SessionPanelReportSettings::get()['report']['semester']` when non-empty |

4. **And** layout rules for the metadata block:
   - Column A = labels (bold), column B = values (left-aligned); optional wider merge for title row across A:B or A:last data column.
   - One **blank row** between metadata and the hierarchical table header.
   - Metadata rows are **not** included in CSV download (CSV remains flat header + data only).

5. **And** styling: title row larger/bold; metadata body readable; no grey header fill on metadata (header fill applies only to score table header rows per `ExportService`).

### 3. Excel — hierarchical headers and alignment (fix)

6. **Given** the hierarchical score table  
   **When** headers are built  
   **Then** column levels are:

   | Level | Row | Content |
   |-------|-----|---------|
   | **0** | 1 (within table) | Review label — horizontal span over entire review group |
   | **1** | 2 | Panel context: **Panel**, **Panel coordinator**, **Reviewers** (each own column, vertically merged through rubric row when 3 header rows) |
   | **1** | 2 | Per reviewer slot: **Reviewer N** — horizontal span over that slot’s score columns |
   | **1** | 2 | End of review group: **Review total**, **Review weight %** (vertically merged when 3 rows) |
   | **2** | 3 (only when any review has ≥1 reviewer slot) | Under each reviewer slot: **Total** + one column per rubric criterion label |
   | **2** | 3 | Fixed leading columns: **Reg no**, **Student name**, **Program**, **Batch**, **Guide emp. ID**, **Guide name** — vertically merged rows 1–3 (or 1–2 when only 2 header rows) |
   | **2** | 3 | Trailing **Combined score** — vertically merged |

7. **Given** a generated XLSX with reviewer slots and multiple rubric criteria  
   **When** a coordinator opens the file  
   **Then** **every data column aligns under its header column** (no shifted rubric scores under wrong reviewer/review).  
   **Regression guard:** header column count === each data row column count.

8. **Root cause to fix (12-3 bug):** `build_consolidated_student_export_sheet()` builds headers from `$expanded_columns` (includes `span_pad` placeholders for horizontal merges) but data rows iterate `$columns` only — **data must emit one cell per expanded column** (empty string for `span_pad` columns), or data iteration must use the same expanded column list as headers.

9. **Given** score columns  
   **When** values export  
   **Then** numeric cells use `0.00` format; empty when absent/no submitted score (7.10 — no `draft` label).

10. **Given** hierarchy totals  
    **When** values export per student row  
    **Then:**
    - **Reviewer slot total** — `ScoreService::calculate_reviewer_total(..., submitted_only: true)` or empty if absent/unassigned.
    - **Review total** — `ScoreService::calculate_review_score()` weighted review score or empty if no reviewers.
    - **Combined score** — `ScoreService::calculate_combined_score()` (trailing column).
    - **Review weight %** — configured weight when `has_review_weight`; else blank (12-3 behaviour).

### 4. Merge plan, freeze, and offsets

11. **Given** metadata block of `P` rows + blank row  
    **When** merge plan and `styles.freeze_row` / `styles.header_row_count` are computed  
    **Then** all merge coordinates are **offset by `P + 1`** table start row (1-based), including:
    - Level-0 review group merges on row 1 of **table** (not row 1 of sheet).
    - Vertical merges for fixed leading + panel context + review footer columns.
    - Horizontal merges for reviewer slot groups on header row 2 of table.

12. **And** freeze pane sits immediately below the **last score header row** (below row `preface_rows + header_rows`, not row 1).

13. **And** `numeric_columns` formatting in `ExportService::to_xlsx()` applies from **first data row** through last row (not from row 2 globally) — extend `ExportService` or pass `data_start_row` / `preface_row_count` in `$styles` if needed (prefer small extension over duplicating PhpSpreadsheet logic in `ReportsViewService`).

### 5. CSV (parity on data, not layout)

14. **Given** format is `csv`  
    **When** downloaded  
    **Then** flattened headers remain `Review N | …` delimiter ` | ` (`CONSOLIDATED_STUDENT_CSV_PATH_DELIMITER`); column count formula unchanged from 12-3 tests.

15. **And** CSV column order and values match the same logical fields as Excel data rows (no metadata rows).

### 6. Tests

16. **And** PHPUnit extends `RestReportsTest` or dedicated export test:
    - Fixture: 2 reviews, 2 criteria, 2 reviewer slots — assert header width === data width on built `rows` before `to_xlsx`.
    - Assert known mark lands in column index matching flattened header path (e.g. `Review Alpha | Reviewer 1 | Criterion A`).
    - XLSX still returns `PK` magic bytes; optional: load with PhpSpreadsheet in test to assert merged range count & freeze pane row if cheap.

17. **And** `composer test` passes.

## Tasks / Subtasks

- [x] **Fix alignment:** Unify header and data column iteration in `build_consolidated_student_export_sheet()` (AC 7–8).
- [x] **Preface block:** `build_consolidated_student_project_preface(int $session_id, array $review_specs)` returning label/value rows; prepend to `rows`; offset `merge_plan` and styles (AC 3–5, 11–13).
- [x] **ExportService:** Add `preface_row_count` (or `data_start_row`) to `$styles`; adjust header styling range, freeze pane, numeric format row bounds (AC 11–13).
- [x] **Tests:** Column-count parity + spot-check cell index; keep existing CSV column-count test green (AC 14–17).
- [x] Run `./vendor/bin/phpunit` — no frontend change required.

## Dev Notes

### Known bug (implement first)

In `ReportsViewService::build_consolidated_student_export_sheet()` (~1092–1233):

```php
// Headers: foreach ($expanded_columns as $column) { ... span_pad cols ... }
// Data:   foreach ($columns as $column) { ... }  // WRONG — fewer cells when span > 1
```

**Fix:** Extract `expand_consolidated_columns(array $columns): array` used by **both** header builder and data row builder. Data rows: `''` for `span_pad` entries.

### Header column order (canonical)

Fixed leading (6): Reg no · Student name · Program · Batch · Guide emp. ID · Guide name  

Per review (repeat):

1. Panel · Panel coordinator · Reviewers  
2. For each slot `1..max_slots`: `[Reviewer N total | Rubric 1 | … | Rubric K]`  
3. Review total · Review weight %  

Trailing: Combined score  

### Metadata block wireframe (Excel rows 1–n)

```
| Consolidated student scores                    |  (title)
| Project          | Final Year Projects 2026   |
| Project status   | active                     |
| Generated        | 19 May 2026, 14:30        |
| Enrolled students| 42                         |
| Reviews included | Review 1, Review 2         |
| Program          | B.Tech CSE                 |  (optional)
| Semester         | Even 2025-26               |  (optional)
|                  |                            |  (blank)
| [hierarchical table headers start]             |
```

Reuse label/value presentation patterns from `PanelReportPdfService::render_metadata()` for consistency; Excel uses cells not HTML.

### Score semantics (do not change)

| Cell | Source |
|------|--------|
| Rubric mark | `score_cell_for_slot` + submitted-only display rules (7.10) |
| Reviewer total | `calculate_reviewer_total(..., true)` |
| Review total | `calculate_review_score` |
| Combined | `calculate_combined_score` |

Live **Consolidated** tab (12-1) stays summary-level; full rubric depth remains export-only.

### Files to touch

| File | Change |
|------|--------|
| `includes/services/ReportsViewService.php` | Preface builder; fix expanded column parity; offset merge plan |
| `includes/services/ExportService.php` | `preface_row_count` / `data_start_row` in styles |
| `tests/RestReportsTest.php` | Alignment regression tests |
| `tests/ExportServiceTest.php` | Optional preface offset unit test |

**Do not** change REST path, catalog key `consolidated_student_scores`, or CSV delimiter.

### Out of scope

- Reordering fixed columns to match live Consolidated tab (12-1 uses different column order on screen — export keeps 12-3 order).
- Client-side SheetJS export.
- Per-panel consolidated files.

### References

- [Source: includes/services/ReportsViewService.php — `consolidated_student_export`, `build_consolidated_student_export_sheet`]
- [Source: includes/services/ExportService.php — `to_xlsx`, merge helpers]
- [Source: _bmad-output/implementation/12-3-reports-consolidated-student-export.md]
- [Source: _bmad-output/implementation/7-10-reports-empty-cells-no-draft-label.md]
- [Source: includes/services/PanelReportPdfService.php — `render_metadata` label/value pattern]

## Dev Agent Record

### Agent Model Used

Composer (Cursor agent)

### Debug Log References

### Completion Notes List

- Fixed XLSX column misalignment by extracting `expand_consolidated_columns()` and emitting one data cell per expanded column (empty for `span_pad` placeholders).
- Added Excel-only project metadata preface (`build_consolidated_student_project_preface`) with title merge, label/value rows, optional program/semester, and blank separator before the score table.
- Offset hierarchical merge plan and styles (`preface_row_count`, `data_start_row`, `freeze_row`) so freeze pane and grey header fill apply only to the score table.
- Extended `ExportService::to_xlsx()` for preface-aware header styling, freeze row, and numeric formatting from first data row.
- Added PHPUnit regression tests for header/data column parity and CSV header path mark alignment; existing CSV column-count test unchanged.

### File List

- includes/services/ReportsViewService.php
- includes/services/ExportService.php
- tests/RestReportsTest.php
- tests/ExportServiceTest.php

## Change Log

- 2026-05-19: Story 12-6 — fix consolidated XLSX column alignment, hierarchical headers, project metadata preface.
- 2026-05-20: Implemented alignment fix, metadata preface, ExportService preface offsets, and regression tests.
