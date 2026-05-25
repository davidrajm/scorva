# Story 1.7: Shared login landing and workspace routing

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator or reviewer**,
I want a single Project Reviews landing page where I sign in and am sent to the workspace I am allowed to use,
So that invite links and bookmarks share one entry URL and I am not dropped into the wrong app or WordPress admin.

## Acceptance Criteria

### 1. Unified entry URL — `/reviews/` when logged out

- **Given** rewrite rules from `1-4-front-end-routes` (`^reviews/?$` → `pr_app=coordinator`)
- **When** a visitor is **not** logged in and requests `/reviews/` (or `/reviews` without trailing path segment beyond root)
- **Then** the plugin renders a **landing** experience (minimal shell, no coordinator or reviewer SPA bundles)
- **And** the page shows Project Reviews branding (wordmark, Direction 1 tokens from `1-5-design-tokens`), short orienting copy, and a primary **Log in** control
- **And** the page does **not** call `auth_redirect()` immediately (guests see the landing first)
- **And** theme assets remain stripped (NFR3); only `app-shell.css` + `build/landing.css` (and `build/landing.js` if used) are enqueued
- **And** `templates/app-shell.php` loads with `data-app="landing"` on `#pr-root`

### 2. Log in CTA and post-login return

- **Given** the landing page for a guest
- **When** they activate **Log in**
- **Then** they go to `PluginSettings::login_url_with_redirect( home_url('/reviews/') )` (settings-aware base login URL + `redirect_to` back to the landing)
- **When** they complete WordPress authentication successfully
- **Then** they return to `/reviews/` and server-side routing (AC 3) sends them to the correct workspace without manual URL editing

### 3. Post-login routing from `/reviews/` (server is source of truth)

- **Given** a logged-in user at `/reviews/` (coordinator rewrite / `pr_app=coordinator` path)
- **When** they have **only** reviewer workspace access (`PR_CAP_ENTER_MARKS` and **no** coordinator workspace caps per `Capabilities::user_has_coordinator_workspace_access()`)
- **Then** `Routes::handle_template()` issues `wp_safe_redirect( home_url('/reviews/mark/') )` and does **not** enqueue `build/coordinator.js`
- **When** they have coordinator workspace access and **not** `PR_CAP_ENTER_MARKS`
- **Then** load the coordinator SPA as today (enqueue `build/coordinator.js`, `data-app="coordinator"`)
- **When** they have **both** coordinator and reviewer access (e.g. administrator or custom role with both cap sets)
- **Then** show the **landing chooser** (same landing bundle, logged-in variant): two actions — **Coordinator workspace** → remain on `/reviews/` with coordinator SPA OR navigate to coordinator hash home; **Marking workspace** → full navigation to `home_url('/reviews/mark/')`
- **And** chooser is the only case where logged-in users see the landing UI instead of an immediate redirect or SPA
- **When** they have **neither** workspace
- **Then** respond with `403` and translatable message (reuse `Routes::deny_workspace_access()` pattern from `5-16-reviewer-header-auth-route-guard`)

### 4. Guest access to `/reviews/mark/` and deep coordinator paths

- **Given** a guest requests `pr_app=reviewer` (`/reviews/mark/`)
- **When** `handle_template()` runs
- **Then** redirect to `home_url('/reviews/')` (landing), **not** `auth_redirect()` to wp-login
- **Given** a guest requests a coordinator catch-all path (`/reviews/registry`, etc.)
- **When** `handle_template()` runs
- **Then** redirect to `home_url('/reviews/')` (landing)
- **Given** logged-in users on `/reviews/mark/` or coordinator deep paths
- **Then** existing workspace guards from story `5-16` remain unchanged (reviewer-only blocked from coordinator SPA; coordinators without `pr_enter_marks` redirected from mark app)

### 5. Landing UI content and accessibility

