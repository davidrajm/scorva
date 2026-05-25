# Story 15.1: Plugin E2E automation and on-demand teardown

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **developer or QA engineer**,
I want an automated test harness that exercises Project Reviews end-to-end across all major domains,
and an explicit, opt-in teardown that removes all plugin data and test-created WordPress instances when I request it,
So that regressions are caught before release and test/staging environments can be reset safely without accidental production data loss.

## Acceptance Criteria

### 1. Test strategy documented and wired into Composer

- **Given** the plugin repo
- **When** a developer runs documented test commands
- **Then** `composer test` runs the full PHPUnit suite (existing ~327 tests + new journey tests)
- **And** `composer test:journey` runs only epic-scenario / full-journey tests (fast feedback loop during harness work)
- **And** `composer test:e2e` runs WordPress-integrated UI tests when `wp-env` or equivalent local WP is available (document skip behaviour when not configured)
- **And** `composer test:teardown` runs the on-demand teardown entry point (see AC 6) and **never** runs automatically as part of `composer test`
- **And** `README` or `tests/README.md` documents prerequisites, env vars, and the difference between **truncate reset** (default teardown) vs **full drop** (destructive)

### 2. Functional coverage matrix (all plugin domains)

- **Given** the coverage matrix in Dev Notes (FR → scenario)
- **When** `composer test` completes
- **Then** every row marked **PHPUnit journey** has at least one automated test that asserts HTTP-equivalent REST outcomes via existing stub stack (`FakeWpdb`, `RestTestFixtures`, route callbacks)
- **And** every row marked **Playwright** has a spec file under `tests/e2e/specs/` (may be `@group e2e` skipped in CI without WP)
- **And** no duplicate reinvented fixtures — journey tests use a shared `ScenarioBuilder` (see Dev Notes) that composes repositories/REST like production order: registry → project → panels → reviewers → rubrics → marks → scores → progress → reports → close → audit

### 3. PHPUnit full-journey suite

- **Given** `tests/FullPluginJourneyTest.php` (or `tests/Journey/`)
- **When** journey tests run
- **Then** `test_happy_path_coordinator_setup_through_reports_export` covers: create students, create active project with enrolment, panels, provision reviewers (stub users), confirm rubric, submit marks, read scores/progress, download at least one xlsx and one csv export with non-empty binary bodies
- **And** `test_reviewer_assignment_scoping_and_blocked_states` covers: unconfirmed rubric, closed project, not-assigned reviewer — same error codes as `RestMarksTest` / UX-DR20
- **And** `test_session_close_and_reopen_lifecycle` covers close (policy B stub), reopen, marking re-enabled
- **And** `test_rubric_reconfirm_keep_flag_and_clear` covers flagged marks visibility in export payload
- **And** `test_unfreeze_request_flows` covers reviewer → panel head → coordinator paths (reuse patterns from `RestUnfreezeRequestsTest`, `RestPanelUnfreezeRequestsTest`)
- **And** `test_faculty_pool_bulk_invite` covers global faculty CSV + bulk invite on open review (Epic 14 parity)
- **And** `test_panel_head_pdf_and_freeze` covers panel report PDF binary response and freeze guard (extend `PanelReportPdfTemplateTest` / `PanelHeadTest` patterns)
- **And** `test_audit_override_requires_reason` covers `AuditService` minimum reason length and audit log row
- **And** `test_plugin_lifecycle_deactivate_preserves_data` and `test_teardown_truncate_leaves_schema` — deactivation non-destructive; teardown truncate does not drop tables
- **And** all new tests pass with `./vendor/bin/phpunit` in &lt; 60s on developer hardware (no real WP DB)

### 4. WordPress UI E2E (Playwright) — real browser, full journey from “Add project”

