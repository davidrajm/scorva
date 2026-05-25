# Story 8.4: Delete project — full cascade with confirmations

Status: review

<!-- Ultimate context engine analysis completed — mirrors review-round delete (ConfirmDialog + confirm_label), replaces unsafe SessionRepository::delete -->

## Story

As a **review coordinator**,
I want to **permanently delete a project** and all of its data after explicit confirmation (stricter when marks exist),
so that I can remove mistaken or obsolete projects without leaving orphaned database rows or silent partial wipes.

## Background — current behaviour (do not guess)

| Layer | Today | Problem |
|-------|--------|---------|
| **REST** | `DELETE /sessions/{id}` → `Rest_Sessions::delete_session()` | Calls `SessionRepository::delete()` only |
| **Repository** | `SessionRepository::delete()` | Deletes `pr_session_students` + `pr_sessions` row **only** |
| **Orphans** | Reviews, marks, panels, freezes, audit, options, etc. | Remain in DB with dangling `session_id` / `review_id` |
| **UI** | Dashboard `SessionCard` | **No** delete affordance |
| **Tests** | `RestSessionsTest` | **No** coverage for `DELETE /sessions/{id}` |

### Reference pattern — review round delete (copy this UX + API shape)

| Concern | Review delete (canonical) |
|---------|---------------------------|
| **REST** | `DELETE /sessions/{id}/reviews/{review_id}` in [`class-rest-reviews.php`](../../includes/rest/class-rest-reviews.php) |
| **Scores guard** | `count_entered_scores_for_review() > 0` → require body `{ confirm_label }` matching **exact** round `label` (trim, case-sensitive) |
| **Cascade** | Marks, panel freezes, unfreeze requests → `ReviewRepository::delete()` |
| **UI** | Two `ConfirmDialog`s in [`ReviewRoundsStep.jsx`](../../src/coordinator/components/ReviewRoundsStep.jsx) / [`RubricsPanel.jsx`](../../src/coordinator/components/RubricsPanel.jsx) |
| **List hint** | `has_entered_scores` on `format_review()` |

This story applies the **same two-tier confirmation** at **project** grain, with a **session-wide cascade** before removing `pr_sessions`.

### Out of scope (explicit)

- Deleting rows from the **global student registry** (`pr_students`, `pr_student_meta`, `pr_field_definitions`) — enrolment only.
- Deleting **WordPress user accounts** (provisioned reviewers/coordinators remain; only `pr_session_reviewers` and session-scoped links go away). Align with NFR15 / Story 8 close policy: account lifecycle is not “delete user on project delete”.
- **Restore / undo** — permanent delete only. Point coordinators to Story **16-1** backup ZIP before destructive action (mention in UI consequences, do not implement backup here).
- Blocking delete when project is `closed` — **allow** delete for any status; closed projects may still be removed (optional: extra consequence bullet that disabled reviewer rows for this project will be removed).

## Acceptance Criteria

### 1. Delete service — full cascade

1. **Given** a valid project id **When** `SessionDeleteService::delete()` runs successfully **Then** all session-scoped plugin data for that project is removed **before** the `pr_sessions` row is deleted, including at minimum:
   - All `pr_marks` and related `pr_mark_audit` rows for marks in that session (and session-level audit rows `entity = session`, `entity_id = session_id`)
   - All review-scoped rows for each review in the project: criteria, review/reviewer weights, assignment tables (`ReviewAssignmentRepository::clear_review_assignments` per review), panel freezes, panel/reviewer unfreeze requests, reviewer unfreeze requests
   - Each review via existing `ReviewRepository::delete()` **or** equivalent ordering that avoids FK/orphan issues
   - All panels (`PanelRepository::delete` per panel) and `pr_session_reviewers` rows for the session
   - `pr_session_students` enrolment rows
   - `SessionPanelReportSettings::delete($session_id)` option
2. **And** the method returns `{ ok: true, deleted: true }`.
3. **Given** project not found **Then** `{ ok: false, error: 'session_not_found' }` with no mutations.

### 2. Confirmation — API (mirror review delete)

4. **Given** the project has **no** entered numeric scores in any review (`score IS NOT NULL` in `pr_marks` for that `session_id`) **When** `DELETE /sessions/{id}` runs **Then** delete succeeds **without** `confirm_label` in the body.
5. **Given** at least one entered score exists for the project **When** `DELETE` runs **without** `confirm_label` matching the project title **Then** `400` with code `pr_session_delete_confirmation_required` and message instructing to type the exact project title.
6. **Given** entered scores exist **When** body contains `confirm_label` equal to trimmed `pr_sessions.title` **Then** cascade delete succeeds.
7. **Given** user lacks `pr_manage_sessions` **Then** `403`.

### 3. API — list/detail hints for UI

