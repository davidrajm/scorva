# Story 14.1: Faculty reviewer pool, directory sync, and bulk invite on open review

Status: review

<!-- Ultimate context engine analysis completed — global faculty account pool, david-sas wp_faculty bridge, session-scoped bulk invite reusing ReviewerProvisionService + SessionCloseService policy B -->

## Story

As a **review coordinator**,
I want a **global Faculty accounts** workspace to provision reviewer WordPress accounts from CSV or the institutional faculty directory, and a **single action on the Open reviews step** to email all assigned reviewers their login details when marking starts,
so that **faculty can access the reviewer workspace with minimal setup**, temporary credentials remain valid for the life of the project, and **users who already have their own passwords are not disrupted**.

## Background — current behaviour (do not guess)

### What exists today

| Area | Current behaviour | Gap |
|------|-------------------|-----|
| **Reviewer provisioning** | Per-panel roster in wizard; `ReviewerProvisionService::provision_reviewer()` creates/matches WP user, sets `pr_session_reviewers.provisioned_for_session = 1`, sends `ReviewerInviteEmail` | No **global** faculty/reviewer account pool; no bulk directory import |
| **CSV import** | Panel reviewer CSV in wizard (`panel`, `reviewer_name`, `email`) via `CsvImportMapper` | No faculty-shaped CSV (`empId`, `name`, `email`, …) at global scope |
| **Faculty bridge** | Design spec §8.2 defines `pr_faculty_search` / `pr_faculty_resolve` filters; Story 3.6 **deferred** bridge — WP user search only | No runtime bridge; `wp_faculty` unread by plugin |
| **Resend credentials** | Per-row `POST .../resend-credentials` resets password for **provisioned** users only | No **session-wide** invite when opening reviews |
| **Open reviews UI** | [`ReviewMarkingStep.jsx`](../../src/coordinator/components/ReviewMarkingStep.jsx) — per-round Start/Pause marking; no invite action | Missing bulk “email all reviewers” control |
| **Global nav** | [`CoordinatorNav.jsx`](../../src/coordinator/CoordinatorNav.jsx) — Dashboard, All Students only | No Faculty accounts entry |
| **Session close** | [`SessionCloseService`](../../includes/services/SessionCloseService.php) policy B disables `provisioned_for_session` accounts | Already satisfies “credentials work until project closed” — **reuse**, do not reinvent expiry |
| **Own passwords** | `resolve_or_create_user()` matches existing WP user by email **without** resetting password; `resend_credentials()` only resets when `provisioned_for_session = 1` | Bulk invite must follow same rules |

### david-sas faculty directory (reference schema — read-only)

**Table:** `{$wpdb->prefix}faculty` (typically `wp_faculty`)

| Column | Use in plugin |
|--------|---------------|
| `faculty_id` | Primary key |
| `empId` | Employee ID; dedupe key for import |
| `emp_name` | Display name → WP `display_name` |
| `official_email` | Login email (CSV column `email` maps here) |
| `designation`, `gender`, `prefix` | Optional metadata (`pr_faculty_designation` user meta) |
| `status` | Import only rows where `status` is `Active` (case-insensitive) |

**Theme reference files (do not depend on theme UI):**

- `themes/david-sas/inc/faculty/class-faculty-manager.php` — CRUD
- `themes/david-sas/inc/course/utils.php` — `get_list_faculty()`
- `themes/david-sas/inc/faculty/bulk-update.php` — CSV headers: `empId,name,designation,gender,email`

**Design spec bridge (extend):**

```php
apply_filters( 'pr_faculty_search', $results, $query );
apply_filters( 'pr_faculty_resolve', $row, $email_or_emp_id );
apply_filters( 'pr_faculty_list_active', $rows ); // NEW — list all active faculty for bulk sync
```

Plugin reads via filters first; when bridge setting is on and table exists, fall back to direct `wp_faculty` SELECT (standalone — no theme code required).

### Product intent