- **Given** `@playwright/test` in `package.json` devDependencies and `playwright.config.ts` with `testIdAttribute: 'data-testid'`
- **When** `npm run test:e2e` runs against a live WordPress site (`PR_E2E_BASE_URL`, default `http://localhost:10008`)
- **Then** `tests/e2e/specs/full-plugin-ui-journey.spec.ts` runs **serially in one browser session** and covers:
  1. **Landing** — guest at `/reviews/` sees **Log in**
  2. **Registry** — coordinator adds two students (unique `reg_no` per run)
  3. **Dashboard** — **Create project** with title + both students from registry search
  4. **Wizard — Students** — roster shows enrolled students → **Continue to Panels**
  5. **Wizard — Panels** — add panel, assign every student → **Continue to Reviewers**
  6. **Wizard — Reviewers** — add reviewer using `PR_E2E_REVIEWER_EMAIL` (links existing WP user)
  7. **Wizard — Reviews & rubrics** — create/confirm Review 1 rubric (Save + Confirm) → **Continue to Panel assignments**
  8. **Wizard — Panel assignments** — assignments complete (inherit panel defaults or save) → **Continue to Open reviews**
  9. **Wizard — Open reviews** — **Open for marking** + **Start marking** on Review 1
  10. **Reviewer** — second login context: assignments → marking grid → open first student → **Save** a criterion score
  11. **Coordinator** — **Progress** shows data; **Reports** downloads tab triggers at least one export download
- **And** `npm run test:e2e:headed` opens a visible browser (same spec) for local debugging
- **And** `tests/e2e/helpers/auth.ts` centralises WP login + navigation to `#/…` hash routes
- **And** tests skip with clear message when `PR_E2E_COORD_USER` / `PR_E2E_COORD_PASS` / `PR_E2E_REVIEWER_USER` / `PR_E2E_REVIEWER_PASS` are unset
- **And** fixture users are tagged `pr_test_fixture = 1` via `tests/e2e/bin/seed-e2e-users.php` (documented in `tests/README.md`)
- **And** `tests/e2e/MVP_CHECKLIST.md` mirrors the spec steps for manual QA
- **And** minimal `data-testid` hooks exist on dashboard create-project form (`pr-create-project`, `pr-project-title`, `pr-registry-search`) for stable selectors

### 5. Shared scenario builder (reuse, do not duplicate)

- **Given** `tests/support/ScenarioBuilder.php`
- **When** journey or REST tests need a “fully configured project”
- **Then** one call chain seeds: 2 students, 1 project, 1 panel, 2 reviewers, 2 review rounds, criteria, weights, assignments, confirmed rubrics — mirroring `tests/sql/01_seed_demo_session.sql` logic in PHP for `FakeWpdb`
- **And** builder exposes `with_marks_submitted()`, `with_session_closed()`, `with_flagged_marks()` variants
- **And** builder registers created stub user IDs in `$GLOBALS['pr_test_created_user_ids']` for teardown

### 6. On-demand teardown (“when said so”)

- **Given** an operator explicitly requests teardown (CLI flag or env)
- **When** `composer test:teardown` runs **without** `--confirm`
- **Then** the command prints what will be removed and exits non-zero (dry-run)
- **When** `composer test:teardown -- --confirm` runs (or `PR_TEST_TEARDOWN_CONFIRM=1`)
- **Then** **truncate path** (default): all rows in every `pr_*` table listed in `Install::get_pr_table_names()` are removed; `pr_rubric_scores` view remains; plugin options **retained** unless `--purge-options` passed
- **And** all WordPress users with meta `pr_test_fixture = 1` are deleted (including provisioned reviewers/coordinators created by E2E setup only)
- **And** custom roles are not removed unless empty (same rule as `Capabilities::remove_custom_roles_if_empty()`)
- **When** `--full-drop` is passed with `--confirm`
- **Then** `Install::drop_all()` runs, then `Install::maybe_upgrade()` (or activation path) recreates empty schema — for disposable local DBs only; command prints loud warning
- **And** teardown **never** runs during `composer test`, `npm run build`, plugin deactivation, or WordPress uninstall unless the dedicated teardown command is invoked with confirm
- **And** production guard: if `wp-config.php` defines `PR_TEST_TEARDOWN_DISABLED` or environment is not `local`/`development` (detect via `wp_get_environment_type()` when WP loaded), teardown aborts unless `--force-local`

### 7. SQL scripts aligned with current schema

- **Given** `tests/sql/00_reset_pr_data.sql`
- **When** a DBA runs manual reset
- **Then** script truncates **all** tables in `Install::get_pr_table_names()` (including `pr_review_panel_freezes`, `pr_panel_unfreeze_requests`, `pr_review_student_attendance_by_reviewer`, `pr_review_student_panels`, `pr_review_panel_reviewers`, etc. — current script is incomplete)
- **And** `01_seed_demo_session.sql` updated to seed assignments/attendance if journey tests require them
- **And** new `tests/sql/02_teardown_test_users.sql` documents deleting `pr_test_fixture` users

