# Story 16.1: Project backup — database SQL + Excel reports (ZIP download)

Status: review

<!-- Ultimate context engine analysis completed — plugin-scoped SQL dump + per-project Excel via ReportsViewService, delivered as ZIP through Rest_Binary_Response -->

## Story

As a **site administrator or review coordinator**,
I want to download a **ZIP backup** that contains the plugin’s database tables and **Excel reports for each project**,
so that I can archive institutional marking data before migration, uninstall, or disaster recovery without manually exporting every report.

## Background — current behaviour (do not guess)

| Area | Current behaviour | Gap |
|------|-------------------|-----|
| **Uninstall policy** | Story 1-8 settings recommend backup before opt-in wipe; no backup tool exists | Coordinators/admins must use phpMyAdmin + click each download |
| **Report exports** | Per-file XLSX/CSV/PDF via `Rest_Reports` + `ReportsViewService` builders (Stories 7.x, 12.x) | No bundled export per project or site |
| **Database** | `Install::get_pr_table_names()` is canonical table list (also uninstall, test teardown) | No SQL dump helper |
| **Binary REST** | `Rest_Binary_Response` serves raw ZIP/XLSX bodies (Story 7-7) | Reuse for `.zip` |
| **Restore** | None | **Out of scope** for this story (export-only) |

### User request (source)

Create functionality to make a **backup of the data, with the database**, and **reports for the projects as Excel files**, **zipped**, and **downloaded**.

## Acceptance Criteria

### 1. Backup modes and permissions

1. **Given** a user with `pr_manage_settings`  
   **When** they trigger **full site backup**  
   **Then** the ZIP includes **all** plugin table data, plugin options, and Excel reports for **every** project (`pr_sessions` row).

2. **Given** a user with `pr_manage_sessions` on a project  
   **When** they trigger **single-project backup** for session `{id}`  
   **Then** the ZIP includes plugin table rows **scoped to that project** where rows are session-bound, global registry tables in full (students, field definitions — needed for restore context), and Excel reports **only** for that project.

3. **Given** a user without the required capability  
   **When** they call either backup endpoint  
   **Then** REST returns **403** (existing `Rest_Auth` pattern).

4. **And** restore/import from ZIP is **not** implemented in this story; `README.txt` inside the ZIP states export-only and points to future restore work.

### 2. ZIP structure (required layout)

5. **Given** any successful backup  
   **When** the ZIP is extracted  
   **Then** it contains at minimum:

```
README.txt
manifest.json
database/pr-plugin-data.sql
options/pr-plugin-options.json
projects/{project-slug}/
  consolidated-student-scores.xlsx
  reviews/{review-slug}/
    panel-roster.xlsx
    rubric-marks-matrix.xlsx
    overall-scores-matrix.xlsx
```

6. **Given** `manifest.json`  
   **Then** it includes: `generated_at` (ISO-8601 UTC), `plugin_version`, `db_version` (`pr_db_version` option), `backup_scope` (`full` | `project`), `project_ids` (array), `project_slugs`, `report_layout` used for marks matrix (string, e.g. `panel_reviewer`), and `php_version`.

7. **Given** `README.txt`  
   **Then** it explains contents, that PDF/CSV are omitted by design (Excel committee deliverables only), export-only (no restore), and recommends storing off-site.

8. **Given** a project with **no confirmed reviews**  
   **When** backup runs  
   **Then** `projects/{slug}/` still exists with consolidated workbook if enrolment exists; `reviews/` may be empty; backup does **not** fail.

9. **Given** a confirmed review with no marks yet  
   **When** Excel files are generated  
   **Then** use the same empty/skeleton workbooks as individual download endpoints (do not skip the file).

### 3. Database SQL (`database/pr-plugin-data.sql`)

10. **Given** full backup  
    **When** SQL is generated  
    **Then** it includes, in order:
    - Header comments (generator, timestamp, table count)
    - `DROP VIEW IF EXISTS` for `{prefix}pr_rubric_scores`
    - `DROP TABLE IF EXISTS` for each table in `Install::get_pr_table_names($prefix)` (child-before-parent order from helper)
    - `CREATE TABLE` statements derived from live schema **or** documented alternative: `SHOW CREATE TABLE` per table via `$wpdb` (preferred for fidelity)
    - `INSERT INTO` rows for **all** data in each plugin table (batched inserts acceptable)
    - `CREATE VIEW` for `pr_rubric_scores` (reuse DDL from `Install` — do not duplicate view SQL in a third place; call existing method if exposed, or extract `get_rubric_scores_view_sql()` once)

