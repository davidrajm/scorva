# Story 3.1: Session, panel, and enrolment database tables

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **developer**,
I want session-related tables for enrolment, panels, and reviewer links,
So that session configuration can be persisted.

## Acceptance Criteria

1. **Given** migration runs after registry tables exist **When** `dbDelta` completes **Then** tables exist: `pr_sessions`, `pr_session_students`, `pr_panels`, `pr_panel_reviewers`, `pr_session_reviewers` (and override table if in schema) **And** session `status` supports `draft`, `active`, `closed` **And** `SessionRepositoryTest` covers create session and enrol student

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Epic 2 registry ready.

### Files / patterns
- Tables: `pr_sessions`, `pr_session_students`, `pr_panels`, `pr_panel_reviewers`, `pr_session_reviewers`.
- `ReviewerProvisionService`, plugin-branded emails in `includes/emails/` (NFR17).
- Wizard: `WizardNav` gates per UX-DR32.

**Covers:** FR3, FR4, FR5; NFR8

### References

- [Source: _bmad-output/planning/epics.md — Story 3.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Schema already in `Install.php`; added `name` column to `pr_panel_reviewers`.
- Implemented `SessionRepository` and `PanelRepository` with enrolment, panel assignment, and import helpers.
- Added `SessionRepositoryTest` (create session, enrol student, status constants).

### File List

- includes/Install.php
- includes/repositories/SessionRepository.php
- includes/repositories/PanelRepository.php
- tests/SessionRepositoryTest.php
- tests/FakeWpdb.php

### Change Log

- 2026-05-17: Session/panel repositories and tests for Epic 3.1.
