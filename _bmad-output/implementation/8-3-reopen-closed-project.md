# Story 8.3: Reopen closed project

Status: review

<!-- Ultimate context engine analysis completed — inverse of SessionCloseService policy B, audit trail, account re-enable edge cases -->

## Story

As a **review coordinator**,
I want to **reopen a closed project** with explicit confirmation,
so that **marking can resume and provisioned reviewer accounts disabled at close are restored** when a project was closed by mistake or needs a controlled correction window.

## Background — current behaviour (do not guess)

### Close flow (Stories 8.1 / 8.2 — already shipped)

| Layer | Behaviour |
|-------|-----------|
| **Service** | [`SessionCloseService::close()`](../../includes/services/SessionCloseService.php) sets `pr_sessions.status` → `closed`; disables provisioned reviewers per policy B; optional coordinator disable via `also_disable_coordinators` |
| **Disable mechanics** | Sets `pr_session_reviewers.disabled_at` for affected rows **and** `user_meta pr_account_disabled = '1'` globally on the WP user |
| **REST** | `GET /sessions/{id}/close-preview`, `POST /sessions/{id}/close` in [`class-rest-session-close.php`](../../includes/rest/class-rest-session-close.php) |
| **UI** | [`CloseSession.jsx`](../../src/coordinator/pages/CloseSession.jsx) — when `preview.status === 'closed'`, shows `EmptyState` (“This project is closed”) with **no reopen action** |
| **Marking guard** | [`MarkService`](../../includes/services/MarkService.php) rejects mark/attendance mutations when session status is `closed` (`session_closed` / `session_not_active`) |
| **Auth guard** | [`Rest_Auth`](../../includes/rest/class-rest-auth.php) blocks REST for users with `pr_account_disabled` meta |
| **Audit** | `session_closed` + per-user `account_disabled` rows via [`AuditService`](../../includes/services/AuditService.php) |
| **Capability** | `pr_close_session` (`PR_CAP_CLOSE_SESSION`) required to POST close; preview allows `pr_close_session` **or** `pr_manage_sessions` |

### Gap

There is **no inverse operation**: coordinators cannot restore project status or re-enable accounts without manual DB intervention. Mis-closes or post-close corrections require reopen.

### Product intent (symmetric to close — policy B)

Reopen should:

1. Change session status from `closed` back to a **working** status (`active` preferred; restore pre-close status from audit when available).
2. Re-enable **only** reviewer accounts that were disabled **for this project** (`pr_session_reviewers.disabled_at IS NOT NULL` for this `session_id`).
3. Clear global `pr_account_disabled` meta **only when** the user has no remaining disabled session-reviewer rows on **other** projects (multi-project edge case).
4. Append audit rows (`session_reopened`, `account_enabled`).
5. **Not** automatically undo coordinator marks lock, reviewer freeze/submitted state, rubric unlock, or review `marking_active` — those are separate governance flows (Stories 5-6, 5-18, 7-5).

## Acceptance Criteria

1. **Reopen service (happy path)** — **Given** a project with `status = closed` **When** `SessionCloseService::reopen()` runs **Then** status becomes `active`, or the **previous status** recorded on the latest `session_closed` audit row (`old_value`) when that value is `draft` or `active` **And** marking is allowed again subject to existing rubric/assignment/freeze guards **And** the method returns `{ ok: true, session, reenabled_user_ids }`.

2. **Account re-enable (policy B inverse)** — **Given** `pr_session_reviewers` rows for the project with non-null `disabled_at` **When** reopen runs **Then** `disabled_at` is cleared for those rows **And** `pr_account_disabled` user meta is removed for each re-enabled user **unless** another `pr_session_reviewers` row for that user still has non-null `disabled_at` (another closed project) **And** audit logs `account_enabled` per re-enabled user.

3. **Guards** — **Given** project not found **Then** `{ ok: false, error: 'session_not_found' }` **Given** project status is not `closed` **Then** `{ ok: false, error: 'session_not_closed' }` **And** no status or account mutations occur.

4. **Audit** — **Given** successful reopen **Then** audit row `session_reopened` is appended with `entity = session`, `old_value = closed`, `new_value = {restored status}` **And** existing `session_closed` / `account_disabled` rows are preserved (append-only log).

5. **REST** — **Given** user with `pr_close_session` **When** `POST /sessions/{id}/reopen` on a closed project **Then** `200` with `{ session, reenabled_user_ids }` **When** project is not closed **Then** `400` with error code `session_not_closed` **When** user lacks capability **Then** `403` **And** routes registered in [`class-rest-session-close.php`](../../includes/rest/class-rest-session-close.php) (extend existing REST class; do not create parallel controller).

