# Story 3.5: Wizard step Reviewers — roster, CSV import, and provisioning

Status: review

## Story

As a **coordinator**,
I want to assign reviewers by panel, import rosters, and provision WordPress accounts,
So that reviewers can log in and receive credentials.

## Acceptance Criteria

1. **Given** panels exist with assigned students **When** the coordinator adds reviewers with email, name, and weight per panel **Then** roster rows persist via REST **When** they import panel reviewer CSV (`panel`, `reviewer_name`, `email`, optional `weight`) **Then** import uses `CsvImportMapper` with row-level error handling **When** provisioning runs for a new email **Then** existing WP user is matched or new user is created with generated password **And** plugin-branded invite email is sent with login URL and credentials (FR7, NFR17) **And** a success toast confirms credentials sent

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- `ReviewerProvisionService`: create/match WP user, `pr_session_reviewers` row, branded email.
- `Rest_Reviewers`: panel roster CRUD, import, provision endpoint.
- Wizard Reviewers step + `CsvImportMapper` `panel-reviewers` type.
- `ReviewerProvisionServiceTest` covers provision and email.

### File List

- includes/services/ReviewerProvisionService.php
- includes/emails/ReviewerInviteEmail.php
- includes/rest/class-rest-reviewers.php
- src/coordinator/pages/SessionWizard.jsx
- src/coordinator/components/CsvImportMapper.jsx
- tests/ReviewerProvisionServiceTest.php
- tests/bootstrap.php

### Change Log

- 2026-05-17: Reviewer roster, CSV import, provisioning (Epic 3.5).