### 8. CI and regression gates

- **Given** CI configuration (document in `tests/README.md` even if CI file lives outside plugin)
- **When** PR validation runs
- **Then** PHPUnit (including journeys) is required green
- **And** Playwright is optional job `e2e-ui` that may be manual/nightly
- **And** teardown is never invoked in CI (tests use `FakeWpdb` isolation per test class `setUp` / `RestTestFixtures::reset()`)

### 9. SOP documentation screenshots (dated folders + Typst global path)

- **Given** the Typst SOP at `docs/sop/project-reviews-sop.typ`
- **When** a developer or QA captures UI screenshots for the SOP PDF
- **Then** PNGs are stored under `docs/sop/screenshots/YYYY-MM-DD_HHmmss/` (local date + time, 24h clock) as `{id}.png` where `{id}` matches placeholders in the SOP (e.g. `04-dashboard.png`)
- **And** `docs/sop/lib/theme.typ` defines a **single global** `#let sop-screenshots-dir = "screenshots/…/"` (trailing slash) used by the `screenshot()` helper — changing only this variable switches the PDF to an older capture run without editing `project-reviews-sop.typ`
- **And** `#let use-live-screenshots = true` embeds images from that folder; `false` shows placeholders

- **Given** Playwright E2E env is configured (`PR_E2E_*`, seeded users)
- **When** `npm run sop:screenshots` runs
- **Then** `tests/e2e/specs/capture-sop-screenshots.spec.ts` writes a new dated folder (unless `PR_SOP_SCREENSHOTS_DIR` points at an existing folder)
- **And** each automated step saves `fullPage` PNGs for IDs listed in `tests/e2e/sop-screenshot-manifest.ts` (`SOP_SCREENSHOT_IDS_AUTOMATED` subset; extend over time)
- **And** `manifest.json` in the run folder lists `captured` vs `pending` IDs
- **And** stdout prints the exact `sop-screenshots-dir` line to paste into `lib/theme.typ`

- **Given** `tests/e2e/sop-screenshot-manifest.ts`
- **When** compared to `project-reviews-sop.typ`
- **Then** every `#screenshot("…")` ID is listed in `SOP_SCREENSHOT_IDS` (35 IDs as of story creation)

- **And** `docs/sop/screenshots/README.md` and `docs/sop/README.md` document dated folders, Typst wiring, and manual capture for IDs not yet automated
- **And** `npm run sop:screenshots:headed` runs the same spec with a visible browser for debugging

## Tasks / Subtasks

- [x] **Docs:** Create `tests/README.md` (commands, env vars, coverage matrix, teardown safety) (AC: 1, 8)
- [x] **Docs:** Create `tests/e2e/MVP_CHECKLIST.md` human supplement (AC: 4)
- [x] **Composer:** Add scripts `test`, `test:journey`, `test:teardown`; document `composer test:e2e` delegation to npm (AC: 1)
- [x] **Support:** `tests/support/ScenarioBuilder.php` + extend `RestTestFixtures` for user ID tracking (AC: 5)
- [x] **PHPUnit:** `tests/FullPluginJourneyTest.php` (or `tests/Journey/*`) implementing AC 3 scenarios (AC: 3)
- [x] **PHPUnit:** Extend `UninstallTest` / lifecycle tests for teardown truncate vs full-drop guards (AC: 3, 6)
- [x] **Teardown CLI:** `bin/pr-test-teardown.php` (load WP if available, else stub mode for PHPUnit-only doc) calling `ProjectReviews\Testing\TestTeardown::run()` (AC: 6)
- [x] **Service:** `includes/testing/TestTeardown.php` — truncate, delete fixture users, optional drop+recreate (AC: 6)
- [x] **SQL:** Update `00_reset_pr_data.sql`, add `02_teardown_test_users.sql` (AC: 7)
- [x] **Playwright:** `playwright.config.ts`, `tests/e2e/specs/full-plugin-ui-journey.spec.ts` (browser journey from create project), `test:e2e` + `test:e2e:headed`, seed script (AC: 4)
- [x] **SOP screenshots:** `sop-screenshots-dir` in `docs/sop/lib/theme.typ`; dated folders; `capture-sop-screenshots.spec.ts`, helpers, manifest; `npm run sop:screenshots` (AC: 9)
- [x] **SOP screenshots:** Extend `capture-sop-screenshots.spec.ts` until all `SOP_SCREENSHOT_IDS` are in `SOP_SCREENSHOT_IDS_AUTOMATED` (or document manual-only IDs) (AC: 9)
- [x] **WP setup script:** `tests/e2e/bin/seed-e2e-users.php` or wp-cli eval-file (AC: 4, 6)
- [x] Run `./vendor/bin/phpunit` and `npm run build`; run `npm run test:e2e` locally once documented

