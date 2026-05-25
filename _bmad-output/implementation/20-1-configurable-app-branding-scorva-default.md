# Story 20.1: Configurable app branding (Scorva default)

Status: review

<!-- Ultimate context engine analysis completed — single source of truth for user-visible product name; default Scorva: The Review Management System; technical identifiers unchanged -->

## Story

As a **site administrator**,
I want **one settings field that controls the product name shown across the app, emails, and WP Admin**,
So that our college can brand the review system (e.g. Scorva) without forking the plugin or hunting hardcoded strings.

## Background

Today **“Project Reviews”** is baked into PHP `__()` strings, React wordmarks, email templates, WP Admin menu labels, role display names, and the plugin header. Story **10-1** renamed user-facing **session → project** but left the **product name** fixed.

**Product default (this story):** **Scorva: The Review Management System**

**Technical identity (unchanged):** plugin directory `project-reviews/`, slug `PR_PLUGIN_SLUG`, REST namespace `project-reviews/v1`, text domain `project-reviews`, hash routes, DB tables, PHP class namespaces, Composer package name, and E2E env vars that reference paths/URLs.

## Acceptance Criteria

### 1. Single source of truth — PHP

1. **Given** `PluginSettings` (`includes/services/PluginSettings.php`)
   **When** any user-visible product name is needed in PHP
   **Then** code calls **`PluginSettings::app_display_name()`** (name may vary slightly if you prefer `display_name()` — use one public method and document it)
   **And** the option key is **`app_display_name`** inside `pr_plugin_settings` (`PluginSettings::OPTION_KEY`)
   **And** the **default** when unset/empty is exactly: **`Scorva: The Review Management System`**
   **And** a class constant documents the default, e.g. `PluginSettings::DEFAULT_APP_DISPLAY_NAME`

2. **Given** sanitization on save
   **When** an administrator saves settings
   **Then** `app_display_name` is trimmed and passed through `sanitize_text_field` (max length reasonable, e.g. 120 chars)
   **And** empty input after trim falls back to the default constant (do not persist empty string as “blank product”)

3. **Given** existing installs that already have `from_name` = `Project Reviews` and no `app_display_name`
   **When** settings are read
   **Then** `app_display_name()` returns the new Scorva default (not “Project Reviews”)
   **And** **`from_name()`** for email headers: if stored `from_name` is empty **or** still the legacy default `Project Reviews`, fall back to **`app_short_name()`** (see AC2) so invite/notification From lines stay coherent without forcing admins to re-save email settings

4. **Given** `app_short_name()` (private or public helper on `PluginSettings`)
   **When** a shorter label is needed (email subjects, WP role names, narrow UI)
   **Then** it returns the substring **before the first `:`** in `app_display_name()`, trimmed, or the full display name if no colon
   **And** for default Scorva branding: short name is **`Scorva`**

### 2. WP Admin configuration (extends story 9-3)

5. **Given** a user with `pr_manage_settings`
   **When** they open **Settings → Project Reviews** (menu label may become **Scorva** or **Review system** — see AC4; at minimum add the field on the existing page)
   **Then** they see **Application display name** (or **Product name**) with description: shown in the app header, landing page, emails, and permission messages
   **And** the field is pre-filled with the current `app_display_name()`
   **And** saving persists via existing `register_setting` / `PluginSettings::sanitize()` flow (native WP admin UI only — UX-DR34)

6. **Given** the settings page heading and options submenu
   **When** rendered
   **Then** user-visible titles use `app_display_name()` where they currently say “Project Reviews Settings” / “Project Reviews” (not hardcoded English)

### 3. Front-end bootstrap — `prAppData`

7. **Given** coordinator, reviewer, and landing apps enqueued in `includes/routes.php`
   **When** `wp_localize_script( …, 'prAppData', … )` runs
   **Then** payload includes **`appDisplayName`** = `PluginSettings::app_display_name()`
   **And** optionally **`appShortName`** = `PluginSettings::app_short_name()` for React copy that needs a short label

8. **Given** `src/shared/appBranding.js` (new, small module)
   **When** React needs the product name
   **Then** it exports **`getAppDisplayName()`** and **`getAppShortName()`** reading `window.prAppData` with the same Scorva default string as fallback when `prAppData` is missing (Storybook/tests)
   **And** **no** React file hardcodes `"Project Reviews"` for user-visible text after this story

