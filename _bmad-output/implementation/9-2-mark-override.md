# Story 9.2: Mark override with mandatory reason

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **administrator**,
I want to override marks with a required reason,
So that changes are fair and auditable.

## Acceptance Criteria

1. **Given** a user with `pr_override_marks` **When** they enable override mode on a mark row, edit score, and provide reason ≥ 10 characters **Then** mark updates and audit row is created **When** reason is missing or too short **Then** submit is blocked with inline validation **And** override UI uses textarea with `aria-required`

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Marks + audit infrastructure from prior epics.

### Files / patterns
- `AuditService` append-only; `pr_mark_audit`.
- WP Admin settings: native WP UI only (UX-DR34).
### Previous story
Continue from `_bmad-output/implementation/9-1-audit-service.md` patterns.


**Covers:** FR23; UX-DR21

### References

- [Source: _bmad-output/planning/epics.md — Story 9.2]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
