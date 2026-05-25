# Story 2.2: Student registry REST CRUD and field schema

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want REST endpoints to manage students and custom field definitions,
So that the registry can be maintained programmatically and by the UI.

## Acceptance Criteria

1. **Given** a user with `pr_upload_students` **When** they `POST`, `GET`, `PUT`, `DELETE` students via `/project-reviews/v1/students` **Then** operations succeed with validation on required `reg_no` and `name` **When** they manage field definitions via the schema endpoint **Then** custom fields can be added and values stored per student **And** users without capability receive 403 **And** duplicate `reg_no` returns a clear error

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable) — N/A this story
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
Continue from `_bmad-output/implementation/2-1-student-registry-tables.md` patterns.


**Covers:** FR1; FR28

### References

- [Source: _bmad-output/planning/epics.md — Story 2.2]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Added `Rest_Students` with CRUD on `/students`, field schema on `/students/field-schema`, and `/students/import`.
- Extended `StudentRepository` and added `FieldDefinitionRepository` for list/search/meta/import.
- Permission: `pr_upload_students` with REST nonce on mutating routes; 403/409/400 errors per AC.
- Tests: `RestStudentsTest`, `StudentRepositoryExtendedTest` (52 PHPUnit tests passing).

### File List

- includes/repositories/StudentRepository.php
- includes/repositories/FieldDefinitionRepository.php
- includes/rest/class-rest-students.php
- includes/rest/class-rest-bootstrap.php
- tests/FakeWpdb.php
- tests/RestStudentsTest.php
- tests/StudentRepositoryExtendedTest.php
- tests/HealthEndpointTest.php
- tests/bootstrap.php

## Change Log

- 2026-05-17: Student registry REST CRUD, field schema, and import endpoint with PHPUnit coverage.