## Dev Notes

### User request (source)

Create **automation testing for the plugin to test all its functionalities**, and finally **remove all the data and instances that it has created, when said so** (explicit opt-in teardown — not automatic on test completion).

### Current testing baseline (do not discard)

| Asset | Role |
|-------|------|
| `tests/bootstrap.php` | WordPress function stubs; `PR_UNIT_TEST` |
| `tests/FakeWpdb.php` | In-memory SQL tables for repositories |
| `RestTestFixtures` in `tests/RestAuthTest.php` | Login, caps, REST nonce |
| ~48 PHPUnit files, **327 tests**, **1471 assertions** (2026-05-21) | Domain + REST coverage |
| `tests/sql/00_reset_pr_data.sql` | Manual truncate — **out of date** vs schema |
| Story **1-8** | Production uninstall opt-in via `pr_delete_data_on_uninstall`; deactivation preserves data |

**NFR16** requires PHPUnit for scoring, exports, critical services — already met. This story adds **cross-cutting journey** coverage and **environment reset**, not replacement of unit tests.

### FR → scenario coverage matrix

| Area | FRs | PHPUnit journey | Playwright UI | Existing tests to extend |
|------|-----|-----------------|---------------|---------------------------|
| Workspace / auth | 26–28 | Login caps, route guard | Landing + login | `RestAuthTest`, `RoutesTest`, `WorkspaceAccessTest` |
| Registry | 1–2 | CRUD + CSV import REST | Registry smoke | `RestStudentsTest`, `StudentRepositoryTest` |
| Projects / wizard | 3–9, 29 | Full setup chain | Wizard students step | `RestSessionsTest`, `RestReviewersTest` |
| Rubrics | 10–13 | Confirm/unlock/reconfirm | Rubric confirm dialog | `RubricLifecycleServiceTest`, `RestReviewsTest` |
| Marking | 14–16 | Draft/submit, blocked codes | Reviewer rubric save | `RestMarksTest`, `MarkServiceTest` |
| Scores | 17–19 | Progress + breakdown REST | Progress table visible | `ScoreServiceTest`, `RestReportsTest` (progress) |
| Exports | 20–21, 20a | xlsx/csv binary + merges | Download button (one report) | `ExportServiceTest`, `RestReportsTest` |
| Close / reopen | 22 | Close + reopen | Close dialog (smoke) | `SessionCloseServiceTest`, `RestSessionCloseTest` |
| Audit / override | 23–24 | Override + audit log | — | `AuditServiceTest`, `Rest` audit routes |
| Settings | 25 | Options read/write stub | — | `PluginSettingsTest` |
| Emails | 30 | Trigger stub `wp_mail` | — | Notification tests if present |
| Faculty pool | (14-1) | Bulk invite REST | — | `RestFacultyAccountsTest` |
| Panel PDF / freeze | (11-1, 5-15+) | PDF bytes + freeze | — | `PanelReportPdfTemplateTest`, `PanelHeadTest` |
| Unfreeze | (5-8, 5-15) | Request/grant chain | — | `RestUnfreezeRequestsTest`, `RestPanelUnfreezeRequestsTest` |
| Lifecycle | 1-8 | Deactivate + teardown modes | — | `UninstallTest` |

Rows marked **PHPUnit journey** must be implemented in Story 15.1. Playwright rows are required specs but may skip in CI without WP.

### Teardown design (three levels)

| Level | Trigger | Tables | Options | WP users | Use case |
|-------|---------|--------|---------|----------|----------|
| **Per-test** | PHPUnit `setUp` | FakeWpdb reset | N/A | Stub IDs discarded | Default unit/journey |
| **Truncate** | `composer test:teardown --confirm` | TRUNCATE all `pr_*` | Keep unless `--purge-options` | Delete `pr_test_fixture` users | Reset staging data between manual QA |
| **Full drop** | `--full-drop --confirm` | `Install::drop_all()` + recreate | Recreated on activate | Delete fixture users | Disposable local DB |

