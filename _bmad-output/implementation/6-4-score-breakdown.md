# Story 6.4: ScoreBreakdown read-only component

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want to view per-student score breakdowns without editing totals,
So that I can explain combined scores to committees.

## Acceptance Criteria

1. **Given** a student with marks across reviews **When** the coordinator views mark detail / score breakdown **Then** `ScoreBreakdown` shows reviewer totals, per-review scores, and combined score **And** no input fields allow editing aggregate totals **And** copy uses academic neutral tone

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
Continue from `_bmad-output/implementation/6-3-progress-table-ui.md` patterns.


**Covers:** FR17, FR18; UX-DR16

### References

- [Source: _bmad-output/planning/epics.md — Story 6.4]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
