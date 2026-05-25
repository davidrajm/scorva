# Story 2.4: CSV student import with column mapper

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want to import students from CSV with remembered column mapping and error reporting,
So that bulk registry setup is fast and recoverable.

## Acceptance Criteria

1. **Given** a CSV with at least `reg_no` and `name` columns **When** the coordinator maps columns via `CsvImportMapper`, previews first 3 rows, and submits **Then** valid rows are imported; mapping is saved to `localStorage` per import type **When** duplicate `reg_no` rows exist **Then** the user chooses update or skip before import proceeds **When** some rows fail validation **Then** the response includes row-level errors and a downloadable error CSV **And** a success Notice summarizes imported vs failed counts

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Epic 1 complete (REST bootstrap, coordinator SPA, shared components).

### Files / patterns
- Migrations: `pr_db_version` + `dbDelta`; tables `pr_students`, `pr_field_definitions`, `pr_student_meta` (design spec §5.1).
- `includes/repositories/StudentRepository.php`
- REST: `includes/rest/class-rest-students.php` → register in `class-rest-bootstrap.php`
- UI: `src/coordinator/pages/Registry.jsx` at `#/registry`

### Do not
- Session tables (Epic 3) or theme assets.
### Previous story
Continue from `_bmad-output/implementation/2-3-registry-ui.md` patterns.

### Component
`CsvImportMapper.jsx` — column map, 3-row preview, localStorage per import type (UX-DR14).

**Covers:** FR2; UX-DR14, UX-DR20

### References

- [Source: _bmad-output/planning/epics.md — Story 2.4]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- `CsvImportMapper` with column dropdowns, 3-row preview, `localStorage` mapping per import type.
- Duplicate reg_no in file prompts update/skip choice before import.
- `POST /students/import` returns row errors + `error_csv`; UI shows Notice summary and download button.
- PHPUnit covers import success, failures, and duplicate update policy.

### File List

- src/coordinator/components/CsvImportMapper.jsx
- src/coordinator/pages/Registry.jsx
- src/shared/components/Notice.jsx
- src/shared/components/index.js
- includes/rest/class-rest-students.php
- includes/repositories/StudentRepository.php
- tests/RestStudentsTest.php

## Change Log

- 2026-05-17: CSV import mapper UI and import REST endpoint with error CSV and duplicate handling.
