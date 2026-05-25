# Story 1.8: Plugin deactivation and uninstall data policy

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **site administrator**,
I want predictable behaviour when Project Reviews is deactivated or deleted from WordPress,
So that review marks and registry data are not lost by accident, but I can fully remove plugin data when I explicitly choose to.

## Acceptance Criteria

### 1. Product decisions documented in code and admin UI

- **Given** the plugin is **deactivated** (Plugins → Deactivate)
- **When** deactivation runs
- **Then** only **non-destructive** teardown occurs: flush rewrite rules (existing behaviour)
- **And** all custom tables, the `pr_rubric_scores` view, options, capabilities on roles, and custom roles **remain**
- **And** WordPress user accounts (including session-provisioned reviewers) are **not** modified, disabled, or deleted
- **And** re-activating the plugin restores routes and schema via existing `activate()` / `Install::maybe_upgrade()` paths

- **Given** the plugin is **deleted** from the Plugins screen (WordPress uninstall)
- **When** the administrator has **not** enabled “Remove all Project Reviews data on uninstall” in WP Admin settings
- **Then** uninstall performs **no** `DROP TABLE`, **no** option deletion, **no** capability removal
- **And** orphaned `pr_*` tables and options remain for manual recovery or plugin reinstall

- **Given** the administrator **has** enabled “Remove all Project Reviews data on uninstall” **before** deleting the plugin
- **When** WordPress runs uninstall
- **Then** the plugin drops the `pr_rubric_scores` **view** first, then all `pr_*` **tables** listed in Dev Notes
- **And** deletes plugin **options** listed in Dev Notes
- **And** removes all `pr_*` capabilities from every role that has them
- **And** removes custom roles `project_reviews_coordinator` and `project_reviews_reviewer` **only when** no users are assigned to that role (use `count_users` / equivalent; do not force-delete users)
- **And** does **not** delete or bulk-disable WordPress user accounts (align with NFR15 — session close owns account lifecycle)

### 2. Deactivation hook (minimal change)

- **Given** `register_deactivation_hook` in `project-reviews.php`
- **When** `Plugin::deactivate()` runs
- **Then** it calls `flush_rewrite_rules()` (keep current behaviour)
- **And** inline docblock or `Plugin::deactivate()` comment states data is intentionally preserved
- **And** no new destructive logic is added to deactivation

### 3. Uninstall implementation

- **Given** WordPress delete-plugin flow
- **When** uninstall executes
- **Then** root `uninstall.php` exists, guards with `defined('WP_UNINSTALL_PLUGIN') || exit`, loads `vendor/autoload.php` only if needed, and delegates to `ProjectReviews\Uninstall::run()`
- **And** `includes/Uninstall.php` centralizes teardown; `Install` may expose `drop_all()` / `get_table_names()` reused by uninstall (avoid duplicating table list in two places)
- **And** uninstall reads `get_option('pr_delete_data_on_uninstall', false)` — **must** be a top-level option (not only nested inside `pr_plugin_settings`) so uninstall still works after partial option cleanup
- **And** capability removal is implemented in `Capabilities::remove_from_all_roles()` (or equivalent) inverse of `apply_defaults()`

### 4. WP Admin settings (extends 9-3)

- **Given** a user with `pr_manage_settings` on **Settings → Project Reviews**
- **When** they view the settings page
- **Then** a clearly worded checkbox appears: **“Remove all Project Reviews data when uninstalling the plugin”** (default **unchecked**)
- **And** help text explains: deactivating keeps data; deleting the plugin removes data **only** if this box was checked **before** delete; recommends backup
- **And** saving settings persists `pr_delete_data_on_uninstall` as boolean via `PluginSettings` or dedicated option helper
- **And** native WP admin styles only (UX-DR34)

### 5. Tests

- **And** `UninstallTest` (or `PluginLifecycleTest`) covers:
  - `test_deactivate_does_not_drop_tables` — stub `$wpdb` / table_exists; deactivate; tables still “exist”
  - `test_uninstall_skips_drop_when_option_false` — option false; `Uninstall::run()` does not call drop helpers
  - `test_uninstall_drops_when_option_true` — option true; assert drop SQL or mock calls for view + tables + `delete_option` list
  - `test_uninstall_removes_caps_from_administrator` — when opt-in true, administrator loses `pr_manage_sessions` etc.
- **And** `PluginBootstrapTest` still passes; `composer test` passes

## Tasks / Subtasks