### 4. User-visible surfaces — must use branding helper

9. **Given** the app shell wordmark (`src/shared/AppShell.jsx`)
   **When** coordinator or reviewer header renders
   **Then** the wordmark text is `getAppDisplayName()` (UX spec: primary color, 20px semibold — unchanged styling)

10. **Given** landing guest page (`src/landing/App.jsx`)
    **When** a logged-out user opens `/reviews/`
    **Then** `PageHeader` title uses `getAppDisplayName()` (not hardcoded “Project Reviews”)

11. **Given** document title (`templates/app-shell.php`)
    **When** any SPA route loads
    **Then** `<title>` uses `PluginSettings::app_display_name()` (escaped)

12. **Given** HTML emails (`includes/emails/*.php`)
    **When** subjects, headers, footers, or body mention the product
    **Then** they use `app_display_name()` / `app_short_name()` via `__()` with translator context where needed — replace literals **Project Reviews** in:
    - `ReviewerInviteEmail.php`
    - `SessionClosedEmail.php`
    - `RubricOpenEmail.php`

13. **Given** REST/auth user messages (`includes/rest/class-rest-auth.php`, `class-rest-sessions.php` plugin reactivation string, etc.)
    **When** messages refer to the product by name
    **Then** they use `PluginSettings::app_display_name()` inside translatable strings (sprintf pattern)

14. **Given** WP role labels on activation (`includes/capabilities.php`)
    **When** roles are ensured
    **Then** display names use short branding, e.g. **`{app_short_name} Coordinator`** and **`{app_short_name} Reviewer`** (default: **Scorva Coordinator**, **Scorva Reviewer**)

15. **Given** backup README / manifest strings in `BackupService.php` that are human-facing
    **When** export ZIP is generated
    **Then** product name in README/manifest uses `app_display_name()` (SQL comment headers may stay technical)

### 5. Plugin header and repository naming (scope boundary)

16. **Given** `project-reviews.php` plugin header
    **When** viewed in **Plugins** list
    **Then** **Plugin Name** is **`Scorva`** and **Description** includes **The Review Management System** (and brief purpose line)
    **And** **Text Domain** remains `project-reviews`

17. **Given** this story’s scope
    **When** implementation is complete
    **Then** the following are **NOT** renamed (document for dev agent — same guardrails as story **10-1**):
    - Directory / repo folder `project-reviews`
    - `PR_PLUGIN_SLUG`, REST paths, capabilities (`pr_*`), option keys, DB tables
    - npm/composer package identifiers unless already required for build
    - Hash routes (`#/session/…`)
    - Translation file names (`project-reviews.pot`) — strings inside may change English default

### 6. Tests, docs, and verification

18. **Given** implementation is complete
    **When** searching the codebase
    **Then** `rg '"Project Reviews"' src/` returns **no** user-visible string literals (identifiers/URLs OK)
    **And** `rg "'Project Reviews'" includes/` returns none in `__()` / email copy except migration comments
    **And** `rg 'Project Reviews' tests/e2e/` is updated to expect **Scorva** (or read display name from a shared test constant)

19. **Given** PHPUnit
    **When** tests run
    **Then** add **`tests/PluginSettingsBrandingTest.php`** (or extend existing settings tests) covering:
    - default `app_display_name()` when option missing
    - `app_short_name()` parsing with and without `:`
    - `from_name()` legacy fallback when stored value is `Project Reviews`
    **And** `composer test` passes

20. **Given** front-end build
    **When** `npm run build` completes
    **Then** coordinator, reviewer, and landing bundles include `appBranding` usage without breaking enqueue

21. **Manual smoke checklist**
    - WP Admin → settings: change display name → save → reload `/reviews/` → wordmark matches
    - Landing `/reviews/` logged out → title + header match setting
    - Send test reviewer invite (or inspect email HTML in test) → product name matches
    - Plugins list shows **Scorva**

### Out of scope (follow-up stories)

