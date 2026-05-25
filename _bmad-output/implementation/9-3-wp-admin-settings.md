# Story 9.3: WP Admin settings and capability documentation

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **administrator**,
I want a WordPress admin settings page for email and capabilities,
So that I can configure the plugin without editing code.

## Acceptance Criteria

1. **Given** a user with `pr_manage_settings` **When** they open the plugin settings under WP Admin **Then** they can set from name, reply-to, and base login URL **And** capability default documentation is visible (native WP admin styles, UX-DR34) **And** settings persist in options table **And** invite emails use configured from/reply-to (FR25, NFR17)

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
Continue from `_bmad-output/implementation/9-2-mark-override.md` patterns.

### WP Admin
`add_options_page`; no Tailwind on admin screens.

**Covers:** FR25; UX-DR34

### References

- [Source: _bmad-output/planning/epics.md — Story 9.3]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
