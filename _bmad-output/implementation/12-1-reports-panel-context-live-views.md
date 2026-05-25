# Story 12.1: Reports — panel context on live review tables

Status: review

<!-- Epic 12 umbrella. Substories: 12-2 panel roster export, 12-3 consolidated export, 12-4 offline PDF, 12-5 downloads tab. Implement 12-1 first globally. -->

## Story

As a **project coordinator**,
I want **panel name**, **panel coordinator**, and **reviewer roster** visible on each review’s live **Rubric marks** and **Overall scores** tables,
So that I can read committee matrices in context without opening the wizard or panel report flows.

As a **project coordinator**,
I want a **Consolidated scores** live tab that lists every enrolled student once with **review number** and **panel details** repeated per review column group,
So that I can scan cross-review outcomes on screen before downloading the full consolidated workbook.

## Acceptance Criteria

### 1. API enrichment (per-review marks + scores matrices)

1. **Given** `GET .../reviews/{review_id}/marks-grid` or `scores-matrix`  
   **When** the payload builds each student row  
   **Then** include additive fields (backward compatible):
   - `panel_name` (string, from `pr_panels` via review assignment `panel_id`)
   - `panel_coordinator_name` (display name of `is_panel_head` reviewer on that panel for this review, or empty)
   - `panel_reviewers` (existing array) **and** `panel_reviewer_names` (comma-separated display names in slot order)

2. **Given** a student with no panel assignment for the review  
   **When** the row renders  
   **Then** `panel_name` is empty; reviewer slots still follow `max_panel_reviewer_slots` rules from 7.8.

3. **And** guide fields on student rows use **enrolment** source (`guide_emp_id`, `guide_name` from `pr_session_students`) per story **2.5** — add to marks-grid/scores-matrix student objects if not already present.

### 2. Rubric marks tab — fixed identity columns

4. **Given** the Rubric marks tab with a confirmed review selected  
   **When** the matrix renders  
   **Then** fixed columns (left of score groups), in order:
   - Reg no · Student · **Panel** · **Panel coordinator** · **Reviewers** (truncated list with `title` full string) · Attendance · Status  
   **And** existing rubric/reviewer slot score groups and **Weighted review score** column unchanged (7.6, 7.10).

5. **And** sort keys exist for `panel`, `panel_coordinator`, `reviewers` (string compare on displayed values).

6. **And** CSV/Excel export from the marks tab includes the new fixed columns in the same order (update `reportsMarksMatrixUtils.buildColumns` + server `marks_grid_export` header row).

### 3. Overall scores tab — panel context

7. **Given** the Overall scores tab  
   **When** the panel-slot matrix renders  
   **Then** the same **Panel**, **Panel coordinator**, **Reviewers** columns appear before reviewer overall score slots (after Reg no / Student).

8. **And** scores-matrix CSV/XLSX export includes those columns (`reportsScoresMatrixUtils` + `scores_matrix_export`).

### 4. Consolidated scores live tab (cross-review)

9. **Given** the Reports page  
   **When** the coordinator opens the new **Consolidated** tab  
   **Then** a table shows **one row per enrolled student** (project grain).

10. **Given** confirmed reviews ordered by `sort_order` then `id`  
    **When** the consolidated table renders  
    **Then** for **each review** a column group includes at minimum:
    - **Review number** (level-0 header: review `label`)
    - **Panel** (student’s panel for that review)
    - **Panel coordinator**
    - **Reviewers** (roster summary)
    - **Review total** (Level 1 aggregate: sum of reviewer raw totals or existing `review_score` — document which in Dev Notes; default: `ScoreService::calculate_review_score()` submitted-only % or raw per product — **use same as Progress overall**: weighted review score from scores API)
    - **Overall weight** contribution column optional in live view (combined score only once in trailing column — see AC 11)

11. **Given** N confirmed reviews  
    **When** headers render  
    **Then** use two header rows: row 1 = review labels spanning sub-columns; row 2 = Panel / Panel coordinator / Reviewers / Review score (per review).

12. **And** trailing fixed columns: **Program**, **Batch**, **Guide emp. ID**, **Guide name**, **Project title** (enrolment), **Combined score** (Level 3, `ScoreService::calculate_student_combined` or existing aggregate endpoint).

13. **And** data loads via new `GET .../sessions/{id}/consolidated-scores` (or extend existing progress/scores REST) returning student-grain rows with per-review panel block — **no client-side N+1** fetches per review.

14. **And** empty cells when a review has no panel assignment or no score (7.10 rules — no literal `draft` in score cells).

### 5. Regression

15. **And** panel-head PDF, panel report page, review freeze/lock, and marking grids unchanged.  
16. **And** `RestReportsTest` covers new API fields + consolidated endpoint shape; `npm run build` passes.

