# Story 9.4: Optional rubric-open and session-closed emails

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **administrator**,
I want optional notification emails on rubric confirm and session close,
So that stakeholders are informed when enabled.

## Acceptance Criteria

1. **Given** settings toggles for rubric-open and session-closed emails **When** toggles are off **Then** no notification emails are sent on those events **When** toggles are on and coordinator confirms rubric or closes session **Then** configured templates send to appropriate recipients **And** emails use plugin-branded HTML (not theme templates)

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
Continue from `_bmad-output/implementation/9-3-wp-admin-settings.md` patterns.


**Covers:** FR30; NFR17

### References

- [Source: _bmad-output/planning/epics.md — Story 9.4]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