6. **Reopen preview data** — **Given** closed project **When** coordinator loads close screen (`GET close-preview`) **Then** response includes **`disabled_accounts`** — count of `pr_session_reviewers` rows for this session with non-null `disabled_at` **And** when status is not closed, `disabled_accounts` is `0` (backward compatible additive field).

7. **UI — closed state** — **Given** coordinator opens **Close project** nav for a closed project **When** the page loads **Then** replace read-only `EmptyState` with a **Reopen project** section showing: closed status chip, count of disabled accounts (from preview), consequence bullet list (UX-DR33 pattern) **When** they confirm via `ConfirmDialog` **Then** `POST reopen` runs, success `Notice` shows (include re-enabled count when > 0), preview refreshes to active/draft state and close UI returns **And** user-facing copy says **project** not session (Story 10-1 terminology).

8. **Permissions & regression** — **Given** user without `pr_close_session` **Then** reopen button hidden/disabled with same warning pattern as close **And** `SessionCloseServiceTest` covers reopen happy path, `session_not_closed`, multi-session meta edge case, audit actions **And** `composer test` + `npm run build` pass.

## Tasks / Subtasks

- [x] **Service — `reopen()`** (AC: 1, 2, 3, 4)
  - [x] Add `SessionCloseService::reopen(int $session_id, ?int $actor_user_id = null)` mirroring `close()` structure.
  - [x] Resolve restored status: latest `session_closed` audit `old_value` if `draft|active`, else `active`.
  - [x] Query disabled reviewers for session; clear `disabled_at`; conditionally clear `pr_account_disabled` meta.
  - [x] Private `enable_user(int $user_id, int $session_id)` inverse of existing `disable_user`.
  - [x] Audit `session_reopened` + `account_enabled`.

- [x] **Preview extension** (AC: 6)
  - [x] Add `disabled_accounts` to `close_preview()` return array.

- [x] **REST** (AC: 5)
  - [x] Register `POST /sessions/(?P<id>\d+)/reopen` with same auth as close POST.
  - [x] Map service errors to `WP_Error` (`session_not_found` → 404, `session_not_closed` → 400).

- [x] **UI** (AC: 7, 8)
  - [x] Update [`CloseSession.jsx`](../../src/coordinator/pages/CloseSession.jsx) closed branch: summary + **Reopen project…** destructive-secondary button (prefer `variant="secondary"` or primary — not destructive; reopen is restorative).
  - [x] `ConfirmDialog` consequences: status returns to active; marking resumes; N accounts re-enabled; does **not** unlock coordinator marks lock or reviewer submitted scores.
  - [x] Wire `post('sessions/${id}/reopen')`; success/error notices; refresh preview.

- [x] **Tests** (AC: 8)
  - [x] Extend [`SessionCloseServiceTest.php`](../../tests/SessionCloseServiceTest.php): reopen restores status, re-enables provisioned user, skips clearing meta when user disabled on second session, audit actions, `session_not_closed`.
  - [x] Add REST test file or extend existing close REST tests if present.

- [x] **Verification**
  - [x] Manual: close demo project → reopen → reviewer can log in and save marks (if rubric confirmed).
  - [x] Manual: closed EmptyState → reopen → close UI available again.
  - [x] `./vendor/bin/phpunit` + `npm run build`.

## Dev Notes

### Technical requirements

- **Terminology:** User-facing “project”; code/REST remain `session`, error codes unchanged (`session_not_closed`, etc.).
- **Capability:** Reuse `PR_CAP_CLOSE_SESSION` for reopen POST — same high-stakes lifecycle permission as close. Preview remains readable with `pr_close_session` OR `pr_manage_sessions`.
- **Status default:** Prefer audit-restored status; fallback `SessionRepository::STATUS_ACTIVE`. Never reopen to `closed`.
- **Do not send email** on reopen — no setting exists (Story 9-4 covers close/rubric only); out of scope unless product adds toggle later.
- **Coordinator marks lock** (`coordinator_marks_locked` on reviews, Story 7-5): reopen does **not** clear it — document in UI consequences.
- **Reviewer `submitted` / panel freeze** (Stories 5-6, 5-15): unchanged by reopen; marking resumes only where existing guards allow edits.

### Architecture compliance

- Extend [`SessionCloseService`](../../includes/services/SessionCloseService.php) — keep close/reopen lifecycle in one service (design spec §8.3); do **not** introduce a new service class for one method.
- REST stays in [`includes/rest/class-rest-session-close.php`](../../includes/rest/class-rest-session-close.php); register in [`class-rest-bootstrap.php`](../../includes/rest/class-rest-bootstrap.php) if not auto-loaded.
- React: coordinator SPA, shared `ConfirmDialog`, `Notice`, `StatusChip`, `Button` from [`src/shared/components`](../../src/shared/components).
- Audit via existing `AuditService::log()` — action strings: `session_reopened`, `account_enabled` (parallel to `session_closed`, `account_disabled`).

