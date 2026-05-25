# Story 5.5: Blocked-state messaging and API error mapping

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want clear messages when marking is blocked,
So that I know whether to contact a coordinator or admin.

## Acceptance Criteria

1. **Given** API returns `rubric_not_confirmed`, `session_closed`, or `not_assigned` **When** the reviewer attempts to save marks **Then** UI shows fixed user-facing strings (not raw codes) per UX-DR20 **And** banner states who can fix the issue (coordinator vs admin) **And** `@wordpress/components` Notice is used for errors

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
Continue from `_bmad-output/implementation/5-4-rubric-form.md` patterns.


**Covers:** FR16; UX-DR20, UX-DR28, UX-DR33

### References

- [Source: _bmad-output/planning/epics.md — Story 5.5]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

- `mapMarkApiError` surfaces server `invalid_score` detail; added `session_not_active` mapping.

### File List

- src/shared/markErrors.js
