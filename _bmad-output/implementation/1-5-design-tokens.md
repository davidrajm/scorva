# Story 1.5: Design tokens and shared UI primitives

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a **user**,
I want consistent Project Reviews branding and reusable UI primitives,
So that coordinator and reviewer apps share a calm, academic visual language.

## Acceptance Criteria

1. **Given** a logged-in user on a plugin route (`pr_app` = `coordinator` or `reviewer`) **When** the shell loads **Then** `assets/css/app-shell.css` (or its PostCSS build output) is enqueued and theme assets remain stripped (NFR3).
2. **Given** the page loads **When** tokens are inspected on `#pr-root` **Then** CSS custom properties match the UX spec palette (primary `#1e4d6b`, surfaces, text, border, status colors) plus layout tokens: top bar **56px**, coordinator sidebar **240px**, content max-width **1280px**, card radius **8px**, card shadow `0 1px 3px rgba(0,0,0,.08)`, and system font stack.
3. **Given** the webpack toolchain **When** `npm run build` runs **Then** Tailwind is configured via PostCSS with `theme.extend` mapping colors/spacing/typography to `var(--pr-*)` and utilities scoped under `#pr-root` (no bleed to `body` outside the mount).
4. **Given** shared React primitives **When** imported from `src/shared/components/` **Then** these exist and use token-backed Tailwind classes: `Button`, `PageHeader`, `StatusChip`, `EmptyState`, `Card`, and a minimal `SessionCard` stub (title + `StatusChip` + placeholder progress area).
5. **Given** `AppShell` markup **When** rendered (component export; full mount is story 1.6) **Then** it includes a visually hidden skip link (“Skip to main content”) targeting `#pr-main`, `nav` landmark for coordinator sidebar, Direction 1 layout structure (top bar + optional 240px sidebar), and `data-app` coordinator vs reviewer layout (sidebar only for coordinator).
6. **Given** PHPUnit **When** the suite runs **Then** route tests assert `app-shell` stylesheet is enqueued on plugin routes and not on non-plugin requests; full suite stays green.

## Tasks / Subtasks

- [x] Add `assets/css/app-shell.css` with documented `:root` tokens (table below) and base shell layout rules using layout CSS variables
- [x] Add `package.json`, `postcss.config.js`, `tailwind.config.js`, `webpack.config.js` (`@wordpress/scripts` + `tailwindcss`); entries may be minimal stubs that import shared styles for Tailwind content scanning
- [x] Add `src/shared/styles.css` (or equivalent) with `@tailwind` layers; ensure build produces enqueueable CSS if PostCSS emits a separate file
- [x] Implement `src/shared/components/` — `Button`, `PageHeader`, `StatusChip`, `EmptyState`, `Card`, `SessionCard` (stub), barrel `index.js`
- [x] Implement `src/shared/AppShell.jsx` with skip link, top bar, coordinator sidebar slot, `#pr-main` content wrapper
- [x] Wire `Routes::enqueue_app_assets()` (or similar) on `pr_app` routes: enqueue **CSS only**; priority **after** `strip_theme_assets` (99999) so plugin CSS is not dequeued
- [x] Extend `tests/RoutesTest.php` (or add `AssetsTest.php`) for stylesheet enqueue on plugin routes
- [x] Run `npm run build` and full PHPUnit — all green
- [x] Do **not** enqueue `build/coordinator.js` / `build/reviewer.js` or `wp_localize_script` (story 1.6)

## Dev Notes

### Scope boundary (critical)

| In scope (1.5) | Deferred to 1.6 |
|----------------|-----------------|
| `app-shell.css` tokens + enqueue | React SPA mount on `#pr-root` |
| Tailwind / PostCSS / webpack toolchain | `wp_localize_script` (`restUrl`, `nonce`) |
| Shared presentational components + `AppShell` export | HashRouter, coordinator/reviewer pages |
| Skip link + shell layout markup in `AppShell` | `src/shared/api.js`, session fetch |
| PHPUnit enqueue assertion | E2E flows |

Story 1.4 intentionally did **not** enqueue CSS/JS. `includes/routes.php` already strips all queued assets at priority 99999 — register and enqueue plugin styles at **100000+** on the same hook.

### Token table (implement in `app-shell.css`)

Scope variables under `#pr-root` (or `:root` if tokens must be read outside React during shell load — prefer `#pr-root, :root` with tokens on `:root` for WP admin parity later).