1. **Global pool** — Coordinators maintain reviewer-capable WP accounts **before** panel assignment (like All Students for faculty).
2. **Two import paths** — CSV upload **or** one-click sync from `wp_faculty`.
3. **Bulk invite at go-live** — On Open reviews, one button provisions + emails **all distinct panel reviewers** for the project.
4. **Password policy** — New/provisioned users get a generated temp password; existing users keep their password; all remain login-capable until **Close project** disables provisioned accounts (policy B). Story 8-3 reopen re-enables them.

## Acceptance Criteria

1. **Global nav & page** — **Given** user with `pr_assign_reviewers` **When** they open the coordinator app **Then** sidebar global nav includes **Faculty accounts** (`#/faculty`) alongside Dashboard and All Students **And** page shows searchable table of reviewer-pool accounts (display name, email, emp ID if known, WP user linked, created date) **And** users without capability do not see the nav item or route.

2. **CSV import (faculty pool)** — **Given** CSV with required columns `empId`, `name`, `email` and optional `designation`, `gender` (headers case-insensitive; align with david-sas bulk template) **When** coordinator maps columns via `CsvImportMapper` import type `faculty-accounts`, previews rows, and submits **Then** each valid row creates or matches WP user by email, assigns `project_reviews_reviewer` role + `pr_enter_marks`, stores `pr_faculty_emp_id` user meta **And** does **not** send email during import (accounts only) **And** row-level errors returned with downloadable error CSV (same pattern as Story 2.4) **And** duplicate `empId` or email within file handled with update-or-skip choice before commit **And** template downloadable at `assets/csv/faculty-accounts-import-template.csv`.

3. **Import from faculty directory** — **Given** plugin setting **Faculty directory bridge** enabled (extend [`PluginSettings`](../../includes/services/PluginSettings.php)) and `wp_faculty` table exists **When** coordinator clicks **Import from faculty directory** **Then** all **Active** faculty rows with non-empty `official_email` are processed through the same provision logic as CSV (create/match user, reviewer role, `pr_faculty_emp_id` = `empId`) **And** response summarizes created, updated, skipped (inactive / no email), failed **And** when bridge off or table missing, action returns clear error (`faculty_bridge_unavailable`) without fatal **And** `apply_filters('pr_faculty_list_active', …)` allows theme override before direct table read.

4. **Account rules (shared provision helper)** — **Given** import or bulk invite **When** email matches existing WP user **Then** user is linked, reviewer role ensured, password **not** changed **When** email is new **Then** user created with `wp_generate_password()`, `pr_force_password_change = 1` meta **And** users with coordinator caps (`Capabilities::coordinator_caps()`) are never stripped of coordinator role (existing `ensure_reviewer_role` guard) **And** coordinator-capable users are skipped for password reset on bulk invite.

5. **Bulk invite on Open reviews** — **Given** active project with panel reviewers assigned **When** coordinator clicks **Email all reviewers** on the Open reviews step ([`ReviewMarkingStep.jsx`](../../src/coordinator/components/ReviewMarkingStep.jsx)) **Then** for each **distinct** panel reviewer email on the project: roster `user_id` is linked if missing (match global pool user by email); `pr_session_reviewers` row created/updated with `provisioned_for_session = 1` when password is set; provisioned users receive new generated password + `ReviewerInviteEmail`; linked-only users (`provisioned_for_session = 0`) receive a **login reminder** email (login URL, project title, **no password**) **And** `ReviewAssignmentRepository::sync_panel_reviewers_to_all_reviews()` runs **And** audit logs `bulk_invite_reviewers` on session with counts **And** UI shows success Notice with sent / skipped / failed breakdown **And** button disabled when project is `draft` or `closed`, or user lacks `pr_assign_reviewers`.

6. **Invite email copy** — **Given** provisioned bulk invite **When** email sends **Then** body states credentials are for this project and remain valid **until the project is closed** (user-facing “project” terminology) **And** recommends changing password after first login **And** uses [`PluginSettings::login_url_with_redirect()`](../../includes/services/PluginSettings.php) targeting `/reviews/mark/`.

