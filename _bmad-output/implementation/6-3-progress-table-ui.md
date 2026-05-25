# Story 6.3: Progress REST and ProgressTable UI (rubric-score grain)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want a progress page showing completion by panel and reviewer at **rubric-score** granularity,
So that I can see how many individual scores still need to be entered before close.

## Acceptance Criteria

1. **Given** an active session with enrolled students, confirmed reviews, rubric criteria, panel reviewers, and marks **When** `ScoreService::calculate_session_progress($session_id)` runs **Then** for each panel × reviewer row, `total` equals the count of expected score cells: **students on that panel × each confirmed review × each criterion in that review** **And** `completed` equals the count of `pr_marks` rows for that reviewer with `status = submitted` at that grain (one increment per criterion mark, not per student-review bundle).

2. **Given** fixture data where reviewer A submitted both criteria for one student in one review **When** progress is calculated **Then** reviewer A shows `completed = 2`, `total = 2`, `percent = 100.0` (not 1/1).

3. **Given** the coordinator opens `#/session/:id/progress` **When** the page loads **Then** `ProgressTable` lists panel, reviewer, completed/total, percent, and ProgressBar **And** table header is sticky; numbers use tabular-nums **And** copy clarifies totals are **rubric scores** (criterion marks), not whole student forms.

4. **And** `ScoreServiceTest::test_progress_percent_matches_rubric_score_grain` asserts server math **And** `GET /sessions/{id}/progress` returns the same numbers.

## Tasks / Subtasks

- [x] Update `ScoreService::calculate_session_progress()` to rubric-score grain (AC: 1, 2)
- [x] Update `ScoreServiceTest` progress fixture expectations (AC: 2)
- [x] Adjust `ProgressTable` column labels / description if needed (AC: 3)
- [x] Run `composer test`

## Dev Notes

### Marking grain (canonical)

For each **project** (`session_id`), for each **review** round, for each **rubric** criterion, from each **reviewer** on the student's panel, for each **student** — one score is stored in `pr_marks` (unique key on session, review, student, reviewer, criterion).

Flat export / SQL view: `pr_rubric_scores` (`project_id`, `review_id`, `reg_no`, `reviewer_id`, `rubric_id`, `score`).

### Prerequisites

- Epic 5 marks in `pr_marks`.
- Story 7.4 view (for export parity; progress uses `pr_marks` directly).

### Files / patterns

- `includes/services/ScoreService.php` — `calculate_session_progress()`
- `includes/rest/class-rest-progress.php`
- `src/coordinator/components/ProgressTable.jsx`
- `tests/ScoreServiceTest.php`

### Previous story

Continue from `_bmad-output/implementation/6-2-scores-rest.md` patterns.

**Covers:** FR19; UX-DR12, UX-DR24, UX-DR14 (reduced-motion)

### References

- [Source: _bmad-output/planning/epics.md — Story 6.3]
- [Source: tests/sql/01_seed_demo_session.sql]

## Dev Agent Record

### Agent Model Used

Composer (Claude)

### Debug Log References

### Completion Notes List

- `calculate_session_progress()` now sums expected cells as students × confirmed reviews × criteria per review; `completed` counts each submitted `pr_marks` row at criterion level.
- Renamed test to `test_progress_percent_matches_rubric_score_grain` with completed=2, total=2 for two-criterion fixture.
- Progress UI: column "Scores submitted", sr-only caption, SessionProgress description clarifies criterion-level counts.
- `composer test` script not in composer.json; ran `./vendor/bin/phpunit` — 100 tests OK.

### File List

- includes/services/ScoreService.php
- tests/ScoreServiceTest.php
- src/coordinator/components/ProgressTable.jsx
- src/coordinator/pages/SessionProgress.jsx