| Variable | Value | Notes |
|----------|-------|-------|
| `--pr-color-primary` | `#1e4d6b` | Brand, primary actions |
| `--pr-color-primary-hover` | `#163a52` | Primary hover |
| `--pr-color-surface` | `#f6f8fa` | Page background |
| `--pr-color-surface-raised` | `#ffffff` | Cards, modals |
| `--pr-color-border` | `#d0d7de` | Dividers, inputs |
| `--pr-color-text` | `#1f2328` | Body text |
| `--pr-color-text-muted` | `#656d76` | Labels, hints |
| `--pr-color-success` | `#1a7f37` | Confirmed / submitted |
| `--pr-color-warning` | `#9a6700` | Unlocked / flagged |
| `--pr-color-danger` | `#cf222e` | Errors, destructive |
| `--pr-color-info` | `#0969da` | Info banners |
| `--pr-chip-draft-bg` | `#eaeef2` | StatusChip draft |
| `--pr-chip-draft-text` | `#656d76` | |
| `--pr-radius-md` | `8px` | Cards, chips |
| `--pr-shadow-card` | `0 1px 3px rgba(0,0,0,.08)` | |
| `--pr-font-family` | `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif` | No webfonts MVP |
| `--pr-layout-topbar-height` | `56px` | Direction 1 |
| `--pr-layout-sidebar-width` | `240px` | Coordinator only |
| `--pr-layout-content-max-width` | `1280px` | Centered main column |

Add inline comments in CSS mirroring UX spec § Visual Design Foundation.

### Tailwind configuration

```javascript
// tailwind.config.js — pattern (adapt paths)
module.exports = {
  content: ['./src/**/*.{js,jsx}'],
  important: '#pr-root',
  theme: {
    extend: {
      colors: {
        primary: 'var(--pr-color-primary)',
        'primary-hover': 'var(--pr-color-primary-hover)',
        surface: 'var(--pr-color-surface)',
        'surface-raised': 'var(--pr-color-surface-raised)',
        border: 'var(--pr-color-border)',
        // ... map remaining tokens
      },
      borderRadius: { md: 'var(--pr-radius-md)' },
      fontFamily: { sans: ['var(--pr-font-family)'] },
      spacing: { 1: '4px', 2: '8px', 3: '12px', 4: '16px', 6: '24px', 8: '32px', 12: '48px' },
    },
  },
  plugins: [],
};
```

Use `@wordpress/scripts` default webpack config extended with two entries (`coordinator`, `reviewer`) per implementation plan Task 13 — entries can be one-line imports of `../shared/styles.css` until 1.6 adds React roots.

**No imports** from `david-sas` theme paths (NFR3, UX-DR3).

### Component contracts (minimal MVP)

- **Button** — variants: `primary` | `secondary` | `ghost` | `destructive`; props: `variant`, `size`, `disabled`, `loading`, `type`, `onClick`, `children`. Primary = filled primary color, white text. One primary per screen is a usage rule, not enforced in code yet.
- **PageHeader** — `title` (32px/semibold), optional `description`, optional `actions` slot.
- **StatusChip** — `variant`: `draft` | `active` | `closed` | `confirmed` | `unlocked` | `flagged`; always render **text label** (never color-only). Map variants to token pairs per UX spec.
- **EmptyState** — `title`, `description`, optional `action` (React node).
- **Card** — raised surface, border, radius, shadow, padding 24px; optional `onClick`.
- **SessionCard** (stub) — `title`, `status` (StatusChip variant), optional `progress` placeholder; composes `Card` + `StatusChip`.
- **AppShell** — props: `variant: 'coordinator' | 'reviewer'`, `children`. Structure:

```jsx
// Structural requirement (simplified)
<a href="#pr-main" className="sr-only focus:not-sr-only">Skip to main content</a>
<header>{/* wordmark "Project Reviews" 20px semibold primary */}</header>
{variant === 'coordinator' && <nav aria-label="Main">...</nav>}
<main id="pr-main">{children}</main>
```

Use Tailwind `sr-only` / focus-visible ring using primary token. Reviewer variant: **no sidebar**; coordinator: fixed sidebar width 240px.

### PHP enqueue pattern

```php
// In Routes::handle_template() after strip_theme_assets(), before include template:
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'project-reviews-app-shell',
        plugins_url('assets/css/app-shell.css', PR_PLUGIN_FILE),
        [],
        PR_PLUGIN_VERSION
    );
}, 100000);
```