- [x] **Product / docs:** Add “Lifecycle” subsection to settings page copy (deactivate vs delete vs opt-in wipe) (AC: 1, 4)
- [x] **Settings:** `pr_delete_data_on_uninstall` option + checkbox on `Admin_Settings` (AC: 4)
- [x] **Install:** `Install::get_pr_table_names( string $prefix ): array` and `Install::drop_all( object $wpdb ): void` — DROP VIEW `pr_rubric_scores`, then tables in safe order (children before parents where applicable; plugin uses no FK constraints — document order in method) (AC: 3)
- [x] **Capabilities:** `Capabilities::remove_from_all_roles()` + optional `remove_custom_roles_if_empty()` (AC: 1, 3)
- [x] **Uninstall:** `includes/Uninstall.php` + root `uninstall.php` (AC: 3)
- [x] **Deactivate:** Document preserve-data intent on `Plugin::deactivate()` (AC: 2)
- [x] **Tests:** `tests/UninstallTest.php`; extend `tests/bootstrap.php` stubs if needed (`delete_option`, `count_users`, `$wpdb->query`) (AC: 5)
- [x] Run `composer test` (no front-end build required unless settings markup shared)

## Dev Notes

### User request (source)

**Decide what to do on plugin deactivation and delete** — implement explicit, safe defaults for an academic marks plugin with audit history.

### Current behaviour (baseline)

```73:76:includes/class-plugin.php
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
```

- **Activation** (`Plugin::activate()`): schema upgrade, capabilities, rewrites — keep unchanged.
- **No** `uninstall.php`, **no** `register_uninstall_hook`, **no** deactivation data policy in settings.
- Error copy in `class-rest-sessions.php` already tells admins to deactivate/reactivate to fix schema — reinforces that deactivation must remain **safe** and **reversible**.

### Recommended policy matrix (implement exactly)

| Event | Tables / view | Options | `pr_*` caps on roles | Custom roles | WP users | Rewrites |
|-------|----------------|---------|----------------------|--------------|----------|----------|
| **Deactivate** | Keep | Keep | Keep | Keep | Untouched | Flush |
| **Delete** (default) | Keep | Keep | Keep | Keep | Untouched | N/A |
| **Delete** + opt-in | Drop all | Delete listed | Remove from all roles | Remove if empty | **Do not delete** | N/A |

**Rationale:** Marks and audit rows are institutional records; accidental plugin delete must not wipe a semester. Explicit opt-in matches common WP plugin practice (WooCommerce, etc.). User disable remains **session close** (`SessionCloseService`, NFR15), not uninstall.

### Database objects to drop (opt-in uninstall only)

**View:** `{prefix}pr_rubric_scores`

**Tables** (from `Install::get_schema_sql()` + patches — single source of truth via new helper):

| Table | Notes |
|-------|--------|
| `pr_mark_audit` | Audit trail |
| `pr_marks` | Core marks |
| `pr_unfreeze_requests` | Reviewer unfreeze |
| `pr_panel_unfreeze_requests` | Panel unfreeze |
| `pr_review_panel_freezes` | Freeze state |
| `pr_review_student_attendance_by_reviewer` | Attendance |
| `pr_review_student_panels` | Assignments |
| `pr_review_panel_reviewers` | Per-review roster |
| `pr_review_reviewer_overrides` | Overrides |
| `pr_reviewer_weights` | Weights |
| `pr_review_weights` | Weights |
| `pr_rubric_criteria` | Rubric |
| `pr_reviews` | Review rounds |
| `pr_session_reviewers` | Provisioned reviewers |
| `pr_panel_reviewers` | Panel roster |
| `pr_session_students` | Enrolment |
| `pr_panels` | Panels |
| `pr_sessions` | Projects |
| `pr_student_meta` | Registry meta |
| `pr_field_definitions` | Field defs |
| `pr_students` | Registry |

Use `$wpdb->query('DROP TABLE IF EXISTS ...')` per table; `DROP VIEW IF EXISTS` for the view. No `TRUNCATE` — full drop only when opted in.

### Options to delete (opt-in uninstall only)

| Option | Purpose |
|--------|---------|
| `pr_db_version` | Schema version |
| `pr_caps_version` | Cap mapping version |
| `pr_rewrite_version` | Rewrite flush tracking |
| `pr_plugin_settings` | Email / login settings |
| `pr_delete_data_on_uninstall` | This flag (delete last) |
| `pr_review_assignments_backfilled` | One-time migration flag |
| `pr_review_panel_reviewers_backfill_v1` | One-time migration flag |

### Capabilities and roles (from `includes/capabilities.php`)

Remove all caps in `Capabilities::all()` from **every** role returned by `wp_roles()->roles` (not only administrator/coordinator/reviewer).

Custom roles:

- `project_reviews_coordinator` (`Capabilities::ROLE_COORDINATOR`)
- `project_reviews_reviewer` (`Capabilities::ROLE_REVIEWER`)

Call `remove_role()` only when `count_users(['role' => $role_id]) === 0`.

### Files to create / modify

| File | Action |
|------|--------|
| `uninstall.php` | **Create** — WP uninstall entry |
| `includes/Uninstall.php` | **Create** — `run()`, read opt-in flag |
| `includes/Install.php` | **Extend** — table list + `drop_all()` |
| `includes/capabilities.php` | **Extend** — `remove_from_all_roles()` |
| `includes/class-plugin.php` | **Extend** — deactivation docblock only |
| `includes/services/PluginSettings.php` | **Optional** — helper for delete-on-uninstall flag |
| `includes/admin/class-admin-settings.php` | **Extend** — checkbox + copy |
| `tests/UninstallTest.php` | **Create** |
| `tests/bootstrap.php` | **Extend** stubs as needed |

**Do not** add destructive logic to `Plugin::deactivate()`.

### WordPress uninstall conventions

- `uninstall.php` runs only on plugin **delete**, not deactivate ([WP Plugin Handbook — Uninstall](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/)).
- Check `WP_UNINSTALL_PLUGIN` constant.
- Do not rely on full WordPress admin UI during uninstall — keep logic self-contained in `Uninstall::run()`.
- Autoload: `require_once __DIR__ . '/vendor/autoload.php';` before namespaced classes (same as `project-reviews.php`).

### Architecture compliance

- PSR-4 `ProjectReviews\` under `includes/`.
- PHPUnit via `tests/bootstrap.php` stubs (no full WP install).
- No theme imports (NFR3).
- WP Admin settings: native WP UI (UX-DR34) — matches story `9-3`.

### Previous story intelligence

- **1-1:** Activation hooks and `PluginBootstrapTest` — extend lifecycle tests, do not break bootstrap test.
- **1-2:** `Capabilities::apply_defaults()` on activation — uninstall must symmetrically remove caps; re-activation must remain idempotent.
- **9-3:** Settings page pattern — add uninstall checkbox in same form/table; `register_setting` / `PluginSettings::OPTION_KEY` or separate option.

### Testing notes

- Mock `$wpdb->query` and capture SQL for drop tests, or use test doubles for `Install::drop_all()`.
- Verify **double-run** uninstall (option true) does not fatal if tables already missing — use `DROP TABLE IF EXISTS` / `DROP VIEW IF EXISTS`.
- No `npm run build` unless you change shared React assets (not expected).

### Out of scope

- Multisite network activation policies (document as future if needed; single-site MVP).
- Export/backup before uninstall (admin copy only recommends backup).
- Deleting provisioned WP users on uninstall.
- Cron cleanup (plugin has no scheduled events).

### References

- [Source: _bmad-output/planning/epics.md — Epic 1 foundation]
- [Source: _bmad-output/implementation/1-1-plugin-scaffold.md]
- [Source: _bmad-output/implementation/1-2-capabilities.md]
- [Source: _bmad-output/implementation/9-3-wp-admin-settings.md]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §5 tables, §7 capabilities, §8 session close]
- [Source: includes/class-plugin.php — activate/deactivate]
- [Source: includes/Install.php — schema]
- [WordPress Plugin Handbook — Uninstall methods](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/)

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

- PHPUnit: `./vendor/bin/phpunit` — 327 tests, 1471 assertions, OK

### Completion Notes List

- Deactivation remains non-destructive (`flush_rewrite_rules` only) with docblock on `Plugin::deactivate()`.
- Opt-in uninstall via top-level `pr_delete_data_on_uninstall`; default delete leaves all `pr_*` data intact.
- `Install::get_pr_table_names()` / `drop_all()` centralize view + table drops; `Uninstall::run()` deletes options and removes caps/empty custom roles.
- Settings → Project Reviews adds Lifecycle copy and uninstall checkbox (native WP admin UI).
- `UninstallTest` covers deactivate preservation, skip-drop, full drop, and cap removal.

### File List

- `uninstall.php` (new)
- `includes/Uninstall.php` (new)
- `includes/Install.php`
- `includes/capabilities.php`
- `includes/class-plugin.php`
- `includes/services/PluginSettings.php`
- `includes/admin/class-admin-settings.php`
- `tests/UninstallTest.php` (new)
- `tests/bootstrap.php`
- `tests/FakeWpdb.php`

### Change Log

- 2026-05-21: Story 1.8 — plugin deactivation/uninstall data policy, settings checkbox, PHPUnit lifecycle tests.
