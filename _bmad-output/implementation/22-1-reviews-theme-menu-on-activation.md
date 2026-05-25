# Story 22.1: Reviews home link in site menu bar on plugin activation

Status: review

<!-- Ultimate context engine analysis completed — cross-theme navigation bootstrap; WordPress nav menus + filter bridge for custom themes (david-sas) -->

## Story

As a **site visitor or faculty member** using the college WordPress site,
I want a **Reviews** entry in the site’s primary menu bar that opens the plugin landing at `/reviews/`,
So that I can discover and sign in to the review system without bookmarking a hidden URL.

As a **site administrator** installing the plugin on **any** theme,
I want activation to **attempt** automatic menu wiring with safe defaults and clear settings when the theme does not use WordPress menus,
So that rollout is predictable on Block themes, classic `wp_nav_menu` themes, and custom PHP nav themes like `david-sas`.

## Background

| Surface today | Behaviour |
|---------------|-----------|
| Plugin entry | `home_url('/reviews/')` — guest landing (Story 1-7), coordinator/reviewer routing (Stories 1-4, 5-16) |
| App chrome | Plugin routes strip theme header/footer (NFR3); menu bar = **theme** responsibility |
| `Plugin::activate()` | Schema, caps, rewrites — **no** theme menu integration |
| Branding label | `PluginSettings::app_short_name()` default **Scorva** (Story 20-1) |
| Reference theme `david-sas` | **Hardcoded** nav in `header.php` — **no** `register_nav_menu()` / `wp_nav_menu()` |

**Gap:** Users on the main site never see Reviews unless an admin manually adds a menu item or theme code is edited.

## Cross-theme installation plan (read before coding)

Use this matrix to choose implementation paths; the dev agent must implement **Path A + Path B** (not “pick one”).

### Path A — WordPress Navigation Menus (majority of themes)

**Applies when:** Theme registers at least one menu location (`register_nav_menus`) **or** site already has a menu assigned to `primary` / `header` / `main` style locations.

**On activation (idempotent):**

1. Resolve target menu: first menu already assigned to a location in `pr_theme_nav_location_priority` filter default list (`primary`, `menu-1`, `header`, `main`, `top`), else first menu in `wp_get_nav_menus()`, else create menu titled **Site navigation** (translatable).
2. Ensure custom-link item:
   - **URL:** `home_url('/reviews/')` (never a WordPress Page post — rewrites own the URL)
   - **Title:** `PluginSettings::app_short_name()` + optional suffix filter (default label **Reviews** via `pr_theme_nav_menu_label` — see AC 2)
   - **Position:** append (or filter `pr_theme_nav_menu_position`)
3. Assign menu to discovered locations where empty or where filter `pr_theme_nav_force_location_assign` returns true (default: assign only if location has no menu yet).
4. Persist `pr_theme_nav_bootstrap` option: `{ version, menu_id, menu_item_id, locations[], bootstrapped_at }` for updates/uninstall.

**Re-activation / upgrade:** If stored `menu_item_id` missing, re-create item; if URL/label drift from settings, update item via `wp_update_nav_menu_item` (compare stored hash).

**Deactivation:** **Do not remove** menu item (consistent with non-destructive deactivation Story 1-8).

**Uninstall (opt-in data wipe):** If `pr_delete_data_on_uninstall` and user confirmed wipe, remove **only** the plugin-created menu item (not entire menu).

### Path B — Filter bridge for custom PHP nav (david-sas and peers)

**Applies when:** Theme does not call `wp_nav_menu()` for the header (david-sas today).

**Plugin provides:**

```php
// includes/theme-nav.php (suggested)
apply_filters('pr_theme_nav_items', []); // list of [ 'url' => string, 'label' => string, 'slug' => 'reviews' ]
```

Default filter callback registers one item when `PluginSettings::theme_nav_bridge_enabled()` (default **true**):

```php
[
  'url'   => home_url('/reviews/'),
  'label' => PluginSettings::theme_nav_menu_label(), // default "Reviews"
  'slug'  => 'reviews',
]
```

**david-sas integration (separate theme PR or documented snippet — not blocked on plugin merge):**

In `header.php` desktop + mobile nav (logged-in block ~line 153), after Timetable dropdown or before Faculty link, render:

```php
if (function_exists('pr_theme_nav_items_for_display')) {
    foreach (pr_theme_nav_items_for_display() as $item) { /* single top-level <a> like $fm_url */ }
}
```

Helper `pr_theme_nav_items_for_display()` lives in plugin, wraps filter, applies `esc_url` / `esc_html` at render time.

**Capability visibility (optional v1):** Filter `pr_theme_nav_show_item` — default `true` for all visitors (landing is public). Future: hide for guests if institution wants login-only discovery.

### Path C — Block themes (Twenty Twenty-Four, etc.)

Same as Path A: block themes expose **Navigation** block backed by `wp_navigation` post type / classic menus depending on WP version. `wp_update_nav_menu_item` + location assignment still works when theme uses core menu locations. Document in settings: “If your header uses a Navigation block, pick the menu we created/updated under Appearance → Menus.”

### Path D — Failure / no menu support

**When:** No menu locations, `wp_create_nav_menu` fails, or admin disabled auto-bootstrap.

**Then:**

- Set option flag `pr_theme_nav_bootstrap_status` = `manual`
- Show **admin notice** (once per site, dismissible): “Add a custom link to `{reviews_url}` labeled **Reviews** in Appearance → Menus.”
- Settings field documents manual steps (AC 5)

### What we explicitly do **not** do

- Inject menu HTML via `wp_footer` on every page (fragile, accessibility poor).
- Create a WordPress **Page** at `/reviews/` (conflicts with rewrite rules).
- Enqueue theme CSS on plugin routes or vice versa (NFR3 unchanged).

## Acceptance Criteria

### 1. Activation bootstrap — WordPress menus

1. **Given** fresh plugin activation on a theme with menu location `primary` assigned to menu “Main”
   **When** `Plugin::activate()` completes
   **Then** `ThemeNavBootstrap::on_activate()` runs after rewrites/caps
   **And** a custom-link item exists on that menu pointing to `home_url('/reviews/')`
   **And** `pr_theme_nav_bootstrap` option records `menu_id`, `menu_item_id`, and `locations`
   **And** second activation does **not** duplicate items (idempotent by stored `menu_item_id` or URL match)

2. **Given** activation on a site with **no** nav menus
   **When** bootstrap runs
   **Then** a new menu is created, the Reviews link is added, and the menu is assigned to every location returned by `get_registered_nav_menus()` that passes the assign filter
   **And** if menu APIs are unavailable (stub tests), bootstrap no-ops without fatal

3. **Given** `PluginSettings::theme_nav_auto_bootstrap_enabled()` is false (new setting, default **true**)
   **When** plugin activates
   **Then** WordPress menu mutation is skipped
   **And** `pr_theme_nav_bootstrap_status` = `disabled`

### 2. Menu label and URL follow branding settings

4. **Given** `app_display_name` = `Scorva: The Review Management System`
   **When** the menu item is created or synced
   **Then** default menu label is **`Reviews`** via `PluginSettings::theme_nav_menu_label()` (configurable in settings — not forced to full display name for narrow nav bars)
   **And** filter `pr_theme_nav_menu_label` can override
   **And** URL always uses `home_url('/reviews/')` regardless of label

5. **Given** administrator changes **Menu label** in plugin settings and saves
   **When** `pr_theme_nav_bootstrap` contains a valid `menu_item_id`
   **Then** `ThemeNavBootstrap::sync_menu_item()` updates the nav menu item title on next `admin_init` or settings save (lightweight sync hook)

### 3. Filter bridge for custom themes

6. **Given** `pr_theme_nav_items` filter with default plugin callback
   **When** theme or mu-plugin calls `pr_theme_nav_items_for_display()`
   **Then** it returns at least one item with `url`, `label`, `slug` = `reviews` when bridge enabled
   **And** theme nav can render without including plugin internal classes

7. **Given** david-sas (or doc-only for v1)
   **When** integration snippet is applied in `header.php`
   **Then** logged-in desktop and mobile drawers show **Reviews** linking to `/reviews/`
   **And** `sas_tt_nav_link_is_current()` / path helper treats `/reviews` prefix as current (add thin wrapper in theme if needed)

### 4. Plugin routes unchanged

8. **Given** a visitor clicks the theme menu link
   **When** they land on `/reviews/`
   **Then** Story 1-7 landing behaviour applies (guest sees login; logged-in users routed per caps)
   **And** theme header/footer still appear on **non-plugin** pages only; `/reviews/` still uses plugin shell without theme chrome (NFR3)