7. **REST** — **Given** authenticated coordinator **Then** routes registered under `project-reviews/v1`:
   - `GET /faculty-accounts` — paginated list (query: search, page)
   - `POST /faculty-accounts/import` — CSV body (reuse students import multipart pattern)
   - `POST /faculty-accounts/sync-directory` — directory sync
   - `POST /sessions/{id}/invite-reviewers` — bulk invite for session  
   **And** capability checks: list/import/sync require `pr_assign_reviewers`; bulk invite requires `pr_assign_reviewers` + session access **And** errors use existing REST error shape.

8. **Regression & tests** — **Given** implementation complete **Then** `FacultyAccountServiceTest` (or extended `ReviewerProvisionServiceTest`) covers: CSV create, email match without password change, directory skip inactive, bulk invite distinct emails, linked user no password reset **And** `RestFacultyAccountsTest` covers auth + import **And** `composer test` + `npm run build` pass **And** wizard per-row provision/resend still works unchanged.

## Tasks / Subtasks

- [x] **Service — `FacultyAccountService`** (AC: 2, 3, 4)
  - [x] Extract shared `provision_reviewer_account( email, name, emp_id?, send_email=false )` from [`ReviewerProvisionService`](../../includes/services/ReviewerProvisionService.php) or delegate to it.
  - [x] `import_csv( rows )`, `sync_from_directory()`, `list_accounts( search, page )`.
  - [x] `FacultyBridgeService` — `list_active()` via filter + optional direct `wp_faculty` read.

- [x] **Bulk invite — extend `ReviewerProvisionService`** (AC: 5, 6)
  - [x] `invite_all_session_reviewers( int $session_id )` — distinct panel reviewers by email; apply provision/link/password rules; return `{ sent, skipped, failed, details[] }`.
  - [x] Add `ReviewerInviteEmail::send_login_reminder()` for linked users (no password).
  - [x] Extend provisioned invite copy with “valid until project closed”.

- [x] **Settings** (AC: 3)
  - [x] Add `faculty_bridge_enabled` boolean to `PluginSettings` + WP Admin checkbox (Story 9-3 pattern).

- [x] **REST** (AC: 7)
  - [x] New `includes/rest/class-rest-faculty-accounts.php`; register in [`class-rest-bootstrap.php`](../../includes/rest/class-rest-bootstrap.php).
  - [x] `POST /sessions/{id}/invite-reviewers` in [`class-rest-sessions.php`](../../includes/rest/class-rest-sessions.php) or session-scoped controller.

- [x] **UI — Faculty accounts page** (AC: 1, 2, 3)
  - [x] `src/coordinator/pages/FacultyAccounts.jsx` — table, `CsvImportMapper` type `faculty-accounts`, directory sync button, template download link.
  - [x] Route in [`App.jsx`](../../src/coordinator/App.jsx); nav item in [`CoordinatorNav.jsx`](../../src/coordinator/CoordinatorNav.jsx).
  - [x] Asset: `assets/csv/faculty-accounts-import-template.csv`.

- [x] **UI — Open reviews bulk invite** (AC: 5)
  - [x] Add **Email all reviewers** button + `ConfirmDialog` (consequence list: N reviewers, temp passwords for new provisioned accounts, existing passwords unchanged, valid until close) to [`ReviewMarkingStep.jsx`](../../src/coordinator/components/ReviewMarkingStep.jsx).
  - [x] Wire `POST sessions/{id}/invite-reviewers`.

- [x] **Tests** (AC: 8)
  - [x] PHPUnit service + REST tests; stub `wp_faculty` in bootstrap if needed.
  - [x] Manual: CSV import → assign reviewer in wizard by email → bulk invite → reviewer logs in → close project → login blocked → reopen (8-3) → login restored.

- [x] **Verification**
  - [x] `./vendor/bin/phpunit` + `npm run build`.

## Dev Notes

### Technical requirements

