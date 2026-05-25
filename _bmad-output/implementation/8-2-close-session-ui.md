# Story 8.2: Close session UI with consequence dialog

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want a close session screen with summary and explicit confirmation,
So that I do not accidentally lock out coordinators or leave marking open.

## Acceptance Criteria

1. **Given** the coordinator opens Close session for an active session **When** the screen loads **Then** summary shows session status, open marks count, and provisioned users affected **And** checkbox “Also disable coordinator-capable users” is unchecked by default with warning when checked **When** they confirm via `ConfirmDialog` **Then** session closes and success Notice appears **And** copy uses consequence bullet list (UX-DR33)

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Session reviewers with provisioned flags (Epic 3).

### Files / patterns
- `SessionCloseService` policy B (§8.3): disable provisioned only; coordinator checkbox for edge case.
- `ConfirmDialog` close variant.
### Previous story
Continue from `_bmad-output/implementation/8-1-session-close-service.md` patterns.


**Covers:** FR22, FR26; UX-DR13, UX-DR33

### References

- [Source: _bmad-output/planning/epics.md — Story 8.2]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
