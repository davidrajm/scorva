# Story 6.1: Three-level ScoreService

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want combined scores computed server-side with three weighted levels,
So that totals are trustworthy and consistent across UI and exports.

## Acceptance Criteria

1. **Given** criterion marks and weights for a student **When** `ScoreService` calculates totals **Then** Level 1 reviewer total, Level 2 review score, and Level 3 combined score match design spec formulas **And** weights default to 1 when unset **And** `ScoreServiceTest` includes fixture scenarios for all three levels **And** combined scores are never read from request body (FR18)

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

**Covers:** FR17, FR18; NFR7, NFR16

### References

- [Source: _bmad-output/planning/epics.md — Story 6.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
