# Story 4.1: Reviews, rubrics, and lifecycle REST

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want REST endpoints for review rounds, criteria, and rubric lifecycle actions,
So that rubric state is enforced server-side before marking opens.

## Acceptance Criteria

1. **Given** tables `pr_reviews`, `pr_rubric_criteria`, `pr_review_weights`, `pr_reviewer_weights` exist after migration **When** coordinator creates Review 1 with criteria (label, max_marks, weight default 1) **Then** criteria persist in draft review status **When** coordinator calls confirm **Then** review status becomes `confirmed` and marking is allowed for that review **When** coordinator unlocks and re-confirms **Then** `RubricLifecycleService` supports `keep_flag` and `clear` paths per design spec §5.3 **And** `RubricLifecycleServiceTest` covers confirm/unlock/re-confirm

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Epic 3 session data + wizard shell.

### Files / patterns
- `RubricLifecycleService` — confirm/unlock/re-confirm (`keep_flag` | `clear`) per §5.3.
- Tables: `pr_reviews`, `pr_rubric_criteria`, `pr_review_weights`, `pr_reviewer_weights`.
- UI: `RubricTable`, `ConfirmDialog`.

**Covers:** FR10, FR11; FR28

### References

- [Source: _bmad-output/planning/epics.md — Story 4.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Added `ReviewRepository` and `RubricLifecycleService` (draft → confirmed → unlocked; re-confirm with `keep_flag` / `clear`).
- Registered `Rest_Reviews` routes under `/sessions/{id}/reviews` (CRUD criteria, confirm, unlock, marks list, weights).
- `RubricLifecycleServiceTest` and `RestReviewsTest` cover lifecycle and REST.

### File List

- includes/repositories/ReviewRepository.php
- includes/services/RubricLifecycleService.php
- includes/rest/class-rest-reviews.php
- includes/rest/class-rest-bootstrap.php
- tests/RubricLifecycleServiceTest.php
- tests/RestReviewsTest.php
- tests/FakeWpdb.php
