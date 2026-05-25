# Story 1.1: Plugin scaffold and activation hooks

Status: done

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **developer**,
I want the plugin bootstrap, autoloading, and activation hooks in place,
So that all subsequent features build on a testable WordPress plugin foundation.

## Acceptance Criteria

1. **Given** the plugin directory is present under `wp-content/plugins/project-reviews/` **When** the plugin is activated in WordPress **Then** constants `PR_PLUGIN_VERSION`, `PR_PLUGIN_SLUG`, `PR_PLUGIN_DIR`, and `PR_PLUGIN_FILE` are defined **And** `Plugin::instance()` registers on `init` **And** PHPUnit test `PluginBootstrapTest` passes without a full WordPress install (stub load) **And** `composer.json` defines PSR-4 autoload for `ProjectReviews\` namespace

## Tasks / Subtasks

- [ ] Implement acceptance criteria
- [ ] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [ ] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [ ] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [ ] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
Epic 1 foundation: REST auth, capabilities, routes, design tokens, SPAs (stories 1.2–1.6).

### Architecture compliance
- REST namespace `project-reviews/v1`; `Rest_Auth` on all routes.
- No `david-sas` theme imports (NFR3).
- PSR-4 `ProjectReviews\` under `includes/`.
- PHPUnit via `tests/bootstrap.php` stubs.
### Scope
Bootstrap only — no domain tables.

Implement: `project-reviews.php`, `includes/class-plugin.php`, `composer.json`, `PluginBootstrapTest.php`.

**Covers:** Additional scaffold requirement; FR28 foundation; NFR1

### References

- [Source: _bmad-output/planning/epics.md — Story 1.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
