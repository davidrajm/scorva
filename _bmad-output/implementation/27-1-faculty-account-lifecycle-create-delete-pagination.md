# Story 27.1: Faculty accounts — individual create, bulk delete, real pagination

Status: draft

## Story

As a **coordinator managing the reviewer/faculty pool**,
I want **to add a single faculty account, remove accounts in bulk, and page through more than 500 reviewers**,
So that I'm not forced to round-trip a CSV for one person, can clean up stale accounts, and don't silently lose reviewers past the 500th.

## Background — current behaviour (do not guess)

Confirmed in `includes/rest/class-rest-faculty-accounts.php` — only three routes exist:

- `GET /faculty-accounts` (list)
- `POST /faculty-accounts/import` (CSV)
- `POST /faculty-accounts/sync-directory`

So:

1. **No single-account create** — the only way to add one faculty member is a one-row CSV.
2. **No delete** (single or bulk) — accounts can be added but never removed through the UI.
3. **Hard 500 cap** — `FacultyAccountService` list calls `get_users([... 'number' => 500 ...])` (~line 60). The method computes `page`/`per_page`/`total` in its return shape but the underlying query never offsets, so anything past 500 reviewers is unreachable and `total` is wrong.

## Acceptance Criteria

1. **Given** a coordinator with the faculty-management capability
   **When** they submit a single faculty account (name, email, optional empId/designation/gender)
   **Then** a `POST /faculty-accounts` endpoint provisions it via the same `ReviewerProvisionService` path the CSV import uses, with the same validation (valid email required, dedupe by empId/email), returning the created account or a field-level error
   **And** the UI exposes an "Add faculty" form alongside the existing import

2. **Given** the faculty accounts list
   **When** the coordinator selects rows and chooses delete
   **Then** a `DELETE`/bulk endpoint removes the selected accounts with a confirm step and an audit entry
   **And** deletion behavior toward existing panel assignments is explicit: block-with-message if the account is assigned to a panel, or cascade with warning — pick one and document it (recommend block-with-message to avoid orphaning marks)

3. **Given** more than 500 reviewers
   **When** the list is paged
   **Then** the query honors `page`/`per_page` with a correct `offset` and returns an accurate `total`/`total_pages`, and the UI paginates instead of truncating

4. **Given** the new endpoints
   **Then** all enforce the existing faculty-management capability and REST nonce, consistent with the other faculty routes

## Tasks / Subtasks

- [ ] `POST /faculty-accounts` (single create) reusing provision + validation
- [ ] `DELETE /faculty-accounts/{id}` and/or bulk delete endpoint; assignment-guard decision
- [ ] Fix `get_users` paging in `FacultyAccountService` (paged + offset + accurate count)
- [ ] UI: add-faculty form, row selection + bulk delete with `ConfirmDialog`, pagination control
- [ ] Audit logging on create/delete
- [ ] PHPUnit: create validation, delete guard, pagination correctness past 500

## Dev Notes

### File structure (expected touch set)

- `includes/rest/class-rest-faculty-accounts.php`
- `includes/services/FacultyAccountService.php` (~line 60 query)
- `src/coordinator/pages/FacultyAccounts.jsx`

### Out of scope

- UX copy + sample CSV row + sync email validation (story 27-2)