**Never** call truncate/full-drop from PHPUnit `tearDown` globally — only explicit CLI.

Reuse table list from `Install::get_pr_table_names()` — single source of truth (Story 1-8).

```960:989:includes/Install.php
    public static function get_pr_table_names(string $prefix): array
    {
        $suffixes = [
            'pr_mark_audit',
            'pr_marks',
            // ... full list in source
        ];
```

Production **uninstall** remains `Uninstall::run()` + `pr_delete_data_on_uninstall` only — do not merge test teardown into `uninstall.php`.

### REST surface for journey tests

Register all routes once per test class (pattern from `RestSessionsTest`):

```11:61:includes/rest/class-rest-bootstrap.php
    public static function register_routes(): void
    {
        // students, sessions, reviewers, reviews, marks, scores, progress,
        // reports, audit, session-close, unfreeze, panel-reports, ...
    }
```

Journey tests call static route handlers with `WP_REST_Request` — no HTTP server required.

### Playwright conventions

- Base URL: `PR_E2E_BASE_URL` env (default `http://localhost:10008` or project Local/WP env).
- Auth: store cookies after `wp-login.php` or use application password REST for setup only.
- Coordinator app: HashRouter — URLs like `/reviews/#/session/{id}/wizard`.
- Reviewer app: `/reviews/mark/#/...`.
- Prefer `getByRole`, `getByLabel`, stable `data-testid` — add minimal testids only where selectors are otherwise ambiguous (coordinate with UX-DR27).

### Files to create / modify

| File | Action |
|------|--------|
| `composer.json` | Add `scripts.test`, `test:journey`, `test:teardown` |
| `package.json` | Add `test:e2e`, devDependency `@playwright/test` |
| `playwright.config.ts` | **Create** |
| `tests/README.md` | **Create** |
| `tests/e2e/MVP_CHECKLIST.md` | **Create** |
| `tests/e2e/specs/*.spec.ts` | **Create** |
| `tests/e2e/specs/capture-sop-screenshots.spec.ts` | **Create** — SOP PNG capture (AC 9) |
| `tests/e2e/helpers/sop-screenshots.ts` | **Create** |
| `tests/e2e/sop-screenshot-manifest.ts` | **Create** |
| `docs/sop/lib/theme.typ` | `sop-screenshots-dir` global |
| `docs/sop/screenshots/README.md` | Dated folder docs |
| `tests/support/ScenarioBuilder.php` | **Create** |
| `tests/FullPluginJourneyTest.php` | **Create** |
| `includes/testing/TestTeardown.php` | **Create** |
| `bin/pr-test-teardown.php` | **Create** |
| `tests/sql/00_reset_pr_data.sql` | **Update** all tables |
| `tests/sql/02_teardown_test_users.sql` | **Create** |
| `tests/bootstrap.php` | Extend stubs: `wp_get_environment_type`, user meta, delete user |
| `tests/UninstallTest.php` | Add teardown guard tests |

### Architecture compliance

- PSR-4 `ProjectReviews\` under `includes/`; test support under `tests/support/`.
- No theme imports (NFR3).
- PHPUnit without full WP install for journeys (existing pattern).
- E2E UI tests may require real WP — keep optional.
- Binary exports: assert magic bytes / content-length via `Rest_Binary_Response` patterns from story 7-7.

### Previous story intelligence

- **1-1:** PHPUnit bootstrap — extend, do not replace.
- **1-8:** `Install::drop_all()`, `Uninstall::run()`, opt-in flags — test teardown must mirror table list but use separate confirm flag (`PR_TEST_TEARDOWN_CONFIRM`), not `pr_delete_data_on_uninstall`.
- **7-3 / 7-7:** Export REST tests — reuse binary assertion helpers in journey test.
- **3-11 / 5-6:** `marking_active`, freeze — include in journey variants.
- **14-1:** Faculty pool — include bulk invite scenario.

### Testing standards

- Run `./vendor/bin/phpunit` before marking done; target all green including new journeys.
- Run `npm run build` if any `data-testid` added to React.
- Document local E2E: `npm run test:e2e` after seeding users.
- Do not claim “all functionalities” complete without coverage matrix rows checked off in Dev Agent Record.

### SOP screenshot capture (AC 9)

| Asset | Role |
|-------|------|
| `docs/sop/lib/theme.typ` | `#let sop-screenshots-dir` — **only** place to switch screenshot run |
| `docs/sop/screenshots/YYYY-MM-DD_HHmmss/` | One capture run; keep multiple runs for comparison |
| `tests/e2e/sop-screenshot-manifest.ts` | Canonical 35 IDs ↔ `project-reviews-sop.typ` |
| `tests/e2e/helpers/sop-screenshots.ts` | Dated dir creation, `captureSopScreenshot`, manifest + Typst instructions |
| `tests/e2e/specs/capture-sop-screenshots.spec.ts` | Automated subset (extend to cover remaining IDs) |

