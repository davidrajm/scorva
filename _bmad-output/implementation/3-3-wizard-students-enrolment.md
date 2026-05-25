# Story 3.3: Wizard step Students — enrolment and CSV re-enrol

Status: review

## Story

As a **coordinator**,
I want wizard step 1 to enrol registry students and import re-enrol CSV,
So that the session has the correct student roster with panel assignments.

## Acceptance Criteria

1. **Given** a draft session and `WizardNav` on step Students **When** the coordinator searches registry and adds students to the session **Then** students appear in the enrolment list **When** they upload a re-enrol CSV (`reg_no`, `panel`) **Then** `CsvImportMapper` handles mapping and updates session enrolment **And** wizard cannot advance to Panels until at least one student is enrolled (visible blocker)

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- REST: enrol list/add/remove, `POST /sessions/{id}/enrol` for CSV rows.
- `SessionWizard` Students step: registry search, enrolment list, blocked Continue button.
- `CsvImportMapper` `session-enrol` import type with localStorage mapping.

### File List

- includes/rest/class-rest-sessions.php
- src/coordinator/pages/SessionWizard.jsx
- src/shared/components/WizardNav.jsx
- src/coordinator/components/CsvImportMapper.jsx
- src/coordinator/App.jsx

### Change Log

- 2026-05-17: Wizard students step and re-enrol CSV (Epic 3.3).
