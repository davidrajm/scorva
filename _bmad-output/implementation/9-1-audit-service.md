# Story 9.1: AuditService, audit REST, and audit UI

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **administrator**,
I want an append-only audit log of governance actions,
So that overrides and session events are traceable.

## Acceptance Criteria

1. **Given** table `pr_mark_audit` (or `pr_audit`) exists **When** rubric unlock, re-confirm, override, session close, or account disable occurs **Then** `AuditService` appends a row with actor, action, entity, old/new values, timestamp **When** authorized user opens audit view **Then** paginated log displays with EmptyState when empty **And** audit log is included as sixth export report type

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

**Covers:** FR24; FR20 audit report

### References

- [Source: _bmad-output/planning/epics.md — Story 9.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