If PostCSS emits to `build/style-coordinator.css`, enqueue that path instead and document in File List. Handle `PR_UNIT_TEST`: set `$GLOBALS['pr_test_enqueued_styles']` for assertions (mirror existing route test globals).

### Testing

- Extend `tests/bootstrap.php` if needed to capture `wp_enqueue_style` calls.
- Assert enqueue on `pr_app=coordinator` and `reviewer`; assert no enqueue when query var absent.
- Manual: logged-in visit `/reviews/` — computed styles on `#pr-root` show `--pr-color-primary: #1e4d6b`.
- `npm run build` must succeed (document in Dev Agent Record if build artifacts committed per team policy).

### Previous story intelligence (1.4)

- `templates/app-shell.php` already exposes `#pr-root` with `data-app`; do not add theme header/footer.
- `Routes::strip_theme_assets()` dequeues **all** handles — plugin assets must enqueue **after** that pass.
- PHPUnit uses `PR_UNIT_TEST` + globals (`pr_test_template_included`, etc.) — follow same pattern for style enqueue.
- Activation flush for rewrites unchanged; no capability gate on routes (login only).

### Git / codebase state

- Committed: plugin scaffold (1.1), install schema. Uncommitted work in tree: capabilities (1.2), REST auth (1.3), routes (1.4) — build on that branch state.
- **No** `package.json`, `src/`, `assets/`, or `build/` yet — greenfield for front-end toolchain.

### Anti-patterns (do not)

- Enqueue `david-sas` or theme styles/scripts.
- Mount React roots or add HashRouter (1.6).
- Add domain REST routes or session UI.
- Use Material/Ant Design full kits.
- Rely on color alone in `StatusChip` (always show label text).

### References

- [Source: _bmad-output/planning/epics.md — Story 1.5, UX-DR1–4, UX-DR8, UX-DR17–18, UX-DR24–25, UX-DR27]
- [Source: _bmad-output/planning/ux-design-specification.md — Design System Foundation, Visual Design Foundation, Component Strategy]
- [Source: _bmad-output/planning/ux-design-directions.html — Direction 1 Structured Academic]
- [Source: david-sas/docs/superpowers/plans/2026-05-16-project-reviews-plugin.md — Task 12 Step 3 (enqueue), Task 13 (toolchain + tokens)]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §4 Product identity]
- [Source: _bmad-output/implementation/1-4-front-end-routes.md — deferred CSS/JS]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

- `npm run build` — webpack 5.106.2, coordinator/reviewer CSS ~16 KiB each (Tailwind from `src/shared/styles.css`).
- `./vendor/bin/phpunit` — 35 tests, 122 assertions, OK.

### Completion Notes List

- Added `assets/css/app-shell.css` with full UX token set, chip tokens, and Direction 1 shell layout (top bar, sidebar, main).
- Front-end toolchain: `package.json`, PostCSS, Tailwind (`important: '#pr-root'`), webpack entries for coordinator/reviewer stub JS importing shared styles.
- Shared React primitives and `AppShell` export (skip link via `.pr-skip-link`, coordinator `nav`, `data-app` on `.pr-shell`).
- `Routes::enqueue_app_assets()` at priority 100000; CSS only — no JS enqueue (1.6).
- PHPUnit: `pr_test_enqueued_styles`, priority-ordered `wp_enqueue_scripts` simulation, four new route enqueue tests.
- **Build artifacts:** `build/` generated locally via `npm run build`; not required for runtime (PHP enqueues `assets/css/app-shell.css` only). Regenerate after component/style changes; commit policy left to team.

### File List

- assets/css/app-shell.css
- package.json
- postcss.config.js
- tailwind.config.js
- webpack.config.js
- src/shared/styles.css
- src/shared/AppShell.jsx
- src/shared/components/Button.jsx
- src/shared/components/PageHeader.jsx
- src/shared/components/StatusChip.jsx
- src/shared/components/EmptyState.jsx
- src/shared/components/Card.jsx
- src/shared/components/SessionCard.jsx
- src/shared/components/index.js
- src/coordinator/index.js
- src/reviewer/index.js
- includes/routes.php
- tests/bootstrap.php
- tests/RoutesTest.php
- .gitignore

## Change Log

- 2026-05-16: Story created (create-story workflow) — ready-for-dev
- 2026-05-16: Implemented design tokens, toolchain, shared components, app-shell enqueue, PHPUnit (dev-story) — review
