# Story 7.2: Report query layer for report types (incl. flat rubric scores)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want normalized data queries for each report type,
So that exports reflect session truth including **per-rubric, per-reviewer** marks with stable IDs.

## Acceptance Criteria

1. **Given** a session with enrolment, marks, scores, and audit rows **When** each legacy report query runs (student master, marks detail, review summary, combined scores, panel progress, audit log) **Then** row structures match design spec §11.2 layouts **And** merge plans are defined for Excel panel/review/reviewer grouping (FR21).

2. **Given** view `{prefix}pr_rubric_scores` (Story 7.4) **When** `ReportQueryService::build(TYPE_RUBRIC_SCORES, $session_id)` runs **Then** CSV rows use header `Project ID, Review ID, Reg No, Reviewer ID, Rubric ID, Score` (plus optional Status, Flagged) **And** each row matches `SELECT * FROM pr_rubric_scores WHERE project_id = ?` **And** no client-supplied scores are accepted (FR18).

3. **And** `ReportQueryServiceTest` covers rubric scores fixture parity with `pr_marks` join **And** `TYPE_RUBRIC_SCORES` is registered in `ALL_TYPES` and REST report download map (Story 7.3).

## Tasks / Subtasks

- [x] Add `TYPE_RUBRIC_SCORES` and `rubric_scores()` query method
- [x] Query marks joined to students (same columns as `pr_rubric_scores` view)
- [x] Extend `ReportQueryServiceTest` with ID-column fixture
- [x] Register type in REST reports controller (coordinate with 7.3)
- [x] Run `composer test`

## Dev Notes

### Prerequisites

- Story 7.4 view deployed (`pr_rubric_scores`).
- Epic 6 scores available for aggregate reports.

### Files / patterns

- `includes/services/ReportQueryService.php`
- `tests/ReportQueryServiceTest.php`

### Previous story

- `_bmad-output/implementation/7-4-rubric-scores-fact-schema.md` (schema)
- `_bmad-output/implementation/7-1-export-service-foundation.md` (export pipeline)

**Covers:** FR20, FR21

### References

- [Source: _bmad-output/planning/epics.md — Story 7.2]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md §11.2]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Added `TYPE_RUBRIC_SCORES` with `rubric_scores()` join query, plain table export (no merge plan), freeze row 1, numeric score column.

### File List

- `includes/services/ReportQueryService.php`
- `tests/ReportQueryServiceTest.php`
- `tests/FakeWpdb.php`