- **Given** the landing bundle mounts in `#pr-root`
- **When** the guest view renders
- **Then** copy includes: headline **Project Reviews**, one sentence explaining sign-in is required for coordinator or reviewer workspaces, primary button **Log in**
- **When** the dual-workspace chooser renders (logged-in, both caps)
- **Then** show two `Card` (or `ReportCard`-sized) actions with titles **Coordinator workspace** / **Marking workspace**, one-line descriptions, and links using full URLs (`home_url('/reviews/')`, `home_url('/reviews/mark/')`)
- **And** layout uses centered column max ~480px (reviewer shell width sensibility), top bar with wordmark only (no sidebar)
- **And** WCAG: focusable controls, visible focus rings, `main` landmark, skip link via shared `AppShell` or equivalent landing-only shell
- **And** respect `prefers-reduced-motion` (NFR14) — no decorative animation on landing

### 6. Invite emails and login redirect alignment

- **Given** `ReviewerProvisionService` sends invite / resend emails
- **When** building `login_url` for reviewers
- **Then** use `PluginSettings::login_url_with_redirect( home_url('/reviews/') )` instead of redirecting straight to `/reviews/mark/` (reviewers still reach mark home via AC 3 after login)
- **Given** `WorkspaceAccess::filter_login_redirect()` for reviewer-only users
- **When** `redirect_to` / `requested_redirect_to` is empty or wp-admin
- **Then** continue sending them to `Capabilities::workspace_home_url_for_user()` (mark home) **or** landing URL — **prefer landing** `home_url('/reviews/')` so behaviour matches AC 3 (update `workspace_home_url_for_user` only if needed for reviewer-only default; coordinator default stays `/reviews/`)
- **And** document in dev notes: site-wide **base login URL** in WP Admin settings (`9-3`) remains the WordPress login page; landing is the **app entry**, not a replacement for wp-login

### 7. `prAppData` and build pipeline

- **Given** webpack config in `webpack.config.js`
- **When** `npm run build` runs
- **Then** a third entry `landing` produces `build/landing.js`, `build/landing.css`, `build/landing.asset.php`
- **And** `Routes::enqueue_app_assets('landing')` localizes at minimum:
  ```php
  'loginUrl' => PluginSettings::login_url_with_redirect(home_url('/reviews/')),
  'appHomeUrl' => home_url('/reviews/'),
  'coordinatorHomeUrl' => home_url('/reviews/'),
  'markingHomeUrl' => home_url('/reviews/mark/'),
  'canAccessCoordinator' => Capabilities::user_has_coordinator_workspace_access(),
  'canAccessMarking' => current_user_can(PR_CAP_ENTER_MARKS),
  ```
- **And** include `currentUser` when logged in (for chooser greeting optional: “Signed in as …”)
- **And** do **not** expose REST nonce on guest landing unless a future need arises (no API calls on guest view)

### 8. Tests and regression

- **And** `RoutesTest` updates / adds:
  - `test_guest_coordinator_route_renders_landing_not_auth_redirect` — logged out, `pr_app=coordinator` → template `landing`, `auth_redirect` **not** called
  - `test_guest_reviewer_route_redirects_to_landing` — logged out, `pr_app=reviewer` → redirect `/reviews/`
  - `test_reviewer_only_logged_in_at_reviews_root_redirects_to_mark` — only `pr_enter_marks` → redirect mark, no coordinator JS
  - `test_dual_access_logged_in_at_reviews_root_renders_landing_chooser` — both cap sets → `landing` app, not coordinator bundle
  - `test_coordinator_only_logged_in_at_reviews_root_renders_coordinator` — unchanged behaviour
- **And** `WorkspaceAccessTest` updated if login redirect target changes to landing for reviewer-only
- **And** `composer test` and `npm run build` pass

## Tasks / Subtasks

- [x] **Routes:** Guest landing vs logged-in branch in `handle_template()` for coordinator root; guest redirects from reviewer + coordinator deep paths (AC: 1, 3, 4)
- [x] **Routes:** `enqueue_app_assets('landing')` + allow `pr_app` / template `landing` (AC: 1, 7)
- [x] **Webpack + landing SPA:** `src/landing/index.js`, `App.jsx` — guest + chooser views; reuse `AppShell` or slim `LandingShell` (AC: 5, 7)
- [x] **Shared components:** Use `Button`, `Card`, `PageHeader` from `src/shared/components/` (AC: 5)
- [x] **Emails / provision:** Reviewer invite `login_url` → landing redirect (AC: 6)
- [x] **WorkspaceAccess (optional):** Reviewer-only `login_redirect` → `/reviews/` when appropriate (AC: 6)
- [x] **Tests:** `RoutesTest`, `WorkspaceAccessTest` (AC: 8)
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