### 5. WP Admin settings (extends 9-3 / 20-1)

9. **Given** user with `pr_manage_settings`
   **When** they open plugin settings
   **Then** they see:
   - **Add Reviews link to site menu on activation** (checkbox, default on) → `theme_nav_auto_bootstrap_enabled`
   - **Menu label** (text, default `Reviews`) → `theme_nav_menu_label`
   - Read-only **Reviews entry URL** with copy button / displayed `home_url('/reviews/')`
   - **Bootstrap status** read-only: `ok` | `manual` | `disabled` | `no_menu_api`
   **And** help text links to Appearance → Menus for manual fix

10. **Given** bootstrap failed (`manual`)
    **When** admin opens any WP Admin screen
    **Then** a dismissible `admin_notice` explains manual menu setup (once per site unless URL changes)

### 6. Tests

11. **And** `tests/ThemeNavBootstrapTest.php` (new) with stubs for `wp_create_nav_menu`, `wp_update_nav_menu_item`, `wp_get_nav_menu_items`, `get_nav_menu_locations`, `set_theme_mod`:
    - `test_activate_creates_menu_item_once`
    - `test_activate_skips_when_disabled`
    - `test_sync_updates_label_when_settings_change`
    - `test_filter_returns_reviews_item`
12. **And** extend `tests/bootstrap.php` only as needed for nav menu stubs
13. **And** `composer test` passes

## Tasks / Subtasks

- [x] **Service:** `includes/services/ThemeNavBootstrap.php` — `on_activate()`, `sync_menu_item()`, location discovery helpers (AC: 1–3, 5)
- [x] **Public API:** `includes/theme-nav.php` — `pr_theme_nav_items_for_display()`, filter registration (AC: 6)
- [x] **Plugin::activate():** call bootstrap after `Routes::register_rewrites()` (AC: 1)
- [x] **PluginSettings:** `theme_nav_auto_bootstrap_enabled`, `theme_nav_menu_label`, `theme_nav_bridge_enabled`; sanitize; defaults (AC: 4–5, 9)
- [x] **Admin settings UI:** `includes/admin/class-admin-settings.php` fields + bootstrap status (AC: 9–10)
- [x] **Hooks:** settings save → sync menu item; optional `admin_notices` (AC: 5, 10)
- [x] **Uninstall:** if data wipe, delete plugin menu item by stored id (AC: Path A uninstall note)
- [x] **Docs in story / README snippet:** david-sas `header.php` integration (~10 lines) for Path B (AC: 7)
- [x] **Tests:** `ThemeNavBootstrapTest.php` (AC: 11–13)

## Dev Notes

### User request (source)

> Adding the Reviews home page menu in the menu bar, upon activation of the plugin. Draw some plans if we are installing this plugin in other themes.

Plans are captured in **Cross-theme installation plan** above; implementation = Path A + Path B.

### Dependencies

| Story | Relevance |
|-------|-----------|
| 1-4 | `/reviews/` rewrite |
| 1-7 | Landing at `/reviews/` — menu must point here, not `/reviews/mark/` |
| 1-8 | Deactivate preserves menu item; uninstall may remove only plugin item |
| 9-3 | Settings page host |
| 20-1 | Short name / display name for labels |

### Suggested `ThemeNavBootstrap` skeleton

```php
final class ThemeNavBootstrap
{
    public const OPTION_BOOTSTRAP = 'pr_theme_nav_bootstrap';
    public const OPTION_STATUS = 'pr_theme_nav_bootstrap_status';

    public static function on_activate(): void
    {
        if (!PluginSettings::theme_nav_auto_bootstrap_enabled()) {
            update_option(self::OPTION_STATUS, 'disabled');
            return;
        }
        if (!function_exists('wp_create_nav_menu')) {
            update_option(self::OPTION_STATUS, 'no_menu_api');
            return;
        }
        // resolve menu → ensure item → assign locations → persist option
    }
}
```

Use `wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => $label, 'menu-item-url' => $url, 'menu-item-status' => 'publish', 'menu-item-type' => 'custom'])`.

### Location priority filter (defaults)

```php
apply_filters('pr_theme_nav_location_priority', ['primary', 'menu-1', 'header', 'main', 'top']);
```

### Idempotency check

