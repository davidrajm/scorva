# Story 5.2: Marks REST with assignment scoping

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want REST endpoints to read and write marks only for my assignments,
So that I cannot access other panels’ students.

## Acceptance Criteria

1. **Given** a reviewer with `pr_enter_marks` assigned to Panel A, Review 1 **When** they GET marks for an assigned student **Then** data is returned **When** they POST marks for an unassigned student or review **Then** API returns `not_assigned` error code **And** coordinators with appropriate caps can read broader mark sets

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Epic 4 confirmed rubrics + active sessions.

### Files / patterns
- `MarkService` guards before REST writes; table `pr_marks`.
- Reviewer funnel: assignments → student list → `RubricForm` on `/reviews/mark/`.
- Map API codes to UX strings (UX-DR20).
### Previous story
Continue from `_bmad-output/implementation/5-1-marks-persistence-guards.md` patterns.


**Covers:** FR14, FR15, FR16, FR28; NFR5

### References

- [Source: _bmad-output/planning/epics.md — Story 5.2]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