Create a **landing page for all reviewers and coordinators**. By logging in from there, users go to **different pages according to whatever access they have**.

### Problem today

```50:54:includes/routes.php
        if (!is_user_logged_in()) {
            auth_redirect();

            return;
        }
```

- Guests hitting `/reviews/` or `/reviews/mark/` are sent straight to WordPress login with **no** plugin-branded entry.
- Reviewer invite emails use `login_url_with_redirect( home_url('/reviews/mark/') )`, bypassing a shared entry (`ReviewerProvisionService`).
- Coordinators and reviewers have **different** bookmark URLs; institution wants **one** link (e.g. on website) → `/reviews/`.

### Recommended architecture (minimal URL churn)

| URL | Guest | Logged-in |
|-----|-------|-----------|
| `/reviews/` | Landing SPA | Route by caps (redirect mark / chooser / coordinator SPA) |
| `/reviews/mark/` | Redirect → `/reviews/` | Reviewer SPA (unchanged) |
| `/reviews/registry`, etc. | Redirect → `/reviews/` | Coordinator SPA (unchanged) |

**Do not** move coordinator base to a new path in this story — avoids breaking HashRouter bookmarks and `5-16` guards.

### Reuse existing helpers (do not duplicate)

| Helper | Location | Use |
|--------|----------|-----|
| `user_has_coordinator_workspace_access()` | `includes/capabilities.php` | Chooser + coordinator SPA gate |
| `user_has_reviewer_workspace_access()` / `current_user_can(PR_CAP_ENTER_MARKS)` | same | Mark redirect |
| `workspace_home_url_for_user()` | same | Post-login default (coordinator wins over reviewer) |
| `assert_workspace_access()` | `includes/routes.php` | Keep for `/reviews/mark/` and coordinator **deep** paths; **root** `/reviews/` logged-in flow branches **before** loading coordinator bundle |
| `PluginSettings::login_url_with_redirect()` | `includes/services/PluginSettings.php` | Login CTA |
| `WorkspaceAccess::filter_login_redirect()` | `includes/workspace-access.php` | WP login → app homes |

### `handle_template()` flow sketch (coordinator rewrite at `/reviews/`)

```php
// pr_app === 'coordinator' at /reviews/
if (!is_user_logged_in()) {
    self::render_landing_shell();
    return;
}
$coord = Capabilities::user_has_coordinator_workspace_access();
$mark = current_user_can(PR_CAP_ENTER_MARKS);
if (!$coord && $mark) {
    wp_safe_redirect(home_url('/reviews/mark/'));
    self::end_request();
    return;
}
if (!$coord && !$mark) {
    self::deny_workspace_access();
    return;
}
if ($coord && $mark) {
    self::render_landing_shell(); // chooser in React
    return;
}
// coordinator only
self::render_coordinator_shell();
```

Extract `render_landing_shell()` / `render_coordinator_shell()` private methods to avoid duplication with template include.

### Landing React sketch

```jsx
// src/landing/App.jsx
const { canAccessCoordinator, canAccessMarking, loginUrl, currentUser } = window.prAppData ?? {};
if (!currentUser) {
  return <GuestLanding loginUrl={loginUrl} />;
}
if (canAccessCoordinator && canAccessMarking) {
  return <WorkspaceChooser coordinatorHomeUrl={...} markingHomeUrl={...} />;
}
// Should not mount chooser without both caps — PHP should redirect single-cap users before enqueue
return null;
```

PHP must enforce single-cap redirects **before** enqueueing landing JS for those users, so the React chooser-only path is not relied on for security.

### Critical files (touch list)

**PHP**

- `includes/routes.php` — guest landing, logged-in branch, `landing` enqueue
- `includes/services/ReviewerProvisionService.php` — invite login URL
- `includes/workspace-access.php` — optional reviewer-only redirect target
- `templates/app-shell.php` — no change required if `$pr_app = 'landing'` already works