11. **Given** single-project backup  
    **When** SQL is generated  
    **Then** **session-scoped** tables only include rows for that `session_id` (e.g. `pr_sessions`, `pr_panels`, `pr_reviews`, `pr_marks`, `pr_session_students`, …)  
    **And** **global** tables (`pr_students`, `pr_field_definitions`, `pr_student_meta`) include **all** rows (registry is shared)  
    **And** manifest notes `sql_scope: project` with `session_id`.

12. **And** SQL file uses UTF-8; string values escaped for MySQL (`$wpdb->_real_escape` / `$wpdb->prepare` patterns); NULL handled correctly.

13. **And** no WordPress core tables (`wp_posts`, `wp_users`, …) are included — **plugin data only** (users are referenced by ID; full WP user export is out of scope).

### 4. Plugin options (`options/pr-plugin-options.json`)

14. **Given** backup runs  
    **When** options file is written  
    **Then** it exports all keys from `Install::get_uninstall_option_names()` with current values (JSON object).

### 5. Excel reports (reuse export pipeline — do not re-query ad hoc)

15. **Given** each project in scope  
    **When** reports are added to the ZIP  
    **Then** bytes are produced via existing services (same as Downloads tab):

| File | Builder | Notes |
|------|---------|--------|
| `consolidated-student-scores.xlsx` | `ReportsViewService::consolidated_student_scores_export()` | Session scope |
| `panel-roster.xlsx` | `ReportsViewService::panel_roster_export()` | Per **confirmed** review |
| `rubric-marks-matrix.xlsx` | `ReportsViewService::marks_grid_export()` | Per confirmed review; **layout** = same default as coordinator marks tab (`panel_reviewer` unless product already centralizes a constant — grep `DEFAULT_MARKS_LAYOUT` / marks tab default and use that single constant) |
| `overall-scores-matrix.xlsx` | `ReportsViewService::scores_matrix_export()` | Per confirmed review |

16. **And** only **`.xlsx`** inside ZIP for reports (user asked for Excel; no CSV/PDF in backup bundle).

17. **And** review slugs in paths come from the same slug helper used in export filenames (`ReportsViewService` / session repository — do not invent a new slug algorithm).

18. **And** if `marks_grid_export()` / `panel_roster_export()` returns `WP_Error` for a review, backup **records warning in manifest** (`warnings[]`) and **continues** other files; overall backup still succeeds unless zero projects could be processed.

### 6. REST API and binary delivery

19. **Given** REST bootstrap  
    **Then** register:

| Method | Route | Capability | Response |
|--------|-------|------------|----------|
| `GET` | `/project-reviews/v1/backup/download` | `pr_manage_settings` | Full-site ZIP |
| `GET` | `/project-reviews/v1/sessions/{id}/backup/download` | `pr_manage_sessions` | Single-project ZIP |

20. **Given** successful generation  
    **When** response is sent  
    **Then** use `Rest_Binary_Response::from_body()` with `Content-Type: application/zip`  
    **And** `Content-Disposition` filename like `project-reviews-backup-full-{Y-m-d-His}.zip` or `project-reviews-backup-{project-slug}-{Y-m-d-His}.zip`  
    **And** body starts with ZIP magic bytes `PK` (0x50 0x4B).

21. **Given** invalid session id on project backup  
    **Then** **404** `pr_session_not_found`.

22. **And** register routes in `includes/rest/class-rest-backup.php` + `Rest_Bootstrap::register_routes()` (mirror `Rest_Reports`).

### 7. UI entry points

23. **Given** WP Admin → Settings → Project Reviews (`Admin_Settings`)  
    **When** user has `pr_manage_settings`  
    **Then** a **Download full backup (ZIP)** button appears in a **Backup** section  
    **And** help text ties to Story 1-8 lifecycle copy (backup before uninstall)  
    **And** clicking triggers download via REST (admin-ajax or direct `window.location` / fetch+blob — match how other admin downloads work; prefer simple link to REST URL with nonce cookie for WP auth).