- **Terminology:** User-facing “project”, “Faculty accounts”; code/REST may use `faculty-accounts`, `session_id`.
- **Do not duplicate wizard provisioning logic** — extend [`ReviewerProvisionService`](../../includes/services/ReviewerProvisionService.php); global import creates WP users only; session linkage still happens via panel roster + bulk invite.
- **Email timing:** Global import = **silent** (no email). Bulk invite on Open reviews = **send**. Per-row wizard provision/resend unchanged.
- **Temp password lifetime:** No custom expiry table — **SessionCloseService policy B** disables provisioned accounts on close; email copy documents this. Reopen (Story 8-3) re-enables.
- **Own passwords:** Never call `wp_set_password` when `created === false` on match OR when `provisioned_for_session === 0` for that session. Bulk invite sets `provisioned_for_session = 1` only when generating a new password for newly created users or explicitly reprovisioned wizard rows.
- **Distinct reviewers:** Bulk invite dedupes by normalized email across all panels; one email per person even if listed on multiple panels.
- **Reviewers without email:** Skip with row in `failed`/`skipped` detail; do not block other sends.
- **Coordinator as reviewer:** If coordinator-capable user is on roster, include in count but skip password reset (same as close service skip logic).

### Architecture compliance

- **Filter bridge** per design spec §8.2 — plugin never requires david-sas theme files at runtime; direct `wp_faculty` read is fallback when table exists + setting on.
- **REST** namespace `project-reviews/v1`; cookie auth + nonce via existing [`configureApi`](../../src/shared/api.js).
- **UI:** Reuse `CsvImportMapper`, `ConfirmDialog`, `Notice`, `Button`, `StatusChip`, table wrappers from Stories 1-9 / 1-10.
- **Audit:** `faculty_import_csv`, `faculty_sync_directory`, `bulk_invite_reviewers` via [`AuditService`](../../includes/services/AuditService.php).
- **Capabilities:** `pr_assign_reviewers` for faculty pool + bulk invite; do not add new cap unless necessary.

### File structure (expected touch sets)

| File | Change |
|------|--------|
| `includes/services/FacultyAccountService.php` | **New** — CSV/directory import, list |
| `includes/services/FacultyBridgeService.php` | **New** — filter + table read |
| `includes/services/ReviewerProvisionService.php` | Shared provision helper, `invite_all_session_reviewers()` |
| `includes/services/PluginSettings.php` | `faculty_bridge_enabled` |
| `includes/admin/class-admin-settings.php` | Bridge toggle UI |
| `includes/rest/class-rest-faculty-accounts.php` | **New** |
| `includes/rest/class-rest-sessions.php` or `class-rest-session-close.php` | `invite-reviewers` route |
| `includes/emails/ReviewerInviteEmail.php` | Close-date copy, login reminder variant |
| `src/coordinator/pages/FacultyAccounts.jsx` | **New** |
| `src/coordinator/components/ReviewMarkingStep.jsx` | Bulk invite button |
| `src/coordinator/CoordinatorNav.jsx` | Global nav item |
| `src/coordinator/App.jsx` | Route |
| `src/coordinator/components/CsvImportMapper.jsx` | `faculty-accounts` preset columns |
| `assets/csv/faculty-accounts-import-template.csv` | **New** |
| `tests/FacultyAccountServiceTest.php` | **New** |
| `tests/RestFacultyAccountsTest.php` | **New** |

### Testing requirements

- **PHPUnit (required):**
  - CSV row creates user with reviewer role + `pr_faculty_emp_id`.
  - Existing user by email: no password change.
  - Directory sync skips `Inactive` and empty email.
  - Bulk invite: 3 panel rows, 2 unique emails → 2 emails sent.
  - Linked user bulk invite → login reminder only, no `wp_set_password`.
  - REST 403 without `pr_assign_reviewers`.
- **Manual:** Full path CSV → wizard assign by email → Open reviews → Email all reviewers → login at `/reviews/mark/` → close → login blocked.
- **Build:** `npm run build` after JSX changes.

### Previous story intelligence