8. **Given** `GET /sessions` or `GET /sessions/{id}` **When** sessions are formatted **Then** each includes `has_entered_scores: bool` (true when any mark in that session has non-null `score`), computed via new `MarkRepository::count_entered_scores_for_session(int $session_id): int` (or equivalent).

### 4. UI — dashboard delete with ConfirmDialog

9. **Given** coordinator on **Dashboard** **When** they activate **Delete project** on a card (control must **not** navigate into the project — `stopPropagation` on the card `Link`) **Then** behaviour matches review delete:
   - **No scores:** single destructive `ConfirmDialog` with consequence bullets (roster, panels, review rounds, rubrics, assignments, marks draft rows, freezes, audit for this project).
   - **Has scores:** second dialog variant — stronger title (“Delete {title} and all scores?”), consequences include permanent mark loss, user must type **exact project title** before confirm is enabled; `DELETE` sends `{ confirm_label }`.
10. **And** on success the project disappears from the list (refresh `loadSessions`) **And** if the user was inside that project’s routes, navigate to `/` (dashboard).
11. **And** user-facing copy uses **project** not session (Story 10-1).

### 5. Account meta hygiene (closed-project edge case)

12. **Given** reviewers had `pr_account_disabled` from project close **When** project is deleted **Then** for each user linked via `pr_session_reviewers` for this session, clear `pr_account_disabled` user meta **only if** no other `pr_session_reviewers` row for that user still has non-null `disabled_at` (reuse logic from `SessionCloseService::reopen()` / `enable_user` — extract shared helper if needed, do not duplicate incorrectly).

### 6. Audit

13. **Given** successful delete **When** cascade runs **Then** append audit row `session_deleted` with `entity = session`, `entity_id = session_id`, `old_value` = project title (or status+title JSON if consistent with other actions) **before** removing session row **And** do not delete global audit history outside this project’s scope except mark rows removed with marks.

### 7. Tests and build

14. **Given** PHPUnit **Then** `RestSessionsTest` (or `SessionDeleteServiceTest`) covers: delete empty project succeeds; delete with scores blocked without `confirm_label`; delete with scores succeeds with title match; `GET` list exposes `has_entered_scores`; no orphaned `pr_reviews` row after delete.
15. **And** `composer test` + `npm run build` pass.

## Tasks / Subtasks

- [x] Add `MarkRepository::count_entered_scores_for_session()` (AC: 4–6, 8)
- [x] Implement `SessionDeleteService` with ordered cascade (AC: 1–3, 12–13)
- [x] Replace `Rest_Sessions::delete_session()` to use service + confirmation rules (AC: 4–7)
- [x] Extend `format_session()` with `has_entered_scores` (AC: 8)
- [x] Dashboard: delete control on `SessionCard` or card actions + dual `ConfirmDialog` (AC: 9–11)
- [x] PHPUnit coverage (AC: 14)
- [x] Run `composer test` + `npm run build`

## Dev Notes

### Implementation sketch — cascade order

Prefer **one service class** [`includes/services/SessionDeleteService.php`](../../includes/services/SessionDeleteService.php) (mirror [`SessionCloseService.php`](../../includes/services/SessionCloseService.php)):

```php
// Pseudocode — adjust to actual repo methods
$session = $sessions->find_by_id($id);
$reviews = $reviews->list_for_session($id);
foreach ($reviews as $review) {
    $rid = (int) $review['id'];
    $marks->delete_all_for_review($rid);
    $panelFreezes->delete_all_for_review($rid);
    $panelUnfreeze->delete_all_for_review($rid);
    $unfreeze->delete_all_for_review($rid);
    $reviews->delete($rid);
}
foreach ($panels->list_by_session($id) as $panel) {
    $panels->delete((int) $panel['id']);
}
// pr_session_reviewers, enrolment, audit cleanup, settings option
$audit->log('session_deleted', 'session', $id, $title, null);
$sessions->delete($id); // only enrolment + session row — or inline SQL after service clears children
```

**Important:** Today’s `SessionRepository::delete()` only clears enrolment — either expand it to call the service internals or make repository `delete()` delegate to `SessionDeleteService` so REST cannot bypass cascade.

Delete **audit** rows tied to removed marks (see `AuditService::mark_ids_for_session`) so the audit UI does not reference ghost mark ids.

### REST body parsing

Reuse `Rest_Sessions::request_body($request)` pattern from reviews:

```php
$phrase = isset($body['confirm_label']) ? trim((string) $body['confirm_label']) : '';
$expected = trim((string) ($existing['title'] ?? ''));
```

Error code: `pr_session_delete_confirmation_required` (400), matching `pr_review_delete_confirmation_required`.

### UI — copy from review delete

Mirror [`ReviewRoundsStep.jsx`](../../src/coordinator/components/ReviewRoundsStep.jsx) lines ~149–440:

- State: `deleteTarget`, `deletePhrase`, `busy`
- `del(\`/sessions/${ id }\`, payload)`
- `parseApiErrorMessage` on failure
- `data-testid` suggestions: `pr-delete-project`, `pr-delete-project-confirm-input`

**SessionCard change:** Add optional `onDelete` / action slot **outside** the navigational `Link`, or pass `actions` prop rendered beside the title with `e.preventDefault(); e.stopPropagation();` on delete button click.

### Files to touch

| File | Change |
|------|--------|
| `includes/services/SessionDeleteService.php` | **New** — cascade + meta cleanup + audit |
| `includes/rest/class-rest-sessions.php` | `delete_session`, `format_session` |
| `includes/repositories/MarkRepository.php` | `count_entered_scores_for_session` |
| `includes/repositories/SessionRepository.php` | Optional: delegate delete to service |
| `src/coordinator/pages/Dashboard.jsx` | Delete UI + dialogs |
| `src/shared/components/SessionCard.jsx` | Optional actions slot (if needed) |
| `tests/RestSessionsTest.php` or `tests/SessionDeleteServiceTest.php` | Coverage |

### Do not

- Call bare `SessionRepository::delete()` from REST without cascade.
- Require `pr_close_session` for delete — use existing `pr_manage_sessions` on `DELETE /sessions/{id}` (same as create/update).
- Add a separate `DELETE` route — extend existing session DELETE.
- Delete global registry students or WP users.

### Relationship to other stories

| Story | Interaction |
|-------|-------------|
| **4-7 / 13-1** | Review-round delete UX — **copy** dialog + `confirm_label` |
| **8-1–8-3** | Close/reopen — delete may run on closed projects; clear disabled meta per AC 12 |
| **16-1** | Backup ZIP — mention in destructive dialog as recommended before delete |
| **1-8** | Plugin uninstall wipe is separate (all tables); do not conflate |

### Testing

**PHPUnit:**

1. Create project + review + mark with `score = 4.0` → `DELETE` without body → 400 `pr_session_delete_confirmation_required`.
2. Same with `confirm_label` = title → 200, `find_by_id` null, `ReviewRepository::count_for_session` = 0.
3. Create project, no scores → `DELETE` without body → 200.
4. `GET /sessions` → item includes `has_entered_scores: true/false`.

**Manual:**

- Dashboard: delete draft project (no scores) — one confirm, gone from list.
- Project with marks: type wrong title → confirm disabled / API 400; correct title → deleted.

### References

- [Source: includes/rest/class-rest-reviews.php — `delete_review`, `format_review` `has_entered_scores`]
- [Source: includes/rest/class-rest-sessions.php — `delete_session`, `format_session`]
- [Source: includes/repositories/SessionRepository.php — current narrow `delete()`]
- [Source: includes/services/SessionCloseService.php — `enable_user` / disabled meta]
- [Source: includes/Install.php — `get_pr_table_names()` canonical table list]
- [Source: src/coordinator/components/ReviewRoundsStep.jsx — dual ConfirmDialog]
- [Source: _bmad-output/implementation/8-3-reopen-closed-project.md]
- [Source: _bmad-output/implementation/3-2-sessions-rest-dashboard.md]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

### Completion Notes List

- Added `SessionDeleteService` with per-review cascade (marks, freezes, unfreeze requests, `ReviewRepository::delete`), panel delete, `pr_session_reviewers` wipe, panel report settings option removal, scoped audit cleanup, `session_deleted` audit log, then `SessionRepository::delete`.
- REST `DELETE /sessions/{id}` mirrors review-round delete: optional `confirm_label` must match trimmed project title when any entered score exists; `format_session` exposes `has_entered_scores`.
- Extracted `SessionReviewerAccountMeta` for shared `pr_account_disabled` cleanup (used by close reopen and project delete).
- Dashboard: Delete button on each card (outside link via `SessionCard` `actions` slot), dual `ConfirmDialog`, navigates to `/` when deleting the open project.
- PHPUnit: 4 new tests in `RestSessionsTest`; full suite 346 tests pass; `npm run build` OK.

### File List

- includes/services/SessionDeleteService.php (new)
- includes/services/SessionReviewerAccountMeta.php (new)
- includes/services/AuditService.php
- includes/services/SessionCloseService.php
- includes/repositories/MarkRepository.php
- includes/rest/class-rest-sessions.php
- src/shared/components/SessionCard.jsx
- src/coordinator/pages/Dashboard.jsx
- tests/RestSessionsTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php

### Change Log

- 2026-05-24: Implemented full project delete cascade, confirmation API, dashboard UI, and tests.
- 2026-05-23: Story created — full project delete with cascade and review-style confirmations; fixes unsafe partial `SessionRepository::delete`.
