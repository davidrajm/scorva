# Story 12.2: Reports — per-review panel roster export (Excel + CSV)

Status: review

<!-- Depends on 12-1 panel/reviewer resolution patterns. Epic 12 order: 2 of 5. -->

## Story

As a **project coordinator**,
I want to download a **panel roster** for each review round — one row per student with identity, project, guide, and reviewer details,
So that committees receive printable committee lists aligned to panel assignments before or during review week.

## Acceptance Criteria

### 1. Report catalog entry (wired in 12-5; implement query here)

1. **Given** a confirmed review in the project  
   **When** the coordinator downloads **Panel roster (per review)**  
   **Then** formats **Excel (.xlsx)** and **flat CSV** are both available (paired buttons, UX-DR31).

2. **Given** the export  
   **When** rows are generated  
   **Then** grain is **one row per student enrolled in the project** who has a panel assignment **for that review** (students without panel for the review are omitted OR included with blank panel — **default: omit**).

3. **And** rows are sorted **Panel name ASC**, **Reg no ASC**.

### 2. Column schema (canonical flat table)

| Column | Source |
|--------|--------|
| Review number | `pr_reviews.label` |
| Panel | `pr_panels.name` |
| Panel coordinator | head reviewer display name |
| Reg no | `pr_students.reg_no` |
| Student name | `pr_students.name` |
| Program | `pr_students.program` |
| Batch | `pr_students.batch` |
| Project title | enrolment / per-review assignment |
| Guide emp. ID | `pr_session_students.guide_emp_id` |
| Guide name | `pr_session_students.guide_name` |
| Attendance | `Present` / `Absent` / empty if unset |
| Reviewer 1 … Reviewer N | display names in panel slot order (N = max slots across panels in review, empty cells for short panels) |
| Reviewer 1 emp. ID … | optional if available on reviewer profile — else omit column group |

4. **Given** Excel export  
   **When** generated  
   **Then** `merge_plan` merges **Review number** and **Panel** columns for contiguous identical values (same pattern as `student_master`).

5. **Given** CSV export  
   **When** generated  
   **Then** plain UTF-8 CSV with header row — no merges.

### 3. REST

6. **Given** `pr_view_reports`  
   **When** `GET /sessions/{session_id}/reviews/{review_id}/panel-roster/download?format=csv|xlsx`  
   **Then** binary response uses `Rest_Binary_Response` (7.7).

7. **And** review must exist and belong to session; unconfirmed rubric reviews **allowed** for roster (identity list) — document in tests.

### 4. Tests

8. **And** fixture session with two panels asserts row count, sort order, reviewer columns, guide from enrolment.  
9. **And** `composer test` + `npm run build` pass.

## Tasks / Subtasks

- [x] **PHP:** `ReportsViewService::panel_roster_export($session_id, $review_id)` or `ReportQueryService::TYPE_PANEL_ROSTER`
- [x] **REST:** download route + catalog key `panel_roster` (session+review scoped)
- [x] **Tests:** `RestReportsTest` panel roster CSV/xlsx

## Dev Notes

### Reuse

- Panel/reviewer/coordinator resolution from **12-1** helpers (extract shared private methods on `ReportsViewService` if needed).
- `ExportService::to_csv` / `to_xlsx` with merge on columns 0–1.

### Out of scope

- Per-panel separate files (single file with Panel column; committee can filter in Excel).
- Marks or rubric scores.

### References

- [Source: _bmad-output/implementation/12-1-reports-panel-context-live-views.md]
- [Source: includes/services/ReportQueryService.php — student_master merge pattern]

## Dev Agent Record

### Agent Model Used

Composer

### Completion Notes List

- Added `ReportsViewService::panel_roster_export()` with canonical flat columns, panel/reviewer resolution from 12-1 helpers, omit students without panel, sort Panel ASC / Reg no ASC, Excel merge on columns 0–1.
- Added `require_review()` (no rubric confirmation) for roster; `format_panel_roster_attendance()` for Present/Absent/empty.
- REST `GET .../panel-roster/download?format=csv|xlsx` via `Rest_Binary_Response`; catalog entry `panel_roster` with `scope: review` (UI wiring in 12-5).
- `RestReportsTest`: two-panel CSV fixture, unconfirmed review allowed, xlsx binary; full PHPUnit (282) and `npm run build` pass.

### File List

- includes/services/ReportsViewService.php
- includes/rest/class-rest-reports.php
- tests/RestReportsTest.php
- _bmad-output/implementation/sprint-status.yaml

### Change Log

- 2026-05-18: Panel roster per-review export (CSV + XLSX), REST download route, catalog key, PHPUnit coverage.