Before creating, scan `wp_get_nav_menu_items($menu_id)` for custom link where `url` normalized equals `home_url('/reviews/')` — reuse existing post ID if found (handles manual pre-creation).

### david-sas manual integration (copy for theme PR)

Desktop nav (`header.php` ~153): after logged-in dropdowns, before Timetable:

```php
if (function_exists('pr_theme_nav_items_for_display')) {
    foreach (pr_theme_nav_items_for_display() as $pr_nav_item) {
        $pr_url = (string) ($pr_nav_item['url'] ?? '');
        $pr_lbl = (string) ($pr_nav_item['label'] ?? '');
        $pr_cur = function_exists('sas_tt_is_current_nav_path') && sas_tt_is_current_nav_path($pr_url);
        ?>
        <a href="<?php echo esc_url($pr_url); ?>"
           class="<?php echo esc_attr($nav_link_base . ' ' . ($pr_cur ? $nav_link_current : $nav_link_idle)); ?>">
          <?php echo esc_html($pr_lbl); ?>
        </a>
        <?php
    }
}
```

Mirror in mobile drawer loop. Extend `sas_tt_is_current_nav_path()` to treat path prefix `/reviews` as current if not already.

### Security

- Menu URL is `esc_url(home_url(...))` on output; no user-supplied URL in bootstrap.
- Settings capability `pr_manage_settings` for toggles.

### Regression risks

| Risk | Mitigation |
|------|------------|
| Duplicate menu items on re-activate | Option + URL scan idempotency |
| Wrong menu assigned | Only auto-assign empty locations by default; filter to override |
| Breaks NFR3 on `/reviews/` | No change to `Routes::strip_theme_assets()` |
| Long menu labels | Default **Reviews**, not full Scorva display name |

### File list (expected)

| File | Action |
|------|--------|
| `includes/services/ThemeNavBootstrap.php` | New |
| `includes/theme-nav.php` | New |
| `includes/class-plugin.php` | Call bootstrap on activate |
| `includes/services/PluginSettings.php` | New keys |
| `includes/admin/class-admin-settings.php` | Settings fields |
| `includes/Uninstall.php` or uninstall routine | Remove menu item on full wipe |
| `tests/ThemeNavBootstrapTest.php` | New |
| `tests/bootstrap.php` | Nav menu stubs |

### References

- [Source: includes/class-plugin.php — `activate()`]
- [Source: includes/routes.php — `/reviews/` landing]
- [Source: _bmad-output/implementation/1-7-shared-login-landing-routing.md]
- [Source: _bmad-output/implementation/20-1-configurable-app-branding-scorva-default.md]
- [Source: themes/david-sas/header.php — hardcoded nav pattern]
- [Source: _bmad-output/planning/epics.md — NFR2, NFR3 standalone plugin]
- [WordPress: wp_update_nav_menu_item](https://developer.wordpress.org/reference/functions/wp_update_nav_menu_item/)

## Dev Agent Record

### Agent Model Used

(create-story workflow; dev: Composer)

### Debug Log References

### Completion Notes List

- Path A: `ThemeNavBootstrap::on_activate()` idempotently creates/updates a custom-link nav item to `home_url('/reviews/')`, assigns empty theme menu locations, persists `pr_theme_nav_bootstrap`.
- Path B: `pr_theme_nav_items_for_display()` + `pr_theme_nav_items` filter; david-sas snippet documented in `docs/theme-nav-david-sas-snippet.md`.
- Settings: auto-bootstrap toggle, menu label, bridge toggle, read-only URL + copy, bootstrap status; manual-setup admin notice (dismissible).
- `composer test` — 368 tests OK (4 new in `ThemeNavBootstrapTest`).

### File List

- includes/services/ThemeNavBootstrap.php (new)
- includes/theme-nav.php (new)
- includes/class-plugin.php
- includes/services/PluginSettings.php
- includes/admin/class-admin-settings.php
- includes/Uninstall.php
- includes/Install.php
- tests/ThemeNavBootstrapTest.php (new)
- tests/bootstrap.php
- docs/theme-nav-david-sas-snippet.md (new)

## Change Log

- 2026-05-25: Story created — theme menu bootstrap on activation; cross-theme plan (WP menus + filter bridge + david-sas snippet)
- 2026-05-25: Implemented Path A + B, settings UI, tests, uninstall menu-item removal