- Renaming plugin folder, REST namespace, or text domain (breaking migration).
- Per-project branding (only site-wide setting).
- Custom logo upload (wordmark remains text; PDF letterhead is per-project in story **11-1**).
- Updating Typst SOP PDF (`docs/sop/`) and all BMad planning docs — optional doc sweep, not blocking AC.
- i18n `.po` regeneration — English defaults change; translators can catch up later.

## Tasks / Subtasks

- [x] **PHP settings:** Add `app_display_name`, `app_display_name()`, `app_short_name()`, default constant; wire `sanitize()` / `defaults()`; adjust `from_name()` fallback (AC: 1–3)
- [x] **WP Admin:** Field + labels using dynamic name (AC: 5–6)
- [x] **Bootstrap:** `appDisplayName` / `appShortName` in `routes.php` for all three SPAs (AC: 7)
- [x] **React:** `src/shared/appBranding.js`; update `AppShell.jsx`, `landing/App.jsx` (AC: 8–10)
- [x] **PHP surfaces:** `app-shell.php`, emails, capabilities, REST copy, backup README (AC: 11–15)
- [x] **Plugin header:** `project-reviews.php` (AC: 16)
- [x] **Tests & E2E:** PHPUnit branding tests; update `tests/e2e`, `MVP_CHECKLIST.md`, `tests/README.md` expectations (AC: 18–20)
- [x] **Verify:** ripgrep guards + manual smoke (AC: 21)

## Dev Notes

### Why one field, not two

Faculty want **college-owned branding** on every screen and email. A single **Application display name** avoids drift between header, landing, and invites. **Email From name** stays a separate field (`from_name`) for SMTP practicalities, but should **fall back** to the short brand when legacy defaults apply.

### Default string

| Setting | Default |
|---------|---------|
| `app_display_name` | `Scorva: The Review Management System` |
| `from_name` (new installs) | `Scorva` (recommended default in `defaults()`) |
| `from_name` (fallback when empty/legacy) | `app_short_name()` |

### PHP pattern (canonical)

```php
public const DEFAULT_APP_DISPLAY_NAME = 'Scorva: The Review Management System';

public static function app_display_name(): string
{
    $name = trim((string) (self::get()['app_display_name'] ?? ''));
    return $name !== '' ? $name : self::DEFAULT_APP_DISPLAY_NAME;
}

public static function app_short_name(): string
{
    $full = self::app_display_name();
    $pos = strpos($full, ':');
    if ($pos !== false) {
        $short = trim(substr($full, 0, $pos));
        if ($short !== '') {
            return $short;
        }
    }
    return $full;
}
```

### React pattern (canonical)

```js
// src/shared/appBranding.js
const DEFAULT_APP_DISPLAY_NAME = 'Scorva: The Review Management System';

export function getAppDisplayName() {
  const name = window.prAppData?.appDisplayName?.trim();
  return name || DEFAULT_APP_DISPLAY_NAME;
}

export function getAppShortName() {
  const short = window.prAppData?.appShortName?.trim();
  if (short) return short;
  const full = getAppDisplayName();
  const idx = full.indexOf(':');
  return idx > 0 ? full.slice(0, idx).trim() : full;
}
```

Export from `src/shared/components/index.js` if other shared helpers are re-exported there.

### File inventory (grep-driven — start here)

| Area | Files |
|------|--------|
| Settings core | `includes/services/PluginSettings.php` |
| Admin UI | `includes/admin/class-admin-settings.php` |
| Enqueue | `includes/routes.php` (`enqueue_app_assets`, `landing_pr_app_data`) |
| Shell / landing | `src/shared/AppShell.jsx`, `src/landing/App.jsx`, `templates/app-shell.php` |
| Emails | `includes/emails/ReviewerInviteEmail.php`, `SessionClosedEmail.php`, `RubricOpenEmail.php` |
| Roles | `includes/capabilities.php` |
| REST copy | `includes/rest/class-rest-auth.php`, `class-rest-sessions.php` (reactivation message) |
| Backup copy | `includes/services/BackupService.php` (README/manifest only) |
| Plugin header | `project-reviews.php` |
| Tests | `tests/PluginSettingsBrandingTest.php` (new), `tests/e2e/**/*.ts`, `tests/e2e/MVP_CHECKLIST.md` |

