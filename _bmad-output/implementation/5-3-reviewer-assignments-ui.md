# Story 5.3: Reviewer assignments and student list UI

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want to see my assignments and pick a student to mark,
So that I know where to work without seeing other panels.

## Acceptance Criteria

1. **Given** the reviewer opens `/reviews/mark/` **When** active sessions have confirmed rubrics for their assignments **Then** assignment cards show session name, review round, and panel **When** they select an assignment **Then** student list shows only assigned students with draft/submitted indicators **And** back navigation returns to assignments (UX-DR23) **When** rubric is not confirmed or session inactive **Then** assignment is hidden or disabled with reason banner (UX-DR28)

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
Continue from `_bmad-output/implementation/5-2-marks-rest-scoping.md` patterns.


**Covers:** FR14, FR27; UX-DR5, UX-DR17, UX-DR23, UX-DR28

### References

- [Source: _bmad-output/planning/epics.md — Story 5.3]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
