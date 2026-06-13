# Story 27.2: Faculty accounts — plain-language copy, sample CSV row, sync email validation

Status: draft

## Story

As a **non-technical coordinator**,
I want **the faculty accounts screen and import template to read in plain language and reject malformed emails during directory sync**,
So that I understand what the screen does and don't provision unreachable accounts.

## Background — current behaviour (do not guess)

1. **Developer-centric copy** — confirmed at `src/coordinator/pages/FacultyAccounts.jsx:112`:
   > "Maintain reviewer WordPress accounts before assigning them to panels. **Import is silent** — use Email all reviewers on Open reviews when marking starts."
   "Import is silent" and "WordPress accounts" are implementation language. (Note: per the reviewer auth rebuild, reviewers no longer rely on WordPress logins for the portal — the "WordPress accounts" wording is also now inaccurate.)

2. **Faculty CSV template has headers only** — `assets/csv/faculty-accounts-import-template.csv` is just `empId,name,designation,gender,email` with no example row, whereas `assets/csv/students-import-template.csv` ships sample rows. Coordinators have no format reference.

3. **Sync skips empty but not malformed emails** — `FacultyAccountService::sync_from_directory` (~line 268) only checks `$email === ''`, then hands the value to `provision_reviewer_account`. A malformed address (e.g. `john.doe`) passes the empty check and gets provisioned, unlike the CSV import which requires a valid email (`error_row(..., 'A valid email is required.')`).

## Acceptance Criteria

1. **Given** the faculty accounts page header/description
   **Then** copy is rewritten in plain language: explain that this is the reviewer pool, that importing/adding does not notify anyone, and that credentials are emailed separately when reviews open — with no "silent" / "WordPress accounts" jargon
   **And** the strings go through the i18n function with the (post-rename) text domain

2. **Given** the faculty import template
   **Then** it includes at least one realistic sample row under the headers, matching the student template's pattern (clearly example data, e.g. an obviously-fake empId/email)

3. **Given** directory sync
   **When** a row's email fails `is_email()`
   **Then** it is counted as `skipped`/`failed` (consistent with the existing result shape) rather than provisioned, matching CSV import's validation
   **And** the same `is_email()` guard is applied wherever provisioning reads an email (single create, CSV, sync) so validation is uniform

4. **Given** the rewritten copy
   **Then** a quick sweep of the faculty page catches sibling jargon (button labels, helper text) and aligns them, but scope stays on the faculty accounts page

## Tasks / Subtasks

- [ ] Rewrite `FacultyAccounts.jsx:112` description and audit nearby labels for jargon
- [ ] Add a sample row to `assets/csv/faculty-accounts-import-template.csv`
- [ ] Add `is_email()` validation in `sync_from_directory`; ensure provision path validates uniformly
- [ ] PHPUnit: sync skips malformed email; rebuild assets

## Dev Notes

### File structure (expected touch set)

- `src/coordinator/pages/FacultyAccounts.jsx:112`
- `assets/csv/faculty-accounts-import-template.csv`
- `includes/services/FacultyAccountService.php` (~line 268)

### Note on a stale review claim

The earlier review said the faculty import "doesn't provide a download link" for the error CSV. This is **already implemented** — `CsvImportMapper.jsx` consumes `result.error_csv` and renders a download (lines ~373, ~512, ~884). No story is needed for it.
