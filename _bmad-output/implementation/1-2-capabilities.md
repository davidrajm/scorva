# Story 1.2: Capabilities and default role mapping

Status: review

## Story

As an **administrator**,
I want Project Reviews capabilities registered with sensible defaults on activation,
So that coordinators and reviewers receive correct permissions without manual code edits.

## Acceptance Criteria

1. **Given** the plugin is activated **When** capabilities are inspected **Then** all `pr_*` capabilities from design spec §7 exist.
2. **Administrator** role receives all capabilities.
3. **Coordinator** role (`project_reviews_coordinator`) receives all except `pr_override_marks`.
4. **Reviewer** role (`project_reviews_reviewer`) receives only `pr_enter_marks`.
5. `CapabilitiesTest` passes via PHPUnit without full WordPress.

## Tasks / Subtasks

- [x] Add `includes/capabilities.php` with cap constants and `Capabilities` class
- [x] Wire `Capabilities::apply_defaults()` on plugin activation in `class-plugin.php`
- [x] Add `tests/CapabilitiesTest.php` (red-green)
- [x] Extend `tests/bootstrap.php` with role/option stubs
- [x] Run full PHPUnit suite — all green

## Dev Notes

- Implementation plan Task 3: constants `PR_CAP_*`, `Capabilities::all()`, versioned option `pr_caps_version`.
- Custom roles registered only if missing (`get_role` null → `add_role`).
- Do not add REST or UI in this story.

## Dev Agent Record

### Implementation Plan

- Added global `PR_CAP_*` defines and `Capabilities` service with `all()`, `coordinator_caps()`, and version-gated `apply_defaults()`.
- On activation: ensure custom roles, grant caps to administrator/coordinator/reviewer per spec §7.
- PHPUnit uses `WP_Role` stubs and in-memory options/roles in `tests/bootstrap.php`.

### Completion Notes

- All 9 PHPUnit tests pass (6 new capability tests + existing scaffold/schema tests).
- Story 1.1 gaps closed: `PR_PLUGIN_DIR`/`PR_PLUGIN_FILE`, `init` hook registration, guarded constant defines.

## File List

- includes/capabilities.php (new)
- includes/class-plugin.php (modified)
- project-reviews.php (modified)
- tests/CapabilitiesTest.php (new)
- tests/bootstrap.php (modified)
- tests/PluginBootstrapTest.php (modified)
- _bmad-output/implementation/1-2-capabilities.md (new)
- _bmad-output/implementation/sprint-status.yaml (new)

## Change Log

- 2026-05-16: Story created for dev-story workflow
- 2026-05-16: Implemented capabilities, roles, and tests — ready for review