## Tasks / Subtasks

- [x] **PHP:** Extend `ReportsViewService::marks_grid` / `scores_matrix` student payload with `panel_name`, `panel_coordinator_name`, guide fields from enrolment
- [x] **PHP:** Add `consolidated_scores(int $session_id)` + REST route `GET /sessions/{id}/consolidated-scores`
- [x] **JS:** `reportsMarksMatrixUtils` / `ReportsMarksTable` — panel columns + export
- [x] **JS:** `reportsScoresMatrixUtils` / `ReportsOverallScoresTable` — panel columns + export
- [x] **JS:** `Reports.jsx` — `Consolidated` tab + table component
- [x] **Tests:** PHPUnit + build

## Dev Notes

### Epic 12 — global implementation order

| Order | Story | Why |
|-------|-------|-----|
| **1** | **12-1** (this) | Shared panel/review context for live UI + APIs used by exports |
| 2 | 12-2 | Per-review panel roster download (flat rows) |
| 3 | 12-3 | Consolidated student-grain workbook (depends on 12-1 score/panel blocks) |
| 4 | 12-4 | Offline scoring PDFs (reuses panel roster + 11.1 PDF stack) |
| 5 | 12-5 | Replace Downloads tab catalog; remove legacy seven reports |

### Panel coordinator resolution

```php
// For review_id + panel_id: first reviewer row with is_panel_head = 1
// Display: ReviewerProvisionService / wp user display name pattern used in build_panel_reviewers_payload
```

Reuse `list_panel_reviewers_for_panel` — do not duplicate SQL.

### Consolidated live tab vs 12-3 download

- **12-1:** on-screen consolidated table (subset of columns for readability).
- **12-3:** full hierarchical Excel/CSV with rubric-level marks — not required in 12-1 live view.

### Files to touch

| Area | Files |
|------|--------|
| Service | `includes/services/ReportsViewService.php` |
| REST | `includes/rest/class-rest-reports.php` |
| UI | `src/coordinator/pages/Reports.jsx`, new `ReportsConsolidatedTable.jsx`, matrix utils |
| Tests | `tests/RestReportsTest.php` |

### Previous story intelligence

- **7.5–7.10:** Live marks/scores tabs, empty cells, panel reviewer **slots** — extend identity columns only.
- **2.5:** Guide on enrolment, not registry.
- **11.1:** Panel coordinator = `is_panel_head`; naming **Panel coordinator** in UI.
- **6-7:** Reviewer totals = raw sum; review score = weighted avg across reviewers.

### Anti-patterns

- Do not fetch marks-grid once per review from the browser for consolidated tab.
- Do not break 7.8 slot-based columns by reverting to per-reviewer user_id columns in live matrices.
- Do not add `panel_name` only to exports without live parity.

## References

- [Source: includes/services/ReportsViewService.php]
- [Source: _bmad-output/implementation/7-6-reports-marks-matrix-layout-sort-export.md]
- [Source: _bmad-output/implementation/2-5-per-project-guide-panel-enrolment.md]
- [Source: _bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md]

## Dev Agent Record

### Agent Model Used

Composer

### Completion Notes List

- Extended marks-grid and scores-matrix student payloads with `panel_name`, `panel_coordinator_name`, `panel_reviewer_names`, and enrolment `guide_emp_id` / `guide_name` via shared `panel_context_for_student()` (panel head from `list_panel_reviewers_for_panel`).
- Added `GET /sessions/{id}/consolidated-scores` returning one row per enrolled student with per-review panel blocks and `review_score` from `ScoreService::calculate_review_score()` (null when no submitted marks); `combined_score` from `calculate_combined_score()`.
- Rubric marks / overall scores tables: Panel, Panel coordinator, Reviewers columns before attendance/status; per-slot reviewer name columns removed (slots remain in score group headers). CSV/XLSX exports updated to match.
- New **Consolidated** Reports tab with two-row review headers and trailing enrolment columns.
- Review score in consolidated view uses same weighted review calculation as Progress/Overall scores (submitted marks only).

### File List

- includes/services/ReportsViewService.php
- includes/rest/class-rest-reports.php
- src/coordinator/components/reportsMarksMatrixUtils.js
- src/coordinator/components/reportsScoresMatrixUtils.js
- src/coordinator/components/reportsConsolidatedUtils.js
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/components/ReportsOverallScoresTable.jsx
- src/coordinator/components/ReportsConsolidatedTable.jsx
- src/coordinator/pages/Reports.jsx
- tests/RestReportsTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php

## Change Log

- 2026-05-18: Story 12-1 — panel context on live marks/scores matrices, consolidated scores tab and API.
