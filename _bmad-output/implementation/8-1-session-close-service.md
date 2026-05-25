# Story 8.1: SessionCloseService and close REST

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want to close a session with policy B account handling,
So that marking stops and only intended reviewer accounts are disabled.

## Acceptance Criteria

1. **Given** an active session **When** `SessionCloseService::close()` runs **Then** session status becomes `closed` and new marks are rejected **And** provisioned reviewers (`provisioned_for_session = true`) are disabled **And** users with `pr_manage_sessions` are NOT disabled unless explicit `also_disable_coordinators` flag is true **And** audit rows record `session_closed` and disable actions **And** `SessionCloseServiceTest` covers policy B edge cases (NFR15)

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

**Covers:** FR22; NFR15

### References

- [Source: _bmad-output/planning/epics.md — Story 8.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
