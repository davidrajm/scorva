# Story 4.5: Flagged marks visibility

Status: review

## Story

As a **coordinator or reviewer**,
I want flagged marks clearly indicated after rubric changes,
So that everyone knows which scores may need review.

## Acceptance Criteria

1. **Given** marks were kept and flagged on rubric re-confirm **When** viewing marks in coordinator or reviewer UI **Then** flagged rows show warning StatusChip and tooltip “Rubric changed after marking” **And** flagged state appears in marks-related exports (when export epic complete)

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- `flag_marks_for_review` on re-confirm with `keep_flag`; marks REST returns `flagged` boolean.
- `FlaggedMarkChip` (warning chip + tooltip + screen reader text); flagged marks list on rubrics panel.
- Export column deferred to Epic 7 (per AC note).

### File List

- includes/services/RubricLifecycleService.php
- includes/rest/class-rest-reviews.php
- src/shared/components/FlaggedMarkChip.jsx
- src/coordinator/components/RubricsPanel.jsx
- tests/RestReviewsTest.php