- **Story 3.5 / 3.6:** Panel CSV + per-row provision/resend/link — **keep**; global pool is additive. Manual WP user pick in wizard should prefer users already in faculty pool (search finds them by email).
- **Story 2.4:** `CsvImportMapper` + error CSV + duplicate policy — copy pattern exactly for `faculty-accounts` type.
- **Story 8.1 / 8.3:** Close disables provisioned accounts; reopen restores — bulk invite email copy must align with this lifecycle.
- **Story 9.3:** WP Admin settings native UI for bridge toggle.
- **Story 3.11:** Open reviews step is terminal wizard step; bulk invite belongs here, not on Reviews & rubrics.
- **Story 3.6 deferred bridge:** This story **implements** bridge (was Phase 2 in epics).

### Risks / edge cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Same person, two empIds, one email | Email match wins; second empId row updates meta or warns duplicate |
| Faculty row inactive | Skipped on directory sync |
| Reviewer on roster, not in global pool | Bulk invite still provisions + links by roster email |
| User changed password after first invite | Bulk invite does not reset unless reprovisioned (`provisioned_for_session`) |
| Project closed | Bulk invite button hidden/disabled |
| `wp_faculty` missing (no david-sas) | Directory button hidden or error; CSV still works |
| PhpSpreadsheet absent | CSV only (no xlsx for faculty import in v1) |

### Out of scope

- Force password change enforcement on first login (meta exists; hook UI deferred)
- Theme-side `pr_faculty_*` filter registration (optional; plugin fallback sufficient)
- Per-review-round invite (session-wide only in v1)
- Auto-send email on global CSV import
- Creating rows in `wp_faculty` from plugin (read-only bridge)
- Coordinator account provisioning via faculty pool

### References

- [Source: _bmad-output/planning/epics.md — FR7, FR8, FR9, FR25, NFR17, NFR18]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §8.1, §8.2, §8.3]
- [Source: _bmad-output/implementation/3-5-wizard-reviewers-provisioning.md]
- [Source: _bmad-output/implementation/3-6-resend-credentials-linking.md]
- [Source: _bmad-output/implementation/2-4-csv-student-import.md]
- [Source: _bmad-output/implementation/8-1-session-close-service.md]
- [Source: themes/david-sas/inc/faculty/class-faculty-manager.php]
- [Source: themes/david-sas/inc/faculty/bulk-update.php — CSV headers]
- [Source: _bmad-output/planning/ux-design-specification.md — tertiary actions, ConfirmDialog consequence pattern UX-DR33]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

### Completion Notes List

- Added global **Faculty accounts** page (`#/faculty`) with paginated search, CSV import (`faculty-accounts` mapper type), and optional directory sync when `faculty_bridge_enabled` is on.
- `ReviewerProvisionService::provision_reviewer_account()` centralizes silent pool import; `invite_all_session_reviewers()` dedupes by email, links roster rows, sends credential or login-reminder emails, and audits `bulk_invite_reviewers`.
- Open reviews step includes **Email all reviewers** with confirm dialog; disabled for draft/closed projects.
- PHPUnit: 323 tests OK; `npm run build` OK.

### File List

- `includes/services/FacultyAccountService.php` (new)
- `includes/services/FacultyBridgeService.php` (new)
- `includes/services/ReviewerProvisionService.php`
- `includes/services/PluginSettings.php`
- `includes/emails/ReviewerInviteEmail.php`
- `includes/admin/class-admin-settings.php`
- `includes/rest/class-rest-faculty-accounts.php` (new)
- `includes/rest/class-rest-bootstrap.php`
- `includes/rest/class-rest-reviewers.php`
- `includes/routes.php`
- `src/coordinator/pages/FacultyAccounts.jsx` (new)
- `src/coordinator/App.jsx`
- `src/coordinator/CoordinatorNav.jsx`
- `src/coordinator/components/CsvImportMapper.jsx`
- `src/coordinator/components/ReviewMarkingStep.jsx`
- `assets/csv/faculty-accounts-import-template.csv` (new)
- `tests/FacultyAccountServiceTest.php` (new)
- `tests/RestFacultyAccountsTest.php` (new)
- `tests/bootstrap.php`

### Change Log

- 2026-05-20: Story 14.1 — faculty reviewer pool, directory bridge, bulk invite on Open reviews.
