# Story 2.3: Registry UI with search and empty state

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want a registry screen to browse, search, and edit students,
So that I can maintain records before enrolling them in sessions.

## Acceptance Criteria

1. **Given** the coordinator navigates to `#/registry` **When** students exist **Then** a searchable table lists students with core and custom columns **And** search is debounced 300ms (UX-DR29) **When** no students exist **Then** EmptyState displays with CTA to import or add first student **And** create/edit form uses labels above inputs with `aria-required` on required fields

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed) — N/A UI-only
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable) — from 2.2
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
Continue from `_bmad-output/implementation/2-2-student-registry-rest.md` patterns.


**Covers:** FR1, FR26; UX-DR17, UX-DR21, UX-DR29

### References

- [Source: _bmad-output/planning/epics.md — Story 2.3]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Replaced registry placeholder with `Registry.jsx` at `#/registry`.
- Debounced search (300ms) via `useDebouncedValue`; table shows core + custom columns.
- EmptyState with Import CSV / Add first student CTAs when registry is empty.
- `StudentForm` with labels above inputs and `aria-required` on reg_no and name.
- `npm run build` successful.

### File List

- src/coordinator/pages/Registry.jsx
- src/coordinator/components/StudentForm.jsx
- src/shared/hooks/useDebouncedValue.js
- src/shared/api.js
- src/coordinator/App.jsx
- src/coordinator/pages/RegistryPlaceholder.jsx (removed)

## Change Log

- 2026-05-17: Registry browse/search/edit UI with empty state and accessible student form.
