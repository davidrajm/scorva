# Story 1.3: REST auth helpers and API bootstrap

Status: review

## Story

As a **client application**,
I want authenticated REST endpoints with shared permission helpers,
So that every future route enforces login, nonce, and capability checks consistently.

## Acceptance Criteria

1. **Given** a logged-out user **When** they call any `project-reviews/v1` route **Then** the response is 401 or 403 as appropriate.
2. **Given** a logged-in user without the required capability **When** they call a protected route **Then** the response is 403 with error code `rest_forbidden`.
3. **Given** a logged-in user with any `pr_*` capability **When** they call `GET /project-reviews/v1/health` **Then** the response is 200 with plugin version.
4. **Given** a mutation without valid `wp_rest` nonce **When** verified via `Rest_Auth::verify_rest_nonce` **Then** the response is 403 with `rest_cookie_invalid_nonce`.
5. `RestAuthTest` and `HealthEndpointTest` pass via PHPUnit without full WordPress.

## Tasks / Subtasks

- [x] Add `includes/rest/class-rest-auth.php` with `require_logged_in`, `require_cap`, `require_any_pr_cap`, `verify_rest_nonce`
- [x] Add `includes/rest/class-rest-bootstrap.php` registering `GET project-reviews/v1/health`
- [x] Wire `rest_api_init` in `includes/class-plugin.php`
- [x] Extend `tests/bootstrap.php` with REST/user/nonce stubs
- [x] Add `tests/RestAuthTest.php` (logged-out, missing cap, invalid nonce)
- [x] Add `tests/HealthEndpointTest.php` (health payload + permission)
- [x] Run full PHPUnit suite — all green

## Dev Notes

- Namespace `ProjectReviews`; require REST files from `Plugin::init()` (lowercase `rest/` folder is not PSR-4 autoloaded).
- Permission helpers return `callable` suitable for `permission_callback` on `register_rest_route`.
- Health route uses `Rest_Auth::require_any_pr_cap()` — not public/anonymous.
- Do not add domain routes (students, sessions, etc.) in this story.
- Reuse `Capabilities::all()` from story 1.2.
- Error codes: `rest_not_logged_in` (401), `rest_forbidden` (403), `rest_cookie_invalid_nonce` (403).

## Dev Agent Record

### Implementation Plan

- `Rest_Auth` provides composable permission callbacks: login, capability, any `pr_*` cap, and `wp_rest` nonce verification via `X-WP-Nonce`.
- `with_rest_nonce()` wraps mutation permission callbacks for future POST/PUT/PATCH routes.
- `Rest_Bootstrap` registers `GET project-reviews/v1/health` returning `{ status, version }`.
- `Plugin::init()` loads REST classes and hooks `rest_api_init`.

### Completion Notes

- 24 PHPUnit tests pass (10 new REST tests + existing suite).
- Health endpoint requires login plus at least one `pr_*` capability (not anonymous).

## File List

- includes/rest/class-rest-auth.php (new)
- includes/rest/class-rest-bootstrap.php (new)
- includes/class-plugin.php (modified)
- tests/bootstrap.php (modified)
- tests/RestAuthTest.php (new)
- tests/HealthEndpointTest.php (new)
- _bmad-output/implementation/1-3-rest-auth.md (new)
- _bmad-output/implementation/sprint-status.yaml (modified)

## Change Log

- 2026-05-16: Story created for dev-story workflow
- 2026-05-16: Implemented REST auth helpers, health bootstrap route, and tests — ready for review