24. **Given** coordinator project context (choose one placement; implement the clearer option)  
    **When** user with `pr_manage_sessions` opens project **Close** screen **or** project dashboard header actions  
    **Then** **Download project backup (ZIP)** is visible  
    **And** uses `GET /sessions/{id}/backup/download` with loading/disabled state and error `Notice` on failure.

25. **And** native WP admin styles on settings section (UX-DR34); coordinator control uses existing `Button` + `Notice` patterns.

### 8. Implementation service

26. **Given** backup orchestration  
    **Then** `includes/services/BackupService.php` coordinates:
    - SQL generation (private methods or `DatabaseDumpHelper` in same file — avoid over-abstracting)
    - Options JSON
    - Per-project report bytes via `ReportsViewService` + `ExportService::to_xlsx()` where builders return rows
    - ZIP assembly via PHP `ZipArchive` (extension required — document in README; fail with clear error if missing)

27. **And** build ZIP under `sys_get_temp_dir()` with unique prefix `pr-backup-`; delete temp dir/file after send (register shutdown handler or try/finally).

28. **And** simple abuse guard: transient `pr_backup_throttle_{user_id}` 60s — second request within window returns **429** with message.

### 9. Performance and operational limits

29. **Given** large site (many projects / rows)  
    **Then** implementation streams ZIP file to output where possible (`ZipArchive::addFromString` per file is acceptable for v1; document memory risk in Dev Notes)  
    **And** set PHP `set_time_limit(0)` or raise limit only for backup callback (document hosting requirement).

30. **And** manifest includes `table_row_counts` summary per table for operator verification.

### 10. Tests

31. **And** `tests/BackupServiceTest.php`:
    - Fixture with one session, one confirmed review, minimal marks
    - Full backup ZIP: has `manifest.json`, `database/pr-plugin-data.sql`, at least one `projects/*/*.xlsx`
    - SQL contains `INSERT INTO` for `pr_sessions`
    - Project backup ZIP omits other sessions’ `pr_panels` rows in SQL (spot-check substring or parse)

32. **And** `tests/RestBackupTest.php`:
    - Authorized GET returns ZIP magic `PK`
    - Unauthorized 403
    - Throttle 429 on second rapid call

33. **And** `./vendor/bin/phpunit` green; `npm run build` if coordinator UI touched.

## Tasks / Subtasks

- [x] **Service:** `BackupService` — SQL dump, options JSON, manifest, ZIP build, project vs full scope
- [x] **REST:** `Rest_Backup` + routes; `Rest_Binary_Response` zip delivery; throttle transient
- [x] **Admin UI:** Backup section on `Admin_Settings` — full backup download
- [x] **Coordinator UI:** Project backup button on Close screen (`CloseSession.jsx`)
- [x] **Install:** Reuse public `Install::rubric_scores_view_ddl()` (no refactor required)
- [x] **Tests:** `BackupServiceTest`, `RestBackupTest`
- [x] Run `./vendor/bin/phpunit` and `npm run build`

## Dev Notes

### Epic placement

New **Epic 16: Data backup and portability** — complements Epic 1 lifecycle (1-8) and Epic 7/12 exports. Restore from ZIP is a future story (16-2+).

### Reuse map (mandatory)

| Need | Use |
|------|-----|
| Table list | `Install::get_pr_table_names()` |
| Option keys | `Install::get_uninstall_option_names()` |
| XLSX bytes | `ExportService::to_xlsx()` |
| Report rows | `ReportsViewService::*_export()` methods from Story 12.5 catalog |
| Binary HTTP | `Rest_Binary_Response::from_body()` |
| Session list | `SessionRepository` |
| Confirmed reviews | Same filter as Reports downloads / marks tab |

### Session-scoped tables (for project SQL filter)

When filtering by `session_id`, include rows where a `session_id` column exists; for join tables, filter via parent:

- `pr_sessions` (id match)
- `pr_panels`, `pr_session_students`, `pr_session_reviewers`, `pr_reviews`, `pr_review_*`, `pr_marks`, `pr_mark_audit`, `pr_unfreeze_requests`, weights, rubric criteria tied to reviews, etc.

**Do not** filter `pr_students`, `pr_field_definitions`, `pr_student_meta` on project backup.

