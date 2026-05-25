# Story 5.16: Reviewer header auth, route guard, and navigation

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want login and logout in the app header, a clear home at `/reviews/mark/`, and navigation limited to my marking workspace,
So that I cannot reach the coordinator project dashboard and can sign out cleanly on shared machines.

## Acceptance Criteria

### 1. Header — login and logout (reviewer)

- **Given** a reviewer is logged into WordPress and opens `/reviews/mark/`
- **When** the reviewer `AppShell` top bar renders
- **Then** the right cluster shows (left → right): **user identity** (existing `UserIdentity` from 5.9) and **account actions**:
  - **Log out** — link to WordPress logout URL with redirect back to the reviewer home after logout
  - **Log in** — shown only when `window.prAppData.currentUser` is absent (defensive; normal plugin routes still use `auth_redirect()` for guests)
- **And** URLs come from `prAppData` localized in `Routes::enqueue_app_assets()` (no hard-coded `/wp-login.php` in React):
  ```php
  'loginUrl' => PluginSettings::login_url(), // existing settings-aware helper
  'logoutUrl' => wp_logout_url(home_url('/reviews/mark/')),
  'appHomeUrl' => home_url('/reviews/mark/'),
  ```
- **And** logout link uses `rel="nofollow"` and visible text **Log out** (not icon-only)
- **And** login link text **Log in** when shown
- **And** actions use shared text-button styling (`text-sm font-medium text-primary hover:underline` or existing ghost pattern) and sit in a `flex items-center gap-3` group with identity
- **And** coordinator `AppShell` may receive the same login/logout block for parity (optional; reviewer AC is mandatory)

### 2. Server-side route guard — block coordinator app for reviewers

- **Given** rewrite rules from `1-4-front-end-routes` (`^reviews/?$`, `^reviews/.+` → coordinator; `^reviews/mark/?$` → reviewer)
- **When** a logged-in user requests `pr_app=coordinator` (`/reviews/`, `/reviews/registry`, etc.)
- **Then** access is allowed only if the user has **coordinator workspace** access: `current_user_can()` for **any** cap in `Capabilities::coordinator_caps()`, **or** `PR_CAP_OVERRIDE_MARKS`, **or** `PR_CAP_MANAGE_SETTINGS`
- **When** the user has **only** reviewer access (`PR_CAP_ENTER_MARKS` and **none** of the coordinator caps above — default `project_reviews_reviewer` role)
- **Then** `Routes::handle_template()` issues `wp_safe_redirect()` to `home_url('/reviews/mark/')` and **does not** load the coordinator SPA or enqueue `build/coordinator.js`
- **When** the user has neither coordinator nor reviewer caps
- **Then** respond with `403` and a short translatable message (or `wp_die` with “You do not have permission…”) — do not load either SPA
- **And** administrators (all `pr_*` caps) still reach `/reviews/` as coordinators

### 3. Server-side route guard — reviewer mark app

- **Given** `pr_app=reviewer` (`/reviews/mark/`)
- **When** the user lacks `PR_CAP_ENTER_MARKS`
- **Then** if they have coordinator workspace access → `wp_safe_redirect(home_url('/reviews/'))`
- **Else** → `403` as above
- **When** they have `PR_CAP_ENTER_MARKS`
- **Then** load reviewer shell as today

### 4. Reviewer home and HashRouter defaults

- **Given** a reviewer-only user
- **When** they open `http://localhost:3000/reviews/mark/` (or production equivalent)
- **Then** the assignments home (`MarkAssignments` at HashRouter `#/`) is the landing experience
- **When** they hit an unknown hash path (e.g. `#/registry`)
- **Then** `<Navigate to="/" replace />` already in `src/reviewer/App.jsx` sends them to assignments — **no change required** unless regression found
- **When** they complete logout
- **Then** WordPress returns them to `/reviews/mark/` (logged out); `auth_redirect()` sends them to login on next visit

### 5. Reviewer navigation links

- **Given** reviewer `AppShell` (no left sidebar per UX-DR4)
- **When** the reviewer app is active
- **Then** a **horizontal nav** appears in the top bar (between wordmark and identity/actions) with at least:
  | Label | Target | Notes |
  |-------|--------|--------|
  | **Assignments** | `#/` | Active on `/` and when not inside a marking deep link |
- **And** implement via new `src/reviewer/ReviewerNav.jsx` using `NavLink` + same active/inactive classes as `CoordinatorNav` (`linkClass` pattern)
- **And** wordmark **Project Reviews** links to `#/` (reviewer home) on reviewer variant only (coordinator wordmark stays non-link or links to `#/` on coordinator — do not change coordinator behavior unless linking to `#/` is already desired)
- **And** nav is hidden on coordinator variant
- **And** deep-link routes (`#/mark/...`, `#/panel-report/...`) keep existing in-page **Back to assignments** controls; nav **Assignments** item remains available to return home

### 6. Client-side coordinator URL hardening (belt-and-suspenders)