Env: `PR_SOP_SCREENSHOTS_DIR=screenshots/2026-05-21_143022/` reuses a folder (trailing slash). Default: new timestamp folder per run.

After capture: set `sop-screenshots-dir` + `use-live-screenshots = true` → `typst compile docs/sop/project-reviews-sop.typ`.

### Out of scope

- Replacing PHPUnit with Behat/Codeception.
- Running teardown on production/staging without `--force-local` guard.
- Multisite network teardown policies.
- Visual regression / Percy.
- Performance/load testing.
- Auto-teardown after each Playwright test (use per-spec isolation or truncate only via explicit command).
- Committing large PNG trees to git (optional per team; folders are local artifacts by default).

### References

- [Source: _bmad-output/planning/epics.md — NFR16, E2E validation note, FR inventory]
- [Source: _bmad-output/implementation/1-8-plugin-deactivation-uninstall.md]
- [Source: includes/Install.php — `get_pr_table_names`, `drop_all`]
- [Source: includes/Uninstall.php]
- [Source: includes/rest/class-rest-bootstrap.php]
- [Source: tests/RestAuthTest.php — `RestTestFixtures`]
- [Source: tests/sql/00_reset_pr_data.sql, 01_seed_demo_session.sql]
- [Playwright docs](https://playwright.dev/docs/intro)

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

- PHPUnit 337 tests green after `FakeWpdb` TRUNCATE support and journey test fixes.
- `npm run build` succeeded (bundle size warnings only).

### Completion Notes List

- Added `composer test:journey`, `TestTeardown` service, refactored `bin/pr-test-teardown.php`, `ScenarioBuilder`, and `FullPluginJourneyTest` (10 `@group journey` scenarios).
- Extended `tests/README.md` with coverage matrix, teardown modes, CI notes; SQL reset scripts aligned with `Install::get_pr_table_names()`.
- SOP: documented manual-only screenshot IDs via `SOP_SCREENSHOT_IDS_MANUAL` in manifest (13 automated / 22 manual).
- Playwright UI journey, seed script, and MVP checklist were already present; verified wiring in `package.json` / `playwright.config.ts`.
- `npm run test:e2e` requires live WP + `PR_E2E_*` env (documented; not run in this session).

### File List

- `composer.json`
- `bin/pr-test-teardown.php`
- `includes/testing/TestTeardown.php`
- `tests/support/ScenarioBuilder.php`
- `tests/FullPluginJourneyTest.php`
- `tests/RestAuthTest.php`
- `tests/bootstrap.php`
- `tests/FakeWpdb.php`
- `tests/README.md`
- `tests/sql/00_reset_pr_data.sql`
- `tests/sql/02_teardown_test_users.sql`
- `tests/e2e/sop-screenshot-manifest.ts`
- `_bmad-output/implementation/sprint-status.yaml`

### Change Log

- 2026-05-21: Story 15.1 created — E2E automation harness and opt-in teardown (create-story).
- 2026-05-21: Added Playwright `full-plugin-ui-journey.spec.ts` (browser test from create project), helpers, README, MVP checklist, `data-testid` on dashboard create form.
- 2026-05-21: SOP screenshots — `sop-screenshots-dir` in Typst theme, dated `screenshots/YYYY-MM-DD_HHmmss/` folders, `capture-sop-screenshots.spec.ts`, `npm run sop:screenshots` (AC 9).
- 2026-05-21: Dev-story complete — journey PHPUnit suite, TestTeardown, ScenarioBuilder, SQL/docs/composer scripts (status → review).
