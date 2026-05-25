# Story 7.4: Flat rubric scores schema (project × review × student × reviewer × rubric)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator or integrator**,
I want a stable SQL surface for per-rubric marks at the grain **project (session) × review × reg_no × reviewer × rubric × score**,
So that exports and external tools can query criterion-level scores without joining five tables by hand.

## Acceptance Criteria

1. **Given** `pr_marks` stores criterion-level scores and `pr_students` stores `reg_no` **When** the plugin schema is installed or upgraded **Then** view `{prefix}pr_rubric_scores` exists with columns: `project_id` (session), `review_id`, `reg_no`, `reviewer_id`, `rubric_id` (criterion), `score` **And** optional helper columns `status`, `flagged`, `mark_id` for audit/export **And** `Install::ensure_rubric_scores_view()` is invoked from `ensure_schema_patches()`.

2. **Given** a session with marks **When** `SELECT project_id, review_id, reg_no, reviewer_id, rubric_id, score FROM {prefix}pr_rubric_scores WHERE project_id = ?` runs **Then** row count equals joined `pr_marks` count for that session **And** each `score` matches the underlying `pr_marks.score` for the same grain.

3. **Given** `tests/sql/validate_rubric_scores.sql` **When** a developer runs the queries against a fixture or staging database **Then** view existence, row parity, and spot-check joins succeed.

4. **And** `RubricScoresSchemaTest` and `InstallSchemaPatchTest` cover view DDL and patch idempotency **And** canonical storage remains `pr_marks` (no duplicate write path in this story).

## Out of scope (later stories)

- Seventh report type / REST download (Story 7.2, 7.3).
- UI score breakdown per criterion (Story 6.4 extension).
- Full assignment grid with NULL scores for unrated rubrics (optional `pr_rubric_score_grid` view).

## Tasks / Subtasks

- [x] Add `Install::rubric_scores_view_ddl()` and `ensure_rubric_scores_view()`
- [x] Wire into `ensure_schema_patches()`
- [x] Add `tests/sql/validate_rubric_scores.sql`
- [x] Add PHPUnit coverage (`RubricScoresSchemaTest`, patch test)
- [ ] Run manual SQL validation on staging after deploy

## Dev Notes

### Column mapping (export contract)

| Export column | Source |
|---------------|--------|
| `project_id` | `pr_marks.session_id` |
| `review_id` | `pr_marks.review_id` |
| `reg_no` | `pr_students.reg_no` |
| `reviewer_id` | `pr_marks.reviewer_user_id` |
| `rubric_id` | `pr_marks.criterion_id` |
| `score` | `pr_marks.score` |

### Prerequisites

- Epic 5 marks in `pr_marks` (Story 5.1).
- Student registry `reg_no` (Epic 2).

### References

- [Source: _bmad-output/planning/epics.md — Story 7.4]
- [Source: tests/sql/validate_rubric_scores.sql]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