Run before/after:

```bash
rg -n '"Project Reviews"' src includes templates
rg -n "Project Reviews" tests/e2e
```

### Relationship to story 10-1

| Concept | 10-1 | This story |
|---------|------|------------|
| Review event noun | **project** (not session) | unchanged |
| Product / app name | still “Project Reviews” | **configurable**, default **Scorva: …** |
| Code identifiers `session_*` | frozen | frozen |

### Architecture compliance

| Requirement | Application |
|-------------|-------------|
| FR25 / Story 9-3 | Extend existing options page; no Tailwind in WP Admin |
| NFR17 | Emails remain plugin-branded HTML; brand string from settings |
| UX-DR4 / UX spec wordmark | Text wordmark uses `app_display_name`; 20px semibold primary unchanged |
| UX-DR34 | Native WP Admin settings UI |

### Testing notes

- PHPUnit stubs in `tests/bootstrap.php` already mock `get_option` — follow `PluginSettings` / settings tests patterns.
- E2E: prefer `getByRole('heading', { name: /Scorva/ })` or read from env `PR_APP_DISPLAY_NAME` if you add test helper — avoid brittle full subtitle match in every spec.
- Do **not** rename `data-testid` values or REST paths in this story.

### Previous story intelligence

- **9-3** — `PluginSettings`, `Admin_Settings`, `from_name` / `reply_to` / `login_url` patterns; extend, do not duplicate options page.
- **10-1** — copy-only sweep discipline; **do not** rename `session` identifiers while changing product name.
- **1-7** — landing page uses `PageHeader`; branding belongs in title, not only shell.
- **5-9 / 5-16** — reviewer wordmark in `AppShell`; link behaviour unchanged.

### References

- [Source: includes/services/PluginSettings.php — `from_name()`, `OPTION_KEY`, defaults]
- [Source: includes/admin/class-admin-settings.php — settings fields]
- [Source: includes/routes.php — `prAppData` localization]
- [Source: src/shared/AppShell.jsx — wordmark lines 138–144]
- [Source: _bmad-output/implementation/10-1-rename-session-to-project-terminology.md — identifier guardrails]
- [Source: _bmad-output/implementation/9-3-wp-admin-settings.md]
- [Source: _bmad-output/planning/ux-design-specification.md — Wordmark § Typography]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

- PHPUnit OK (354 tests). `npm run build` OK.
- AC18: no `"Project Reviews"` in `src/`; only `LEGACY_FROM_NAME` in PHP.

### Completion Notes List

- Added `PluginSettings::app_display_name()` / `app_short_name()` with Scorva default; `from_name()` falls back from legacy `Project Reviews` to short brand.
- WP Admin **Application display name** field; menu/title use dynamic branding.
- `prAppData.appDisplayName` / `appShortName` on coordinator, reviewer, landing; `src/shared/appBranding.js` for React.
- Replaced user-visible **Project Reviews** strings in shell, emails, REST, roles, backup README, plugin header (**Scorva**).
- `tests/PluginSettingsBrandingTest.php`; E2E helper `expectedAppDisplayName()`.

### File List

- includes/services/PluginSettings.php
- includes/admin/class-admin-settings.php
- includes/routes.php
- includes/capabilities.php
- includes/emails/ReviewerInviteEmail.php
- includes/emails/RubricOpenEmail.php
- includes/emails/SessionClosedEmail.php
- includes/rest/class-rest-auth.php
- includes/rest/class-rest-sessions.php
- includes/services/BackupService.php
- templates/app-shell.php
- project-reviews.php
- src/shared/appBranding.js
- src/shared/AppShell.jsx
- src/landing/App.jsx
- tests/PluginSettingsBrandingTest.php
- tests/RoutesTest.php
- tests/e2e/helpers/reviews-site.ts
- tests/e2e/MVP_CHECKLIST.md
- tests/README.md
- build/* (coordinator, reviewer, landing bundles)

### Change Log

- 2026-05-24: Configurable app branding (default Scorva); single PHP/React source of truth.

## Story Completion Status

- Status: **review**
- Ultimate context engine analysis completed — comprehensive developer guide for configurable Scorva branding and legacy Project Reviews string removal on user surfaces.
