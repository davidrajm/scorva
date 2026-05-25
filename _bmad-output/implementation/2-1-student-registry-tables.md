# Story 2.1: Student registry database tables

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **developer**,
I want student registry tables created on plugin install/upgrade,
So that coordinators can persist students and custom field definitions.

## Acceptance Criteria

1. **Given** plugin activation or `pr_db_version` bump **When** migrations run via `dbDelta` **Then** tables `pr_students`, `pr_field_definitions`, and `pr_student_meta` exist with `reg_no` unique constraint **And** migration is idempotent on re-activation **And** `StudentRepositoryTest` can insert and fetch a student fixture

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable) — N/A this story
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

**Covers:** FR1; NFR8

### References

- [Source: _bmad-output/planning/epics.md — Story 2.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- `Install::get_schema_sql()` defines registry tables with `UNIQUE KEY reg_no` on `pr_students`.
- `Install::maybe_upgrade()` runs `dbDelta` when `pr_db_version` < `PR_PLUGIN_VERSION`; idempotent when version is current.
- Renamed `includes/install.php` → `includes/Install.php` for PSR-4 autoload; explicit require on plugin activation.
- Added `StudentRepository` (`insert`, `find_by_id`) and `FakeWpdb` for unit tests.
- Tests: `StudentRepositoryTest`, `InstallUpgradeTest`, extended `InstallSchemaTest` (43 tests, all passing).

### File List

- includes/Install.php (renamed from install.php)
- includes/repositories/StudentRepository.php
- includes/class-plugin.php
- tests/FakeWpdb.php
- tests/StudentRepositoryTest.php
- tests/InstallUpgradeTest.php
- tests/InstallSchemaTest.php
- tests/bootstrap.php

## Change Log

- 2026-05-17: Student registry schema, migration idempotency, and `StudentRepository` with PHPUnit coverage.