### Anti-patterns (prevent regressions)

- **Do not** reimplement report SQL in `BackupService` — call `ReportsViewService`.
- **Do not** return ZIP as base64 JSON in REST.
- **Do not** include legacy seven `ReportQueryService` gallery types unless product explicitly asks (user asked for project reports = canonical Downloads Excel set).
- **Do not** add restore UI or auto-import SQL in this story.
- **Do not** dump `wp_users` / `wp_usermeta` (security + scope creep).

### Coordinator marks layout constant

Before implementing, grep coordinator marks tab default (`ReportsMarksTab`, `reportsMarksMatrixUtils`, or `ReportsViewService::marks_grid_export` default `$layout`). Use **one** constant shared between backup and UI default.

### WP Admin download auth

REST routes use cookie auth for logged-in admin. Pattern options:

1. `rest_url('project-reviews/v1/backup/download')` + `wp_create_nonce('wp_rest')` header via small inline script on settings page (like media picker pattern already on settings page).
2. Or admin-post action that streams ZIP — only if REST nonce is awkward; prefer REST consistency.

### Previous story intelligence

- **1-8:** Settings already mention backup; wire copy to real button.
- **7-7:** All file downloads must use `Rest_Binary_Response` — ZIP included.
- **12-5:** Canonical Excel deliverables list is the backup report set.
- **15-1:** `get_pr_table_names()` is single source of truth for table inventory — align SQL table list exactly.

### Project structure (files to add/touch)

| File | Action |
|------|--------|
| `includes/services/BackupService.php` | **New** |
| `includes/rest/class-rest-backup.php` | **New** |
| `includes/rest/class-rest-bootstrap.php` | Register backup routes |
| `includes/admin/class-admin-settings.php` | Backup section UI |
| `src/coordinator/pages/CloseSessionPage.jsx` or `SessionDashboard` | Project backup button |
| `tests/BackupServiceTest.php`, `tests/RestBackupTest.php` | **New** |
| `tests/bootstrap.php` | Stubs if `ZipArchive` / transients needed |

### ZIP / PHP requirements

- PHP `ext-zip` (`ZipArchive`) required; if `! class_exists('ZipArchive')`, return `WP_Error` `pr_zip_unavailable` with admin notice text.
- Filename sanitization: `sanitize_file_name()` on slugs.

### Security

- Institutional data — backups only for caps in AC §1.
- Throttle per user (AC §28).
- No public/anonymous endpoints.
- Do not log SQL contents or marks in `error_log`.

### Out of scope (explicit)

- Restore/import wizard
- Automated scheduled backups (cron)
- CSV/PDF inside ZIP
- Full WordPress DB dump
- Encrypting ZIP with password

## Dev Agent Record

### Agent Model Used

Auto (Cursor agent router)

### Debug Log References

### Completion Notes List

- Added `BackupService` for full/project ZIP assembly: SQL via `SHOW CREATE TABLE` + scoped `INSERT`s, options JSON, README, manifest with warnings and row counts, Excel via `ReportsViewService` + `ExportService`.
- REST: `GET /backup/download` (`pr_manage_settings`) and `GET /sessions/{id}/backup/download` (`pr_manage_sessions`); 60s per-user throttle (429); binary ZIP via `Rest_Binary_Response`.
- Admin Settings: “Download full backup (ZIP)” with REST fetch + nonce. Coordinator: button on Close project screen.
- Marks matrix layout constant: `ReportsViewService::DEFAULT_MARKS_MATRIX_LAYOUT` (`rubric`).
- PHPUnit: 342 tests green; `npm run build` run for coordinator bundle.

### File List

- includes/services/BackupService.php (new)
- includes/services/ReportsViewService.php
- includes/rest/class-rest-backup.php (new)
- includes/rest/class-rest-bootstrap.php
- includes/admin/class-admin-settings.php
- src/coordinator/pages/CloseSession.jsx
- tests/BackupServiceTest.php (new)
- tests/RestBackupTest.php (new)
- tests/FakeWpdb.php
- tests/bootstrap.php
- build/coordinator.js
- build/coordinator.asset.php

### Change Log

- 2026-05-23: Story 16.1 — project backup ZIP (SQL, options, Excel reports); REST, admin + close UI, tests.