- **Given** a reviewer-only user somehow loads coordinator JS (stale cache, bug)
- **When** `CoordinatorApp` mounts
- **Then** optional guard: if `prAppData` includes `workspace: 'reviewer'` or `canAccessCoordinator: false`, immediately `window.location.replace(prAppData.appHomeUrl)` — **prefer server guard (AC 2) as source of truth**; add `canAccessCoordinator` boolean to `prAppData` only if cheap to compute in PHP alongside caps
- **Do not** rely on client guard alone

### 7. Tests and build

- **And** `RoutesTest` adds cases:
  - `test_reviewer_only_user_redirected_from_coordinator_route` — user with only `PR_CAP_ENTER_MARKS` → redirect URL contains `/reviews/mark/`, coordinator template not included
  - `test_coordinator_user_can_load_coordinator_route` — user with `PR_CAP_MANAGE_SESSIONS` → coordinator shell
  - `test_reviewer_user_can_load_reviewer_route` — `PR_CAP_ENTER_MARKS` → reviewer shell
  - `test_pr_app_data_includes_login_logout_urls` — localized `loginUrl`, `logoutUrl`, `appHomeUrl` non-empty
- **And** extend `tests/bootstrap.php` stubs for `wp_safe_redirect`, `wp_logout_url`, `home_url` if not already captured
- **And** optional `Capabilities::user_has_coordinator_workspace_access(): bool` helper in `includes/capabilities.php` to avoid duplicating cap lists in `Routes.php` (single source of truth)
- **And** run `composer test` and `npm run build`

## Tasks / Subtasks

- [x] **Capabilities helper:** `user_has_coordinator_workspace_access()` / `user_has_reviewer_workspace_access()` on `Capabilities` (AC: 2, 3, 7)
- [x] **Routes guard:** Redirect/forbid in `Routes::handle_template()` before template include (AC: 2, 3)
- [x] **prAppData:** `loginUrl`, `logoutUrl`, `appHomeUrl`, optional `canAccessCoordinator` (AC: 1, 6, 7)
- [x] **AppShell:** Account actions + reviewer nav slot; wordmark link on reviewer (AC: 1, 5)
- [x] **ReviewerNav:** New component; wire in `src/reviewer/App.jsx` (AC: 5)
- [x] **CSS:** Topbar layout for nav + actions (`assets/css/app-shell.css`) without breaking coordinator topbar (AC: 1, 5)
- [x] **Tests:** `RoutesTest` + bootstrap stubs (AC: 7)
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

1. Add **login** and **logout** in the header for the reviewer role.
2. **Restrict** access to the coordinator `/reviews` workspace — reviewer home is `/reviews/mark/`.
3. Add **proper nav links** for the reviewer role.
4. **Bug today:** reviewer can open `/reviews` and see the coordinator dashboard (create project, etc.) because `Routes::handle_template()` only checks `is_user_logged_in()`, not capabilities (`1-4` explicitly deferred capability gate).

### Root cause

```48:55:includes/routes.php
        if (!is_user_logged_in()) {
            auth_redirect();
            return;
        }

        self::strip_theme_assets();
        self::enqueue_app_assets($app);
```

Any authenticated WP user hitting `^reviews/?$` or `^reviews/.+` gets `pr_app=coordinator` and the full coordinator SPA.

### Capability matrix (defaults)

| Role | Coordinator `/reviews/` | Reviewer `/reviews/mark/` |
|------|-------------------------|---------------------------|
| `project_reviews_reviewer` | **Redirect** → `/reviews/mark/` | Allow |
| `project_reviews_coordinator` | Allow | **Redirect** → `/reviews/` (no `pr_enter_marks` by default) |
| `administrator` | Allow | Allow (has all caps) |

Use **capability checks**, not role name strings — custom mappings may differ.

### Architecture alignment

| Area | Current | Target |
|------|---------|--------|
| Route auth | Login only | Login + workspace capability gate |
| Reviewer home | `/reviews/mark/` loads app but `/reviews/` also loads coordinator SPA | Reviewer-only users never get coordinator bundle |
| Top bar | Wordmark + identity | + nav + login/logout |
| Login URL | `PluginSettings::login_url()` in emails only | Exposed on `prAppData` for header |
| Nav | Coordinator sidebar only | Reviewer horizontal nav in topbar |

**Do not** change REST permission callbacks — they already enforce caps (`1-3-rest-auth`). This story is **route + shell UX**.

**Do not** change mark persistence, freeze, assignments APIs (5.6–5.15).

### Critical files (touch list)

**PHP**

- `includes/capabilities.php` — workspace access helpers
- `includes/routes.php` — redirect/guard + `prAppData` fields
- `includes/services/PluginSettings.php` — already has `login_url()`; reuse

**React / CSS**

- `src/shared/AppShell.jsx` — identity + auth links + optional `topNav` prop
- `src/reviewer/ReviewerNav.jsx` (new)
- `src/reviewer/App.jsx` — pass `topNav={<ReviewerNav />}`
- `assets/css/app-shell.css` — topbar grid/flex for nav + actions

