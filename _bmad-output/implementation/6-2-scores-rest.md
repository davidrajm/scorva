# Story 6.2: Scores REST (read-only)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator or reviewer**,
I want to fetch computed scores via REST,
So that UI displays breakdowns without client-side math.

## Acceptance Criteria

1. **Given** marks exist for a student/review **When** client calls `GET` scores endpoint **Then** response includes reviewer totals, review scores, and combined score as read-only numbers **When** client sends combined score in POST body **Then** server ignores it and does not persist client aggregates

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Epic 5 marks in `pr_marks`.

### Files / patterns
- `ScoreService` — three-level formulas §6; never persist client combined totals (FR18).
- UI: `ProgressTable`, `ScoreBreakdown`; route `#/session/:id/progress`.
### Previous story
Continue from `_bmad-output/implementation/6-1-score-service.md` patterns.


**Covers:** FR17, FR18, FR28

### References

- [Source: _bmad-output/planning/epics.md — Story 6.2]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
