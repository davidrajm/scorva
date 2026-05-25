# Story 1.6: React SPAs, AppShell, and shared API client

Status: review

## Story

As a **coordinator or reviewer**,
I want React apps mounted on plugin routes with HashRouter and a shared API client,
So that I can navigate the product and call REST endpoints securely.

## Acceptance Criteria

1. **Given** build assets for `coordinator` and `reviewer` are enqueued on the correct routes **When** the SPA loads **Then** `AppShell` renders coordinator variant (sidebar) on `/reviews/` and reviewer variant (no sidebar) on `/reviews/mark/`.
2. **Given** the coordinator app **When** navigating **Then** HashRouter serves `#/`, `#/session/:id/wizard`, `#/session/:id/progress`, and `#/registry` (placeholders acceptable).
3. **Given** `src/shared/api.js` **When** calling REST **Then** `apiFetch` uses `wp_rest` nonce from `window.prAppData` for mutations.
4. **Given** the coordinator dashboard **When** no sessions exist (empty list or sessions endpoint unavailable) **Then** `EmptyState` is shown.
5. **Given** source imports **When** scanned **Then** no imports reference `david-sas` theme paths.
6. **Given** PHPUnit **When** the suite runs **Then** route tests assert correct JS/CSS enqueue and `prAppData` localization; full suite green.

## Tasks / Subtasks

- [x] Add `react-router-dom` and `@wordpress/api-fetch` dependencies
- [x] Implement `src/shared/api.js` (`configureApi`, `get`)
- [x] Implement coordinator SPA: mount, `HashRouter`, `AppShell`, dashboard + placeholder routes
- [x] Implement reviewer SPA: mount, `AppShell`, assignments placeholder with `EmptyState`
- [x] Extend `Routes::enqueue_app_assets()` — enqueue built JS/CSS + `wp_localize_script` (`restUrl`, `nonce`)
- [x] Extend `tests/bootstrap.php` and `tests/RoutesTest.php` for script enqueue and localization
- [x] Run `npm run build` and full PHPUnit — all green

## Dev Notes

### Scope boundary

| In scope (1.6) | Deferred |
|----------------|----------|
| React mount on `#pr-root` | Session CRUD REST (Epic 3) |
| HashRouter + placeholder pages | Full wizard/registry UI |
| `api.js` + `prAppData` | Domain routes beyond health |
| Dashboard `EmptyState` | E2E checklist |

Story 1.5 deferred JS enqueue — this story adds it. Enqueue **both** `assets/css/app-shell.css` and webpack `build/{app}.css`.

### PHP enqueue pattern

```php
wp_enqueue_script('project-reviews-coordinator', plugins_url('build/coordinator.js', PR_PLUGIN_FILE), $deps, $ver, true);
wp_localize_script('project-reviews-coordinator', 'prAppData', [
    'restUrl' => rest_url('project-reviews/v1/'),
    'nonce'   => wp_create_nonce('wp_rest'),
]);
```

Priority **100000** (after `strip_theme_assets` at 99999).

### HashRouter routes (coordinator)

- `#/` — Dashboard (session list or EmptyState)
- `#/session/:id/wizard` — placeholder
- `#/session/:id/progress` — placeholder
- `#/registry` — placeholder

### References

- [Source: _bmad-output/planning/epics.md — Story 1.6]
- [Source: david-sas/docs/superpowers/plans/2026-05-16-project-reviews-plugin.md — Task 12 Step 3, Task 13]
- [Source: _bmad-output/implementation/1-5-design-tokens.md — deferred mount]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Coordinator and reviewer SPAs mount on `#pr-root` with `AppShell` variants (sidebar vs top-bar only).
- `HashRouter` routes: `#/`, `#/session/:id/wizard`, `#/session/:id/progress`, `#/registry` (placeholders for non-dashboard routes).
- `src/shared/api.js` configures `@wordpress/api-fetch` with `prAppData.restUrl` and `wp_rest` nonce middleware.
- Dashboard fetches `GET /sessions`; empty or unavailable endpoint shows `EmptyState` with disabled “Create session” CTA.
- `Routes::enqueue_app_assets($app)` enqueues app-shell CSS, webpack JS/CSS, and `wp_localize_script` at priority 100000.
- `npm run build` OK; PHPUnit 38 tests, 143 assertions, all green.

## File List

- package.json
- package-lock.json
- src/shared/api.js
- src/coordinator/index.js
- src/coordinator/App.jsx
- src/coordinator/CoordinatorNav.jsx
- src/coordinator/pages/Dashboard.jsx
- src/coordinator/pages/SessionPlaceholder.jsx
- src/coordinator/pages/RegistryPlaceholder.jsx
- src/reviewer/index.js
- src/reviewer/App.jsx
- includes/routes.php
- tests/bootstrap.php
- tests/RoutesTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css
- build/reviewer.asset.php

## Change Log

- 2026-05-16: Story created and implementation started (dev-story)
- 2026-05-16: Implemented React SPAs, API client, asset enqueue, tests — review