**Tests**

- `tests/RoutesTest.php`
- `tests/bootstrap.php` — redirect/logout URL stubs

### AppShell API sketch

```jsx
export function AppShell({ variant, children, sidebar, topNav }) {
  const isCoordinator = variant === 'coordinator';
  const homeHref = window.prAppData?.appHomeUrl; // reviewer: full URL to /reviews/mark/
  // wordmark: reviewer → <a href={homeHref or '#/'}>, coordinator → unchanged <p>
  // topNav rendered in topbar when !isCoordinator && topNav
  // AuthLinks component reads loginUrl, logoutUrl, currentUser
}
```

Use **full page URL** for wordmark on reviewer (`/reviews/mark/`) so refresh lands on correct rewrite route, not bare `#/` on wrong path.

### Redirect implementation sketch (PHP)

```php
private static function assert_workspace_access(string $app): void
{
    if ($app === 'coordinator' && !Capabilities::user_has_coordinator_workspace_access()) {
        if (current_user_can(PR_CAP_ENTER_MARKS)) {
            wp_safe_redirect(home_url('/reviews/mark/'));
            self::end_request();
        }
        // 403 ...
    }
    if ($app === 'reviewer' && !current_user_can(PR_CAP_ENTER_MARKS)) {
        if (Capabilities::user_has_coordinator_workspace_access()) {
            wp_safe_redirect(home_url('/reviews/'));
            self::end_request();
        }
        // 403 ...
    }
}
```

Call **before** `enqueue_app_assets()` to avoid loading wrong bundle.

### Previous story intelligence

- **5.9** added `currentUser` to `prAppData` and `UserIdentity` in `AppShell` — extend same topbar cluster; do not remove identity block.
- **1.4** registered rewrites; reviewer rule must stay **above** coordinator catch-all (`register_rewrites` order is already correct).
- **9.3** `PluginSettings::login_url()` — use for branded login link consistency with invite emails.

### UX references

- UX-DR4: coordinator sidebar 240px; reviewer **no sidebar** — horizontal topbar nav is correct.
- UX-DR6: `AppShell` coordinator vs reviewer variants.
- FR27: reviewer workspace at `/reviews/mark/`.
- FR26: coordinator workspace at `/reviews/`.

### Manual verification

1. Log in as **reviewer-only** (`project_reviews_reviewer`) → open `/reviews/` → lands on `/reviews/mark/` with assignments; no dashboard/create project UI.
2. Same user → header shows name, email, **Log out** → logout → login screen → login → returns to mark home.
3. Log in as **coordinator** → `/reviews/` works; `/reviews/mark/` redirects to `/reviews/` (unless user also has enter_marks).
4. Log in as **administrator** → both URLs work.
5. Deep link ` /reviews/mark/#/mark/{session}/{review}/{panel}` → marking grid; **Assignments** nav → home.

### References

- [Source: `_bmad-output/implementation/1-4-front-end-routes.md`]
- [Source: `_bmad-output/implementation/1-2-capabilities.md`]
- [Source: `_bmad-output/implementation/5-9-reviewer-page-ui-polish.md`]
- [Source: `includes/routes.php`]
- [Source: `src/shared/AppShell.jsx`]
- [Source: `_bmad-output/planning/epics.md` — FR26, FR27, Epic 1]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

- `coordinator_caps()` excluded `PR_CAP_ENTER_MARKS` so reviewer-only users are not treated as coordinators.
- Redirect/deny paths return before template enqueue; unit tests use `workspace_access_blocked()` because `end_request()` does not halt in `PR_UNIT_TEST`.

### Completion Notes List

- Added `Capabilities::user_has_coordinator_workspace_access()` and `user_has_reviewer_workspace_access()`; excluded `pr_enter_marks` from coordinator cap list used for workspace checks.
- `Routes::assert_workspace_access()` redirects reviewer-only users away from `/reviews/` and coordinators without `pr_enter_marks` away from `/reviews/mark/`; 403 when neither workspace applies.
- Localized `loginUrl`, `logoutUrl`, `appHomeUrl`, `canAccessCoordinator` on `prAppData`; reviewer logout returns to mark home.
- `AppShell` shows Log in / Log out, reviewer wordmark links to `appHomeUrl`, horizontal `ReviewerNav` with Assignments.
- Coordinator SPA client guard redirects when `canAccessCoordinator === false`.
- PHPUnit: 222 tests passing; `npm run build` successful.

### File List

- includes/capabilities.php
- includes/routes.php
- assets/css/app-shell.css
- src/shared/AppShell.jsx
- src/reviewer/ReviewerNav.jsx
- src/reviewer/App.jsx
- src/coordinator/App.jsx
- tests/bootstrap.php
- tests/RoutesTest.php
- tests/CapabilitiesTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css

## Change Log

- 2026-05-17: Story created — reviewer header login/logout, route capability guard, reviewer nav (user request).
- 2026-05-17: Implemented route guards, header auth links, reviewer nav, tests, and production build.
