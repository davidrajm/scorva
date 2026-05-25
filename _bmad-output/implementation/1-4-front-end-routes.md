# Story 1.4: Front-end routes and minimal PHP app shell

Status: review

## Story

As a **coordinator or reviewer**,
I want dedicated URLs that load a minimal plugin shell without theme header/footer,
So that the experience feels like a standalone product.

## Acceptance Criteria

1. **Given** rewrite rules are flushed after activation **When** capabilities are set up **Then** rewrite rules for `^reviews/?$` and `^reviews/mark/?$` are registered with query var `pr_app`.
2. **Given** an unauthenticated visitor **When** they hit a plugin route **Then** `auth_redirect()` runs (login required).
3. **Given** a logged-in user **When** they visit `/reviews/` (`pr_app=coordinator`) **Then** `templates/app-shell.php` renders with `#pr-root` and `data-app="coordinator"` without theme `get_header()` / `get_footer()`.
4. **Given** a logged-in user **When** they visit `/reviews/mark/` (`pr_app=reviewer`) **Then** the same shell loads with `data-app="reviewer"`.
5. **Given** a plugin route request **When** assets enqueue **Then** theme-registered styles/scripts are dequeued (NFR3); do not enqueue React or `app-shell.css` in this story (stories 1.5–1.6).
6. `RoutesTest` passes via PHPUnit without full WordPress.

## Tasks / Subtasks

- [x] Add `includes/routes.php` with `Routes` class (rewrites, query var, `template_redirect`, asset stripping)
- [x] Add `templates/app-shell.php` minimal HTML shell with `#pr-root`
- [x] Wire `Routes::register_hooks()` in `includes/class-plugin.php`
- [x] Extend `tests/bootstrap.php` with rewrite/query/auth/template stubs
- [x] Add `tests/RoutesTest.php` (red-green)
- [x] Run full PHPUnit suite — all green

## Dev Notes

- Implementation plan Task 12: `add_rewrite_rule('^reviews/?$', 'index.php?pr_app=coordinator', 'top')` and mark variant.
- Query var: `pr_app` values `coordinator` | `reviewer`.
- `template_redirect` loads shell and `exit`; never call theme header/footer.
- `Plugin::activate()` already calls `flush_rewrite_rules()`.
- Reuse login check only (no capability gate in this story — AC says authenticated user).
- Do **not** add `assets/css/app-shell.css`, React builds, or `wp_localize_script` (stories 1.5, 1.6).
- Strip foreign assets on `wp_enqueue_scripts` priority 99999 by dequeuing all queued handles (no plugin assets enqueued yet).

### References

- [Source: _bmad-output/planning/epics.md — Story 1.4]
- [Source: david-sas/docs/superpowers/plans/2026-05-16-project-reviews-plugin.md — Task 12]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Implementation Plan

- `Routes` registers rewrite rules, `pr_app` query var, and `template_redirect` handler.
- Logged-out visitors get `auth_redirect()` then return; logged-in users load `templates/app-shell.php` with `$pr_app` and `#pr-root`.
- `strip_theme_assets()` dequeues all queued styles/scripts on plugin routes (NFR3).
- No React/CSS enqueue in this story (deferred to 1.5–1.6).

### Completion Notes

- 31 PHPUnit tests pass (7 new route tests + existing suite).
- Activation already flushes rewrite rules; visit Settings → Permalinks once if routes 404 on existing installs.

## File List

- includes/routes.php (new)
- templates/app-shell.php (new)
- includes/class-plugin.php (modified)
- tests/RoutesTest.php (new)
- tests/bootstrap.php (modified)
- _bmad-output/implementation/1-4-front-end-routes.md (new)
- _bmad-output/implementation/sprint-status.yaml (modified)

## Change Log

- 2026-05-16: Story created for dev-story workflow
- 2026-05-16: Implemented front-end routes, app shell template, and tests — ready for review
