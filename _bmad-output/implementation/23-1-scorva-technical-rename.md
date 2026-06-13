# Story 23.1: Rename technical identity to Scorva

Status: draft

## Story

As the **product owner**,
I want **the plugin's technical identity (folder, repo, slug, text domain, REST namespace, package names) renamed from `project-reviews` to `scorva`**,
So that the codebase, repository, and install footprint carry the product name instead of the working title.

## Background — current behaviour (do not guess)

Story 20-1 deliberately made only the *display* name configurable (default "Scorva: The Review Management System") and froze technical identifiers as `project-reviews`. **This story supersedes that decision** — David has decided the technical identity should be `scorva` everywhere.

Current identifiers:

- Plugin folder: `project-reviews/` (under `wp-content/plugins/`)
- Main file: `project-reviews.php` (`Plugin Name: Scorva` already; `Text Domain: project-reviews`)
- Constants: `PR_PLUGIN_VERSION`, `PR_PLUGIN_SLUG = 'project-reviews'`, `PR_PLUGIN_FILE`, `PR_PLUGIN_DIR`, …
- REST namespace: `Rest_Bootstrap::NAMESPACE = 'project-reviews/v1'` (consumed by built JS bundles)
- Composer package: `sastt/project-reviews`; npm package: `project-reviews`
- GitHub remote: `davidrajm/scora-review-management-system` — note the existing repo name says **"scora"** (missing the v), so it is wrong twice over
- README documents the slug split — must be rewritten
- DB tables/options/capabilities use the short prefix `pr_` (e.g. `pr_marks`, `pr_plugin_settings`, `pr_manage_settings`)

## Acceptance Criteria

1. **Given** the plugin directory
   **When** renamed to `scorva/` with main file `scorva.php`
   **Then** WordPress re-activation works, `PR_PLUGIN_FILE`/`PR_PLUGIN_DIR` resolve, and the `active_plugins` entry is updated (deactivate → rename → activate is acceptable for the dev site; document the step)

2. **Given** the text domain
   **When** changed to `scorva` in the plugin header and all `__()`/`_e()`/`esc_html__()` calls and JS i18n config
   **Then** no string keeps the old `project-reviews` domain (grep returns zero hits outside build artefacts and vendored code)

3. **Given** the REST namespace
   **When** `Rest_Bootstrap::NAMESPACE` becomes `scorva/v1`
   **Then** all frontend API clients (shared `api.js`, `prAppData.root` consumers, Playwright helpers) use the new namespace
   **And** assets are rebuilt so `build/` bundles reference `scorva/v1`
   **And** PHPUnit route tests (`RoutesTest`, `RestPortalTest`, etc.) are updated

4. **Given** package metadata
   **When** renamed
   **Then** `composer.json` name is `sastt/scorva` (autoload PSR-4 namespace decision documented — keeping `ProjectReviews\` PHP namespace vs renaming to `Scorva\` is an explicit task choice; renaming requires `composer dump-autoload` and the macOS case-insensitivity gotcha from story 26 of the auth rebuild applies)
   **And** `package.json` name is `scorva`

5. **Given** the GitHub repository
   **When** renamed to `scorva` (or `scorva-review-management-system`)
   **Then** the local `origin` remote URL is updated and a push round-trips (GitHub auto-redirects old names, but the remote should not rely on the redirect)

6. **Given** the DB layer (`pr_` table prefix, option keys, capability slugs, `pr_` PHP constant prefix)
   **Then** these are **explicitly decided**: either (a) keep `pr_` as a stable short prefix (recommended — zero data migration, prefix is not user-visible), or (b) full rename with a wipe-and-reinstall of the dev site (acceptable per the 2026-06-12 decision that existing data doesn't matter, but touches `Install::*`, `FakeWpdb` tests, uninstall.php, backup SQL export). Record the choice in the README.

7. **Given** docs
   **Then** README.md no longer describes the `project-reviews` slug split, and CLAUDE/bmad docs referencing the old slug are updated.

## Tasks / Subtasks

- [ ] Decide AC6 (pr_ prefix keep vs rename) and AC4 (PHP namespace) before touching code
- [ ] Rename folder + main file; update plugin header (Text Domain: scorva)
- [ ] Update `PR_PLUGIN_SLUG` value, rewrite-rule slugs in `includes/routes.php` if they embed the slug
- [ ] `Rest_Bootstrap::NAMESPACE` → `scorva/v1`; update `src/shared/api.js` and any hardcoded `/wp-json/project-reviews` strings (incl. tests, Playwright config)
- [ ] Sweep text domain across PHP + `src/`
- [ ] composer.json / package.json rename; `composer dump-autoload`; rebuild assets
- [ ] Rename GitHub repo; `git remote set-url origin …`
- [ ] Update README.md; full-repo grep for `project-reviews` to catch stragglers
- [ ] Run PHPUnit + Playwright suites

## Dev Notes

### Risks / edge cases

- Local (the dev environment) maps the site by path; renaming the plugin folder does not affect the site root, but any IDE workspace, `.claude/` project memory path, and shell history reference the old path.
- Rewrite rules: if landing/coordinator/reviewer routes derive from `PR_PLUGIN_SLUG`, flush rewrite rules after rename (`pr_rewrite_version` bump).
- The reviewer portal token links emailed before the rename will embed old URLs if routes change — regenerate/resend credentials on the dev site after rename.

### Out of scope

- Display-name branding (done in 20-1).
- Renaming the `ProjectReviews\` PHP namespace if the AC4 decision is "keep".

### References

- `project-reviews.php`, `includes/rest/class-rest-bootstrap.php:9`, `includes/routes.php`, `composer.json`, `package.json`, `README.md`
- Story 20-1 (`20-1-configurable-app-branding-scorva-default.md`) — superseded technical-identity decision
