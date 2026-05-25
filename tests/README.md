# Project Reviews — testing guide

Reference for validating the plugin locally or on staging. Story **15-1** (E2E harness + opt-in teardown).

**Quick links**

| Goal | Jump to |
|------|---------|
| **Walk through the UI yourself** | [Manual check (step by step)](#manual-check-step-by-step) |
| **Slow automated demo (coordinator / reviewer)** | [Walkthrough specs](#walkthrough-headed-slow-demo) |
| Fast regression (no WordPress) | [PHPUnit](#phpunit-no-wordpress-required) |
| Automated browser journey | [Playwright UI E2E](#playwright-ui-e2e-live-wordpress) |
| Reset test/staging data | [Teardown](#teardown-opt-in-only) |
| Printable checklist | [MVP checklist](e2e/MVP_CHECKLIST.md) |
| SQL / phpMyAdmin reset | [SQL helpers](#sql-helpers-manual-database) |
| SOP PDF screenshots | [SOP screenshots](#sop-documentation-screenshots) |

---

## Recommended order

Use this sequence when you want confidence before a release or after a big change.

| Step | Command | Needs WP? | Time |
|------|---------|-----------|------|
| 1 | `composer test` | No | ~1s |
| 2 | `composer test:journey` | No | &lt;1s |
| 3 | `npm run build` | No (but for UI) | ~5s |
| 4 | Seed E2E users (once per site) | Yes | 1 min |
| 5a | [Manual check](#manual-check-step-by-step) in browser | Yes | 10–15 min |
| 5b | `npm run test:e2e:headed` (same flow, automated) | Yes | ~10 s |
| 6 | `composer test:teardown -- --confirm` | Yes | Only when wiping data |

Steps 1–3 are enough for day-to-day development. Step 4 + **5a or 5b** prove the real site. Step 6 only on throwaway/staging data.

---

## Manual check (step by step)

Use this when you want to **click through the product yourself** (no Playwright). It mirrors `tests/e2e/specs/full-plugin-ui-journey.spec.ts`.

Replace `http://sastt.local` with your site URL (same value as `PR_E2E_BASE_URL` in `tests/e2e/.env.local`).

### Before you start

```bash
cd wp-content/plugins/project-reviews
composer install && npm install && npm run build
```

From **WordPress root** (`public`):

```bash
php wp-content/plugins/project-reviews/tests/e2e/bin/seed-e2e-users.php
```

Confirm in the browser: **Plugins → Scorva** is **Active**, and **Settings → Permalinks → Save** has been clicked once.

| Account | Login | Password |
|---------|-------|----------|
| Coordinator | `pr_e2e_coordinator` | `pr-e2e-change-me` |
| Reviewer | `pr_e2e_reviewer` | `pr-e2e-change-me` |

Reviewer email for the wizard (must match exactly): `pr_e2e_reviewer@example.test`

### 1. Guest landing

| | |
|---|---|
| **URL** | `http://sastt.local/reviews/` |
| **Expect** | Heading **Scorva: The Review Management System** (default display name) and a **Log in** button (no left sidebar) |
| **If wrong** | Theme home / timetable → activate plugin + flush permalinks (see [§4](#4-flush-permalinks-required-once-per-site)) |

Click **Log in**, sign in as **coordinator**.

### 2. Create project and add students in wizard

| | |
|---|---|
| **URL** | `http://sastt.local/reviews/#/` then wizard **Students** |
| **Expect** | **Add students** step with **Add student** and **Import Students** |

For each student (use unique reg numbers, e.g. `E2E-MANUAL-001-A` / `E2E-MANUAL-001-B`):

1. **Create project** on the dashboard (title only is fine).
2. On wizard **Students**, click **Add student**.
3. Fill **Registration number** * and **Name** * (Program / Batch optional).
4. Click **Add to project**.
5. Confirm the reg no appears in **Students Added to this Project**.

**Optional:** **Student directory** at `#/registry` for bulk import or custom fields — not required before each project.

### 3. Wizard — Students → Panels → Reviewers

**Students:** both students listed → **Continue to Panels**.

**Panels:**

1. Enter a panel name → **Add panel**.
2. For each student, set the panel dropdown to your new panel.
3. **Continue to Reviewers**.

**Reviewers:**

1. Name + email `pr_e2e_reviewer@example.test` → **Add reviewer**.
2. **Important:** click **Send credentials** on that row and wait for **Account linked**.  
   (Email alone does not link the WordPress user; reviewers will see **No assignments** until linked.)
3. Open **Reviews & rubrics** (tab or continue).

### 5. Wizard — Reviews & rubrics

1. Under **Review 1**, set criterion label (e.g. `Technical quality`) and **Max marks** (e.g. `10`) — both required.
2. **Save**.
3. **Confirm** → in the dialog, **Confirm rubric** (not only the table Confirm button).
4. Status should show **Confirmed**; **Total marks** should show `10`.

If **Panel assignments** tab stays disabled, **refresh the page** (wizard tab flags load from the server on first load).

### 6. Wizard — Panel assignments → Open reviews

1. **Panel assignments** tab → **Continue to Open reviews** (defaults from the Panels step are usually enough).
2. **Open for marking** (project becomes active).
3. **Start marking** on Review 1 → **Marking open**.

### 7. Reviewer — save a mark

| | |
|---|---|
| **URL** | `http://sastt.local/reviews/mark/#/` |
| **Login** | `pr_e2e_reviewer` / `pr-e2e-change-me` |

1. Open your project card (not **Freeze scores** at the top).
2. **Update score** on a student row.
3. Enter a score (e.g. `8`) in the modal → **Save**.
4. Grid should show the score and status **Draft** for that row.

### 8. Coordinator — progress and reports

Log in as coordinator again.

| Step | URL | Expect |
|------|-----|--------|
| Progress | `http://sastt.local/reviews/#/session/{id}/progress` | **Marking progress**; **Student** dropdown lists your students |
| Reports | `http://sastt.local/reviews/#/session/{id}/reports` | **Downloads** tab → **Download Excel** (or CSV) saves a file |

Replace `{id}` with the numeric project id from the wizard URL (`#/session/4/wizard` → id `4`).

### 9. Coordinator — close and delete project

Sidebar **End project** → **Close project** (`#/session/{id}/close`).

| Step | Expect |
|------|--------|
| Close | **Close project…** → confirm **Close this project?** → success notice (e.g. “Project closed”) |
| Reopen block | **Reopen project** section visible while status is closed |
| Delete | Scroll to **Delete project** → **Delete project…** |
| Delete (no scores) | Single confirm dialog → redirect to dashboard with “was permanently deleted” |
| Delete (has scores) | Dialog requires typing the **exact project title** → **Delete project and scores** |
| Dashboard | Project no longer listed; green success notice at top |

Optional: create a second empty draft project and delete it to exercise the simple confirm (no typed title).

### Stuck? Quick map

| What you see | What to do |
|--------------|------------|
| **Scorva** display name + **Log in** only | Guest — click **Log in** |
| **wp-admin** after login | Open `/reviews/` |
| Blank `/reviews/` | `npm run build`; plugin active |
| Theme / timetable, not review app | Activate plugin; flush permalinks |
| Registry: filled reg no, cannot add Name | Use the **Add student** form fields, not table search |
| Reviewer: **No assignments yet** | Coordinator: **Send credentials** → **Account linked** |
| **Panel assignments** tab disabled | Save rubric with max marks, confirm rubric, then refresh page |
| Rubric: “Each criterion needs max marks…” | Fill **Max marks** before Save/Confirm |

Printable checklist: [`tests/e2e/MVP_CHECKLIST.md`](e2e/MVP_CHECKLIST.md).

---

## Prerequisites

### All tests

```bash
cd wp-content/plugins/project-reviews
composer install
npm install
```

### PHPUnit only

- PHP 8.2+ and Composer dependencies (`vendor/`).

### Browser / Playwright

- WordPress running with **Scorva** (project-reviews plugin) active.
- Permalinks saved so `/reviews/` and `/reviews/mark/` load the SPAs.
- Built assets: `npm run build` (run after React changes).
- Chromium (once): `npx playwright install chromium`

---

## PHPUnit (no WordPress required)

In-memory tests use `tests/bootstrap.php` stubs and `FakeWpdb`. No database, no HTTP server.

### Commands

```bash
composer test              # full suite (unit + @group journey)
composer test:journey      # journey tests only
./vendor/bin/phpunit
./vendor/bin/phpunit --group journey
```

**Expected:** all tests green (currently **347** tests including **10** journey tests in `tests/FullPluginJourneyTest.php`).

### What journey tests cover

| Test | What it checks |
|------|----------------|
| `test_happy_path_coordinator_setup_through_reports_export` | Setup chain, progress REST, XLSX + CSV export bodies |
| `test_reviewer_assignment_scoping_and_blocked_states` | `rubric_not_confirmed`, `session_closed`, `not_assigned` |
| `test_session_close_and_reopen_lifecycle` | Close + reopen, marking allowed again |
| `RestSessionsTest` delete cases | Delete without scores, with title confirm, closed project delete |
| `test_rubric_reconfirm_keep_flag_and_clear` | Reconfirm with `keep_flag`, flagged marks |
| `test_unfreeze_request_flows` | Reviewer request → panel head grant |
| `test_faculty_pool_bulk_invite` | Faculty CSV import + bulk invite |
| `test_panel_head_pdf_and_freeze` | Panel freeze + PDF bytes (`%PDF`) |
| `test_audit_override_requires_reason` | Reason length + audit log row |
| `test_plugin_lifecycle_deactivate_preserves_data` | Deactivate does not DROP tables |
| `test_teardown_truncate_leaves_schema` | Truncate empties rows, schema + view remain |

Shared fixture: `tests/support/ScenarioBuilder.php`.

---

## Playwright UI E2E (live WordPress)

Automated spec: `tests/e2e/specs/full-plugin-ui-journey.spec.ts`  
Manual mirror: `tests/e2e/MVP_CHECKLIST.md`

### 1. Install browser (once per machine)

```bash
npx playwright install chromium
```

### 2. Create test users

**Option A — WP-CLI seed script (recommended)**

From your **WordPress root** — the folder that contains `wp-load.php` (e.g. `.../app/public`), **not** the plugin folder:

```bash
cd /path/to/your/public

# Option A — plain PHP (recommended)
php wp-content/plugins/project-reviews/tests/e2e/bin/seed-e2e-users.php

# Option B — WP-CLI
wp eval-file wp-content/plugins/project-reviews/tests/e2e/bin/seed-e2e-users.php
```

Default logins: `pr_e2e_coordinator`, `pr_e2e_reviewer`.  
Default password: `pr-e2e-change-me` (override with `PR_E2E_DEFAULT_PASSWORD`).

Users get usermeta `pr_test_fixture = 1` so teardown can delete them safely.

**Option B — manual**

Create two accounts in WP Admin:

| Account | Role |
|---------|------|
| Coordinator | `project_reviews_coordinator` (or admin with plugin caps) |
| Reviewer | `project_reviews_reviewer` |

Add usermeta `pr_test_fixture` = `1` on both (or use the seed script once to tag them).

### 3. Environment variables

Copy the example file, edit `PR_E2E_BASE_URL` in your editor, then load (do **not** paste comment lines into the terminal):

```bash
cp tests/e2e/env.example tests/e2e/.env.local
# edit tests/e2e/.env.local in Cursor/your editor

source tests/e2e/load-env.sh
```

Or export manually:

```bash
export PR_E2E_BASE_URL="http://sastt.local"       # WP Local — no trailing slash
# export PR_E2E_BASE_URL="http://localhost:10008" # wp-env, etc.
export PR_E2E_COORD_USER="pr_e2e_coordinator"
export PR_E2E_COORD_PASS="pr-e2e-change-me"
export PR_E2E_REVIEWER_USER="pr_e2e_reviewer"
export PR_E2E_REVIEWER_PASS="pr-e2e-change-me"
export PR_E2E_REVIEWER_EMAIL="pr_e2e_reviewer@example.test"
```

| Variable | Required | Notes |
|----------|----------|-------|
| `PR_E2E_BASE_URL` | No | Default `http://localhost:10008` |
| `PR_E2E_COORD_USER` / `PR_E2E_COORD_PASS` | Yes | Coordinator login |
| `PR_E2E_REVIEWER_USER` / `PR_E2E_REVIEWER_PASS` | Yes | Reviewer login |
| `PR_E2E_REVIEWER_EMAIL` | Yes | Must match email used in wizard reviewer step |

If any required variable is missing, specs **skip** with a clear message (not a false pass).

### 4. Flush permalinks (required once per site)

Open **`http://sastt.local/reviews/`** in the browser (use your real Local URL). You must see:

- Heading **Scorva: The Review Management System** (or configured `app_display_name`)
- A **Log in** button

If you see the theme homepage, timetable, or 404 instead, rewrites are stale:

1. WP Admin → **Settings → Permalinks → Save Changes** (no need to change settings), **or**
2. From WordPress root: `wp rewrite flush`

Then retry the URL before running Playwright.

### 5. Build and run Playwright

```bash
npm run build
source tests/e2e/load-env.sh    # loads tests/e2e/.env.local
npm run test:e2e                # UI journey only (headless) — expect 6 passed
npm run test:e2e:headed         # same journey, visible browser (~15s)
npm run walkthrough:coordinator # slow coordinator demo (see below)
npm run walkthrough:reviewer    # slow reviewer demo (after coordinator)
npm run walkthrough:all         # coordinator setup → reviewer → optional finish
npm run test:e2e:all            # journey + SOP screenshot spec
npm run test:e2e:sop            # SOP captures only
npm run test:e2e:ui             # Playwright UI mode
```

First time on a machine: `npx playwright install chromium`.

### Walkthrough (headed, slow demo)

Use when you want Playwright to **drive the browser step by step** so you can read each screen before it continues (training, demos, SOP rehearsal).

| Command | Who | What |
|---------|-----|------|
| `npm run walkthrough:coordinator` | Coordinator | Registry → wizard → open for marking (10 steps) |
| `npm run walkthrough:reviewer` | Reviewer | Assignments → **Update score** (5 steps; needs coordinator first) |
| `npm run walkthrough:coordinator:finish` | Coordinator | Progress, reports, close, delete (after reviewer) |
| `npm run walkthrough:all` | Both | Runs coordinator setup + reviewer in one session |

Each step shows a **dark banner** at the bottom of the page and logs `▶ Walkthrough: [n/total] …` in the terminal. Defaults:

- **Pause between steps:** 3.5s (`PR_E2E_WALKTHROUGH_PAUSE_MS`, e.g. `5000` for five seconds)
- **Slow click typing:** 800ms (`PR_E2E_WALKTHROUGH_SLOW_MO`, e.g. `1200` for slower)

Example (more time per step):

```bash
source tests/e2e/load-env.sh
php ../public/wp-content/plugins/project-reviews/tests/e2e/bin/seed-e2e-users.php   # from WP public root
PR_E2E_WALKTHROUGH_PAUSE_MS=6000 PR_E2E_WALKTHROUGH_SLOW_MO=1200 npm run walkthrough:coordinator
PR_E2E_WALKTHROUGH_PAUSE_MS=6000 npm run walkthrough:reviewer
```

Specs: `tests/e2e/specs/coordinator-walkthrough.spec.ts`, `reviewer-walkthrough.spec.ts`. Coordinator run writes `tests/e2e/.walkthrough-state.json` (gitignored) so the reviewer run can open the same project.

### 6. What the automated UI spec does

Spec: `tests/e2e/specs/full-plugin-ui-journey.spec.ts` (same flow as [Manual check](#manual-check-step-by-step)).

1. Guest at `/reviews/` sees **Log in**
2. **Dashboard** — **Create project** with title only (`data-testid`: `pr-show-create-project`, `pr-project-title`)
3. Wizard **Students** — **Add student** for two students (`pr-wizard-student-reg-no`, `pr-wizard-student-name`, **Add to project**)
4. Wizard **Students** → **Panels** → **Reviewers** (add email → **Send credentials** → **Account linked**)
5. **Reviews & rubrics** — max marks + **Save** → **Confirm** → dialog **Confirm rubric** → page reload (refresh wizard tab state)
6. **Panel assignments** → **Open reviews** → **Open for marking** → **Start marking**
7. Reviewer at `/reviews/mark/` — **Update score** modal → score `8` → **Draft** in grid
8. Coordinator **Progress** (student in dropdown) → **Reports → Downloads** (`.xlsx` or `.csv`)
9. **End project → Close project** — **Close project…** → closed status + reopen section
10. **Delete project…** — type exact project title (scores exist) → dashboard success notice; project gone
11. Second spec: create draft project → delete with single confirm (no scores)

Config: `playwright.config.ts` (`testIdAttribute: 'data-testid'`, `baseURL` from `PR_E2E_BASE_URL`).

### WP Local + Terminal PHP (“Error establishing a database connection”)

The site works in the browser but `php …/seed-e2e-users.php` fails because **Terminal uses Homebrew PHP**, while Local’s MySQL listens on a **socket** (not port 3306). `wp-config.php` often has `DB_HOST` = `localhost`, which is wrong for CLI.

**Fix (automatic):** seed and teardown scripts load `tests/e2e/bin/wp-local-db-bootstrap.php`, which sets `DB_HOST` to Local’s `mysqld.sock` when found under `~/Library/Application Support/Local/run/*/mysql/`.

From WordPress root (`public`):

```bash
php wp-content/plugins/project-reviews/tests/e2e/bin/seed-e2e-users.php
```

**Optional overrides:**

```bash
# Manual host (socket or host:port from Local → Database tab)
export PR_DB_HOST="localhost:/Users/you/Library/Application Support/Local/run/SITE_ID/mysql/mysqld.sock"
```

**Alternatives:**

- In Local app: right-click site → **Open site shell** → run the same `php` command (shell may already have correct env).
- Use Local’s **Site shell** + `wp eval-file …` if WP-CLI is installed there.

**Optional (removes PHP 9 warning):** in `wp-config.php`, wrap the host define:

```php
if ( ! defined( 'DB_HOST' ) ) {
    define( 'DB_HOST', 'localhost' );
}
```

### Troubleshooting (Playwright and manual)

| Symptom | Check |
|---------|--------|
| Tests skipped | `source tests/e2e/load-env.sh`; all `PR_E2E_*` vars set; users seeded |
| Login fails | Roles assigned; `PR_E2E_BASE_URL` matches browser host (e.g. `http://sastt.local`) |
| Blank SPA | `npm run build`; plugin active; visit `/reviews/` manually |
| **Log in** not found / timetable page | Flush permalinks; confirm `/reviews/` shows the review app landing (not theme home) |
| Stuck on registry after reg no | Fill **Name** in the **Add student** form, not **Search students** |
| Reviewer **No assignments yet** | Coordinator ran **Send credentials** and row shows **Account linked** |
| **Panel assignments** tab disabled | Rubric has max marks, **Confirm rubric** in dialog; refresh wizard page |
| Playwright timeout on `Name` | Fixed in spec via `#student-name`; update if you fork the spec |
| Wizard stuck | Reviewer email in wizard = `PR_E2E_REVIEWER_EMAIL` |
| Timeout | `npx playwright install chromium`; run `npm run test:e2e:headed` to watch |
| DB error running seed in Terminal | WP Local running; use `php …/seed-e2e-users.php` from `public` folder |

---

## Teardown (opt-in only)

**Never** runs from `composer test`, `npm run test:e2e`, `npm run build`, plugin deactivation, or uninstall.

Requires WordPress (`wp-load.php` relative to standard `wp-content/plugins/project-reviews` layout).

### Modes

| Mode | Command | Tables | Plugin options | WP users |
|------|---------|--------|----------------|----------|
| **Per-test** | PHPUnit `setUp` | `FakeWpdb` reset | N/A | Stub IDs discarded |
| **Truncate** (default) | `--confirm` | TRUNCATE all `Install::get_pr_table_names()` | Kept | Deletes `pr_test_fixture = 1` |
| **Full drop** | `--confirm --full-drop --force-local` | `Install::drop_all()` + recreate on activate | Recreated | Fixture users deleted |

### Commands

```bash
# Preview only (exit 0)
composer test:teardown -- --dry-run

# Truncate plugin data + delete fixture users
composer test:teardown -- --confirm

# Also remove plugin options
composer test:teardown -- --confirm --purge-options

# Disposable local DB only — DROP all plugin tables
composer test:teardown -- --confirm --full-drop --force-local
```

Or set `PR_TEST_TEARDOWN_CONFIRM=1` instead of `--confirm`.

### Safety guards

- Without `--confirm` or `--dry-run`: prints plan and exits **non-zero**.
- Refuses when `PR_TEST_TEARDOWN_DISABLED` is defined (unless `--force-local`).
- Refuses when `wp_get_environment_type()` is not `local` or `development` (unless `--force-local`).

Implementation: `includes/testing/TestTeardown.php`, CLI: `bin/pr-test-teardown.php`.

---

## SQL helpers (manual database)

For phpMyAdmin, TablePlus, or mysql CLI. Replace `wp_` with your `$table_prefix` if different.

| File | Purpose |
|------|---------|
| `tests/sql/00_reset_pr_data.sql` | Truncate all `pr_*` tables (canonical list in `Install::get_pr_table_names()`) |
| `tests/sql/01_seed_demo_session.sql` | Demo project with marks (edit `@reviewer_1` / `@reviewer_2` to real `wp_users.ID`) |
| `tests/sql/02_teardown_test_users.sql` | How to find and delete `pr_test_fixture` users |

Typical manual flow: run `00_reset` → run `01_seed` → smoke-test UI → run `02_teardown` or `composer test:teardown -- --confirm`.

---

## SOP documentation screenshots

Captures PNGs for `docs/sop/project-reviews-sop.typ` into `docs/sop/screenshots/YYYY-MM-DD_HHmmss/`.

```bash
# Reads tests/e2e/.env.local automatically (see playwright.config.ts)
npm run build
npm run sop:screenshots
npm run sop:screenshots:headed
```

Success: new folder under `docs/sop/screenshots/YYYY-MM-DD_HHmmss/` with **19** PNGs and `manifest.json` → `"captured"` lists their IDs. Remaining SOP figures are **manual** (`SOP_SCREENSHOT_IDS_MANUAL`).

After the run:

1. Copy the printed `#let sop-screenshots-dir = "…"` into `docs/sop/lib/theme.typ`
2. Set `use-live-screenshots = true`
3. Compile: `typst compile docs/sop/project-reviews-sop.typ`

Optional: `PR_SOP_SCREENSHOTS_DIR=screenshots/2026-05-21_143022/` (trailing slash) reuses a folder.

Manifest: `tests/e2e/sop-screenshot-manifest.ts` (`SOP_SCREENSHOT_IDS_AUTOMATED` vs `SOP_SCREENSHOT_IDS_MANUAL`).  
Details: `docs/sop/screenshots/README.md`.

---

## CI

| Job | When | Command |
|-----|------|---------|
| PHPUnit | Every PR (required) | `composer test` |
| Playwright `e2e-ui` | Optional / nightly | `npm run test:e2e` when WP + secrets available |
| Teardown | **Never** in CI | — |

---

## Command cheat sheet

```bash
# No WordPress
composer test
composer test:journey

# Assets
npm run build

# Manual: load env then open browser (see "Manual check" section)
source tests/e2e/load-env.sh
# http://sastt.local/reviews/  →  coordinator login  →  #/registry

# Browser (WordPress + env)
source tests/e2e/load-env.sh
npm run test:e2e
npm run test:e2e:headed

# Reset staging (WordPress + confirm)
composer test:teardown -- --dry-run
composer test:teardown -- --confirm

# SOP PNGs
npm run sop:screenshots
```

---

## Related files

| Path | Role |
|------|------|
| `tests/FullPluginJourneyTest.php` | PHPUnit journey suite |
| `tests/support/ScenarioBuilder.php` | Shared journey fixture builder |
| `tests/e2e/specs/full-plugin-ui-journey.spec.ts` | Playwright UI journey (fast) |
| `tests/e2e/specs/coordinator-walkthrough.spec.ts` | Slow coordinator setup demo |
| `tests/e2e/specs/coordinator-walkthrough-finish.spec.ts` | Slow coordinator progress/reports |
| `tests/e2e/specs/reviewer-walkthrough.spec.ts` | Slow reviewer demo |
| `tests/e2e/helpers/walkthrough.ts` | Step banner + pause helper |
| `tests/e2e/MVP_CHECKLIST.md` | Manual QA checklist |
| `tests/e2e/env.example` | Copy-paste env template |
| `tests/e2e/bin/seed-e2e-users.php` | Create fixture WP users |
| `playwright.config.ts` | Playwright base URL, timeouts |
| `composer.json` scripts | `test`, `test:journey`, `test:e2e`, `test:teardown` |
| `_bmad-output/implementation/15-1-plugin-e2e-automation-and-teardown.md` | Story spec |