### File structure (expected touch sets)

| File | Change |
|------|--------|
| [`includes/services/SessionCloseService.php`](../../includes/services/SessionCloseService.php) | `reopen()`, `enable_user()`, preview field |
| [`includes/rest/class-rest-session-close.php`](../../includes/rest/class-rest-session-close.php) | `reopen` route + handler |
| [`src/coordinator/pages/CloseSession.jsx`](../../src/coordinator/pages/CloseSession.jsx) | Closed-state reopen UI |
| [`tests/SessionCloseServiceTest.php`](../../tests/SessionCloseServiceTest.php) | Reopen unit tests |
| `tests/RestSessionCloseTest.php` (new or existing) | REST permission + error codes |

### Testing requirements

- **PHPUnit (required):**
  - Close project with provisioned reviewer → reopen → `disabled_at` null, meta cleared, status `active`.
  - User disabled on session A and session B → reopen A only → meta **stays** until B also reopened.
  - Reopen active project → `session_not_closed`.
  - Audit contains `session_reopened` and `account_enabled`.
- **Manual:** Full close → reopen → reviewer assignment list no longer `session_closed`; mark save succeeds when rubric confirmed.
- **Build:** `npm run build` after JSX changes.

### Previous story intelligence (8.1, 8.2)

- Policy B: provisioned reviewers disabled by default; coordinator-capable users skipped unless `also_disable_coordinators`. Reopen re-enables **all** rows with `disabled_at` on this session — including coordinators disabled via opt-in checkbox (symmetric inverse).
- [`CloseSession.jsx`](../../src/coordinator/pages/CloseSession.jsx) already loads `close-preview` and handles `isClosed` — extend that branch rather than new route/page.
- `ConfirmDialog` + consequence bullet list is the established high-stakes pattern (UX-DR33); mirror close dialog structure.
- `close_preview` already used by UI — extend response shape; front-end should tolerate missing `disabled_accounts` during rollout (`?? 0`).

### Risks / edge cases

| Scenario | Expected behaviour |
|----------|-------------------|
| Project closed from `draft` | Reopen restores `draft` via audit `old_value` |
| No audit row (legacy data) | Fallback status `active` |
| Linked (non-provisioned) reviewer never disabled | Unaffected; `reenabled_user_ids` may be empty |
| User manually re-enabled in WP admin but `disabled_at` still set | Reopen clears `disabled_at`; idempotent meta clear |
| Session closed with 0 provisioned users | Reopen still sets status active; success copy without account count |
| Reviewer password unchanged | Re-enable meta only — no re-provision email |

### Out of scope

- Reopen notification email
- Bulk reopen from dashboard
- Auto-reopen on unfreeze request grant
- Renaming `CloseSession.jsx` → lifecycle page (Story 10-1 optional follow-up)
- Unlocking coordinator marks lock or reviewer submitted scores

### References

- [Source: _bmad-output/planning/epics.md — Epic 8]
- [Source: _bmad-output/implementation/8-1-session-close-service.md]
- [Source: _bmad-output/implementation/8-2-close-session-ui.md]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §8.3 Session close (policy B)]
- [Source: _bmad-output/planning/ux-design-specification.md — Close Session Safely journey, UX-DR33 consequence confirms]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Implemented `SessionCloseService::reopen()` as inverse of policy B close: restores status from latest `session_closed` audit (`draft`/`active`) or `active`; clears `disabled_at` for session reviewers; removes `pr_account_disabled` meta only when no other disabled session-reviewer rows remain.
- Extended `close_preview()` with `disabled_accounts` (0 when project not closed).
- Added `POST /sessions/{id}/reopen` REST route with `PR_CAP_CLOSE_SESSION` auth and error mapping.
- Replaced closed-state `EmptyState` in `CloseSession.jsx` with reopen summary, consequence `ConfirmDialog`, and success notices using project terminology.
- PHPUnit: 7 new service tests + `RestSessionCloseTest.php` (3 cases). Full suite 316 tests OK; `npm run build` OK.

### File List

- includes/services/SessionCloseService.php
- includes/rest/class-rest-session-close.php
- src/coordinator/pages/CloseSession.jsx
- tests/SessionCloseServiceTest.php
- tests/RestSessionCloseTest.php
- tests/FakeWpdb.php
- tests/bootstrap.php
- build/coordinator.js
- build/coordinator.asset.php

### Change Log

- 2026-05-20: Story 8.3 — reopen closed project (service, REST, UI, tests).