**React / build**

- `webpack.config.js` — add `landing` entry
- `src/landing/index.js`, `src/landing/App.jsx` (new)
- `src/shared/AppShell.jsx` — optional `variant="landing"` (top bar only) OR duplicate minimal chrome in landing only

**Tests**

- `tests/RoutesTest.php`
- `tests/WorkspaceAccessTest.php`
- `tests/bootstrap.php` — stubs if new redirect patterns

**Out of scope**

- Replacing WordPress login form with custom credentials UI (still wp-login / configured `login_url`)
- Magic-link / passwordless auth (Phase 3 in epics)
- Coordinator HashRouter route changes
- REST permission changes (`1-3` already enforces caps)

### Previous story intelligence

- **1-4** — rewrites; reviewer rule must stay **above** coordinator catch-all.
- **1-5 / 1-6** — tokens, `AppShell`, webpack entries pattern for coordinator/reviewer.
- **5-16** — workspace redirects and `prAppData` auth URLs; **extend**, do not remove, mark/coordinator guards on non-root paths.
- **9-3** — `login_url` setting is the WP login page URL, not the app landing.

### UX references

- Emotional goal **First login**: “Oriented, separate from timetable” — standalone shell, wordmark ([ux-design-specification.md — Desired Emotional Response](_bmad-output/planning/ux-design-specification.md)).
- UX-DR3 Direction 1 — Structured Academic; landing should feel like the same product family as coordinator/reviewer shells.
- UX-DR6 `AppShell` variants — add landing as third layout (top bar, no sidebar, narrow main).

### Manual verification

1. Logged out → open `/reviews/` → branded landing, **Log in** → wp-login → return → correct workspace.
2. Reviewer-only → `/reviews/` after login → `/reviews/mark/` assignments (no coordinator dashboard flash).
3. Coordinator-only → `/reviews/` after login → coordinator dashboard.
4. Administrator → `/reviews/` after login → chooser with both links; each workspace loads correctly.
5. Logged out → `/reviews/mark/` → `/reviews/` landing (not wp-login).
6. Reviewer-only → `/reviews/` still blocked from coordinator **deep** URLs (`/reviews/registry`) per `5-16`.
7. Resend invite email → login link returns through `/reviews/` then mark home.

### References

- [Source: `_bmad-output/planning/epics.md` — Epic 1, FR26, FR27]
- [Source: `_bmad-output/planning/ux-design-specification.md` — First login, entry routes]
- [Source: `_bmad-output/implementation/1-4-front-end-routes.md`]
- [Source: `_bmad-output/implementation/1-6-react-spas.md`]
- [Source: `_bmad-output/implementation/5-16-reviewer-header-auth-route-guard.md`]
- [Source: `_bmad-output/implementation/9-3-wp-admin-settings.md`]
- [Source: `includes/routes.php`, `includes/capabilities.php`, `includes/workspace-access.php`]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Added `/reviews/` landing flow: guests see landing SPA (no `auth_redirect`); logged-in users routed by caps at coordinator root (mark redirect, chooser, or coordinator SPA).
- Guests on `/reviews/mark/` and coordinator deep paths redirect to landing.
- New `landing` webpack entry with guest sign-in and dual-workspace chooser UI (`AppShell` variant `landing`).
- Reviewer invite/resend emails and reviewer-only WP login redirect now use `home_url('/reviews/')`.
- PHPUnit: 238 tests OK; `npm run build` produces `build/landing.*`.

### File List

- includes/routes.php
- includes/workspace-access.php
- includes/services/ReviewerProvisionService.php
- webpack.config.js
- src/landing/index.js
- src/landing/App.jsx
- src/shared/AppShell.jsx
- assets/css/app-shell.css
- build/landing.js
- build/landing.css
- build/landing-rtl.css
- build/landing.asset.php
- tests/RoutesTest.php
- tests/WorkspaceAccessTest.php

## Change Log

- 2026-05-17: Story created — shared `/reviews/` login landing and capability-based workspace routing (user request).
- 2026-05-17: Implemented landing SPA, server-side routing, invite/login redirect alignment, and tests.
