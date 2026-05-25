# Story 3.4: Wizard step Panels — assign all enrolled students

Status: review

## Story

As a **coordinator**,
I want to create panels and assign every enrolled student,
So that reviewers can be scoped to panel groups.

## Acceptance Criteria

1. **Given** enrolled students in the session **When** the coordinator creates panels and assigns students **Then** each student has exactly one `panel_id` **When** any student is unassigned **Then** WizardNav blocks advance to Reviewers with tooltip listing unassigned count **And** unassigned students are highlighted in the step UI

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- Panels REST CRUD; assign panel via `PUT /sessions/{id}/students/{student_id}`.
- Wizard Panels step highlights unassigned rows; `WizardNav` + Continue gated on `unassigned_count`.
- `GET /sessions/{id}/wizard-state` exposes gate flags.

### File List

- includes/rest/class-rest-sessions.php
- includes/repositories/SessionRepository.php
- src/coordinator/pages/SessionWizard.jsx
- src/shared/components/WizardNav.jsx

### Change Log

- 2026-05-17: Wizard panels step with assignment gates (Epic 3.4).
