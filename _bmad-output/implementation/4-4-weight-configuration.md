# Story 4.4: Review and reviewer weight configuration

Status: review

## Story

As a **coordinator**,
I want to configure review weights and reviewer weights within a session,
So that combined scores reflect our weighting policy.

## Acceptance Criteria

1. **Given** a user with `pr_configure_weights` **When** they update review weights and per-reviewer weights via REST/UI **Then** values persist and default to 1 when unset **When** weights change after marks exist **Then** UI shows optional warning Notice (amber) **And** scores recalculate on next read via ScoreService (no client totals)

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- GET/PUT `/sessions/{id}/weights` with defaults of 1; `RestReviewsTest::test_save_weights_defaults_and_persist`.
- `WeightConfiguration` UI with amber Notice when `has_marks` is true (no client score totals).

### File List

- includes/repositories/ReviewRepository.php (weight helpers)
- includes/rest/class-rest-reviews.php
- src/coordinator/components/WeightConfiguration.jsx
- tests/RestReviewsTest.php
