# Story 3.6: Resend credentials and manual reviewer linking

Status: review

## Story

As a **coordinator**,
I want to resend invites and link reviewers to existing users when email is missing,
So that roster gaps can be resolved without re-importing.

## Acceptance Criteria

1. **Given** a provisioned reviewer on a session roster **When** the coordinator clicks Resend credentials **Then** a new invite email is sent and action is logged
2. **Given** a reviewer row without email **When** the coordinator picks an existing WP user (or faculty row when bridge active) **Then** the roster links `user_id` without creating a duplicate account **And** `pr_session_reviewers` distinguishes provisioned vs linked users

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- `POST .../resend-credentials` resets password and resends invite; audit log entry.
- `POST .../link-user` links `user_id` with `provisioned_for_session = 0`.
- `GET /users/search` for manual WP user pick in wizard UI.
- Faculty bridge deferred (NFR18); WP user search only.

### File List

- includes/services/ReviewerProvisionService.php
- includes/services/AuditService.php
- includes/rest/class-rest-reviewers.php
- src/coordinator/pages/SessionWizard.jsx

### Change Log

- 2026-05-17: Resend credentials and manual user linking (Epic 3.6).
