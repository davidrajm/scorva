# Story 5.1: Marks persistence and MarkService guards

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **developer**,
I want marks stored with server-side guards for rubric and session state,
So that invalid marking cannot be persisted.

## Acceptance Criteria

1. **Given** table `pr_marks` exists **When** `MarkService` receives a mark POST **Then** it rejects if rubric is not `confirmed`, session is `closed`, or reviewer is not assigned **And** criterion values are validated against `max_marks` **And** `MarkServiceTest` covers guard cases and draft vs submitted status

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

**Covers:** FR15, FR16; NFR7

### References

- [Source: _bmad-output/planning/epics.md — Story 5.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

- MarkService guards (confirmed rubric, closed session, assignment, max_marks); MarkRepository upsert; MarkServiceTest.

### File List

- includes/repositories/MarkRepository.php
- includes/Services/MarkService.php
- tests/MarkServiceTest.php
- tests/FakeWpdb.php
