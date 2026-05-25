# Story 5.18: Review coordinator freeze — all panels frozen, marking_active sync, unlock

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **project coordinator**,
I want to **freeze** a review round only after every participating panel has frozen panel scores,
So that committee sign-off is complete before I lock the whole review and stop all further mark and attendance changes.

As a **project coordinator**,
I want to **unlock** a frozen review without an approval workflow,
So that I can reopen marking when I locked by mistake or need a controlled correction.

As a **reviewer**,
I want the wizard and assignments to show marking as closed when the coordinator has frozen the review,
So that I am not misled by “Marking active” while scores are locked.

## Problem statement (user report — gaps in 7.5 lock)

Story **7.5** shipped `coordinator_marks_locked` via **Lock marks for this review** on Reports. Mark entry for reviewers is correctly blocked, but:

| # | Gap | Expected after this story |
|---|-----|---------------------------|
| 1 | Wizard **Reviews** table still shows **Marking active** when review is locked | Lock sets `marking_active = 0`; UI shows **Marks locked** (not active checkbox on) |
| 2 | **Correct attendance** still works while locked | Blocked server-side + hidden/disabled in coordinator UI |
| 3 | No **unlock** | Coordinator **Unlock marks** — instant, no approval queue |
| 4 | Lock allowed before all panels frozen | Lock only when every **participating** panel has a `pr_review_panel_freezes` row |

**Terminology:** Reuse existing flag `coordinator_marks_locked` (DB/API). User-facing copy may say **Freeze review** / **Unlock review** on Reports; keep API codes `coordinator_marks_locked` for backward compatibility.

## Acceptance Criteria

### 1. Participating panels — definition

1. **Given** a confirmed review on an active project  
   **When** evaluating readiness to freeze the review  
   **Then** **participating panels** = distinct `panel_id > 0` from `ReviewAssignmentRepository::list_student_panels($review_id)` (panels with at least one student assigned on that review)  
   **And** panels with no students on that review are **ignored** (no freeze required).

2. **Given** zero participating panels (no student assignments yet)  
   **When** coordinator attempts lock  
   **Then** `400` `no_panels_for_review_lock` with message to assign students to panels first.

### 2. Lock (freeze review) — prerequisites and side effects

3. **Given** coordinator with `pr_manage_sessions` on Reports  
   **When** not all participating panels are frozen (`PanelFreezeRepository::is_frozen` for each)  
   **Then** **Lock / Freeze review** is disabled in UI with helper text naming unfrozen panels (panel display name from `PanelRepository`)  
   **And** `POST /sessions/{session_id}/reviews/{review_id}/lock-marks` returns `400` `panels_not_all_frozen` with `unfrozen_panels: [{ id, name }]`.

4. **Given** all participating panels frozen and review confirmed  
   **When** coordinator confirms **Freeze review** (rename from “Lock marks” acceptable)  
   **Then** `coordinator_marks_locked = 1` on `pr_reviews`  
   **And** `marking_active = 0` on the same row (atomic in one service method)  
   **And** audit `review_marks_locked` unchanged entity shape (add `marking_active_cleared: true` in JSON payload optional).

5. **Given** review already `coordinator_marks_locked`  
   **When** `POST lock-marks` again  
   **Then** idempotent `200` `{ coordinator_marks_locked: true }` (no duplicate audit).

6. **Given** frozen review  
   **When** reviewer attempts save, freeze personal scores, unfreeze request, panel freeze, mark override  
   **Then** existing `403` `coordinator_marks_locked` guards remain (5.6, 5.8, 7.5, 11.1).

### 3. Unlock — no approval

7. **Given** review with `coordinator_marks_locked`  
   **When** coordinator clicks **Unlock review** and confirms  
   **Then** `POST /sessions/{session_id}/reviews/{review_id}/unlock-marks` sets `coordinator_marks_locked = 0`  
   **And** audit `review_marks_unlocked` on entity `review` with `session_id`, `review_id`, actor  
   **And** **no** pending-request workflow (contrast `pr_panel_unfreeze_requests` in 5.15).

8. **Given** review not locked  
   **When** `POST unlock-marks`  
   **Then** idempotent success `{ coordinator_marks_locked: false }` or `400` `review_not_locked` — **prefer idempotent** for safe UI retries.

9. **Given** successful unlock and session still `active` with rubric `confirmed`  
   **Then** `marking_active` is set to `1` (re-open marking for the round)  
   **And** reviewers can save/freeze again subject to panel/personal freeze rules  
   **When** session `closed`  
   **Then** unlock may clear lock flag but must not set `marking_active` (session closed guard on mark paths unchanged).

### 4. Marking active — wizard and REST sync

10. **Given** `ReviewRoundsStep` loads reviews from `GET /sessions/{id}/reviews`  
    **When** `coordinator_marks_locked === true`  
    **Then** marking checkbox is **unchecked and disabled**  
    **And** label shows **Marks locked** with `StatusChip` (reuse `confirmed` or add neutral “Locked” variant)  
    **And** helper copy: coordinator froze this review on Reports; use **Unlock** there to reopen marking.

11. **Given** coordinator tries `PUT .../reviews/{id}` with `marking_active: true` while locked  
    **Then** `403` `coordinator_marks_locked` (server guard in `Rest_Reviews::update_review` or `ReviewRepository`).

12. **Given** coordinator unlocks on Reports  
    **When** wizard reloads  
    **Then** marking checkbox enabled again (subject to `status === confirmed`).

### 5. Attendance correction — respect review freeze

13. **Given** `coordinator_marks_locked` for the review  
    **When** coordinator calls `PUT .../students/{student_id}/attendance`  
    **Then** `403` `coordinator_marks_locked` (add guard at start of `MarkService::correct_attendance_by_coordinator`)  
    **And** **reverts** 5.17 AC that allowed correction while locked.

14. **Given** frozen review  
    **When** coordinator views **Correct attendance** entry points (`ScoreBreakdown`, `ReviewAssignmentsStep`)  
    **Then** control hidden or disabled with short reason “Review marks are frozen.”

### 6. Reports UI

15. **Given** Reports page for a review  
    **When** all participating panels frozen and review not locked  
    **Then** primary action **Freeze review** enabled (destructive + `ConfirmDialog`: reviewers cannot change marks or attendance until unlocked).

16. **Given** review locked  
    **Then** `StatusChip` **Marks locked**; **Freeze** hidden; **Unlock review** shown (secondary or primary, not destructive).

17. **Given** marks-grid / scores-matrix / review list payload  
    **Then** include readiness for UI:
    ```json
    {
      "coordinator_marks_locked": false,
      "review_lock_ready": false,
      "unfrozen_panels": [{ "id": 2, "name": "Panel B" }]
    }
    ```
    **And** `review_lock_ready === true` when all participating panels frozen and review not locked.

### 7. Regression

18. **And** panel freeze / personal freeze / panel unfreeze flows (5.15, 11.1) unchanged except lock prerequisite on coordinator freeze.  
19. **And** `composer test` + `npm run build` pass; extend `RestReportsTest`, `MarkServiceTest`, `RestMarksTest` for new guards and unlock route.

## Tasks / Subtasks

- [x] **PanelFreezeRepository:** `list_frozen_panel_ids(int $review_id): int[]`; optional `list_freeze_rows_for_review`
- [x] **Service:** `MarkService::participating_panel_ids(int $review_id): int[]`; `all_panels_frozen_for_review(int $review_id): bool|WP_Error`; extend `lock_review_marks()` — prerequisite check + `set_marking_active(false)`; add `unlock_review_marks()` — clear lock + `set_marking_active(true)` when session active
- [x] **ReviewRepository:** ensure `set_marking_active` used from service; no duplicate logic in REST
- [x] **REST:** `POST .../unlock-marks`; extend `lock_marks` errors; expose `review_lock_ready` + `unfrozen_panels` on `marks_grid`, `scores_matrix`, and/or `format_review`
- [x] **REST:** Block `marking_active` PUT when locked (`class-rest-reviews.php`)
- [x] **MarkService:** Guard `correct_attendance_by_coordinator` when locked
- [x] **Frontend Reports.jsx:** Freeze/Unlock buttons, readiness messaging, ConfirmDialog copy
- [x] **Frontend ReviewRoundsStep.jsx:** Locked state UI; disable marking toggle
- [x] **Frontend:** Hide/disable `CorrectAttendanceDialog` triggers when `coordinator_marks_locked`
- [x] **Errors:** `panels_not_all_frozen`, `no_panels_for_review_lock` in `markErrors.js` if surfaced to coordinator SPA
- [x] **Audit:** `review_marks_unlocked` label in `AuditLog.jsx`
- [x] **Tests:** lock blocked until panels frozen; lock clears marking_active; unlock restores; attendance 403 when locked; PUT marking_active 403 when locked

## Dev Notes

### User request (source)

> When I click lock scores for a review from the coordinator side, mark entering is disabled for reviewers — good, but: (1) wizard marking active still active, (2) still able to update attendance-consensus-correction, (4) no unlock. Implement freeze for the review if all panels have frozen; unfreeze without approval.

### Architecture — reuse, do not reinvent

| Concern | Existing | This story |
|---------|----------|------------|
| Review lock flag | `pr_reviews.coordinator_marks_locked` (7.5) | Same column; add gating + side effects |
| Panel freeze | `pr_review_panel_freezes` (11.1) | Prerequisite for coordinator freeze |
| Lock endpoint | `POST .../lock-marks` → `MarkService::lock_review_marks` | Add panel check + `marking_active` |
| Personal freeze | `submitted` marks per reviewer (5.6) | Unchanged |
| Panel unfreeze approval | `pr_panel_unfreeze_requests` (5.15) | **Not** used for review unlock |

### Lock readiness algorithm

```php
$panel_ids = array_unique(array_column(
    $assignments->list_student_panels($review_id),
    'panel_id'
));
$panel_ids = array_values(array_filter($panel_ids, fn ($id) => $id > 0));

foreach ($panel_ids as $panel_id) {
    if (!$freezes->is_frozen($review_id, $panel_id)) {
        $unfrozen[] = ['id' => $panel_id, 'name' => $panels->find_by_id($panel_id)['name'] ?? "Panel {$panel_id}"];
    }
}
$ready = $panel_ids !== [] && $unfrozen === [];
```

Mirror panel-freeze reviewer readiness only at **panel** level (panel heads already enforced personal freeze before panel freeze in `PanelReportService::panel_freeze_readiness_error`).

### `lock_review_marks` extension (sketch)

```php
$ready = $this->assert_all_panels_frozen_for_review_lock($review_id);
if ($ready instanceof \WP_Error) {
    return $ready;
}
// existing confirmed review checks...
$this->reviews->set_coordinator_marks_locked($review_id, true);
$this->reviews->set_marking_active($review_id, false);
// audit...
```

### `unlock_review_marks` (sketch)

```php
$this->reviews->set_coordinator_marks_locked($review_id, false);
if ($session active && review confirmed) {
    $this->reviews->set_marking_active($review_id, true);
}
// audit review_marks_unlocked
```

### API additions

| Method | Path | Response |
|--------|------|----------|
| POST | `/sessions/{session_id}/reviews/{review_id}/unlock-marks` | `{ coordinator_marks_locked: false, marking_active: bool }` |
| POST | `/sessions/{session_id}/reviews/{review_id}/lock-marks` | unchanged key; may return `panels_not_all_frozen` |

Extend **`GET marks-grid`** (and scores-matrix if Reports uses it for lock button state) with `review_lock_ready`, `unfrozen_panels`. `format_review` already exposes `coordinator_marks_locked` — add same readiness fields for wizard if Reports not loaded.

### UI copy (coordinator)

| State | Reports action | Wizard |
|-------|----------------|--------|
| Panels not all frozen | Freeze disabled — “Freeze each panel’s scores first (Panel report).” | — |
| Ready | **Freeze review** | Marking active toggle as today |
| Locked | **Unlock review** | **Marks locked** (disabled) |

### Files to touch (expected)

| Area | File |
|------|------|
| Repo | `includes/repositories/PanelFreezeRepository.php`, `ReviewRepository.php` |
| Service | `includes/services/MarkService.php`, `includes/services/ReportsViewService.php` |
| REST | `includes/rest/class-rest-reports.php`, `includes/rest/class-rest-reviews.php` |
| UI | `src/coordinator/pages/Reports.jsx`, `src/coordinator/components/ReviewRoundsStep.jsx` |
| UI | `src/coordinator/components/ScoreBreakdown.jsx`, `ReviewAssignmentsStep.jsx`, `CorrectAttendanceDialog.jsx` |
| Errors | `src/shared/markErrors.js` |
| Audit | `src/coordinator/pages/AuditLog.jsx` |
| Tests | `tests/MarkServiceTest.php`, `tests/RestReportsTest.php`, `tests/RestMarksTest.php`, `tests/PanelReportServiceTest.php` (regression) |

### Previous story intelligence

| Story | Learning |
|-------|----------|
| 7.5 | Shipped lock without unlock, without `marking_active` sync, without panel prerequisite — **this story closes those gaps** |
| 5.17 | Attendance correction explicitly bypassed lock — **remove that bypass** (AC13) |
| 5.15 | Panel unfreeze needs coordinator approval — review unlock is **direct** only |
| 11.1 | Panel freeze requires all reviewers personal-frozen — coordinator review freeze stacks **on top** |
| 3.11 | `marking_active` gates reviewer login — must align with lock |

### Regression guards

- Do not delete `pr_review_panel_freezes` on review unlock (panel-level freeze remains; reviewers still blocked by panel freeze until panel unfrozen per 5.15).
- Session `closed` still blocks all mutations.
- Coordinator lock still blocks `grant_unfreeze` for reviewer marks (7.5).
- Reports exports and live tables read-only unaffected.

### Out of scope

- Reviewer-initiated request to unlock review (coordinator-only).
- Auto-lock review when last panel freezes.
- Email notifications.
- Renaming DB column `coordinator_marks_locked`.

### References

- [Source: `_bmad-output/implementation/7-5-reports-page-live-views-and-lock.md`]
- [Source: `_bmad-output/implementation/5-17-attendance-consensus-correction.md` — attendance bypass to remove]
- [Source: `_bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md` — panel freeze]
- [Source: `includes/services/MarkService.php` — `lock_review_marks`, `correct_attendance_by_coordinator`]
- [Source: `includes/services/PanelReportService.php` — `panel_freeze_readiness_error`]
- [Source: `src/coordinator/components/ReviewRoundsStep.jsx` — marking_active toggle]
- [Source: `src/coordinator/pages/Reports.jsx` — lock UI]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

- `./vendor/bin/phpunit` — 246 tests OK
- `npm run build` — success

### Completion Notes List

- Coordinator review freeze requires every participating panel (`list_student_panels`) to have a panel freeze row before `POST lock-marks` succeeds; returns `panels_not_all_frozen` / `no_panels_for_review_lock` with `unfrozen_panels` payload.
- Lock sets `coordinator_marks_locked` and clears `marking_active`; unlock clears lock and restores `marking_active` when session is active and review confirmed (idempotent unlock).
- `POST .../unlock-marks` added; marks-grid/scores-matrix and `format_review` expose `review_lock_ready` + `unfrozen_panels`.
- Attendance correction and wizard `marking_active` PUT blocked while frozen; Reports UI shows Freeze/Unlock with readiness helper text; wizard shows Marks locked chip.

### File List

- includes/repositories/PanelFreezeRepository.php
- includes/services/MarkService.php
- includes/services/ReportsViewService.php
- includes/rest/class-rest-reports.php
- includes/rest/class-rest-reviews.php
- src/coordinator/pages/Reports.jsx
- src/coordinator/components/ReviewRoundsStep.jsx
- src/coordinator/components/ReviewAssignmentsStep.jsx
- src/coordinator/pages/AuditLog.jsx
- src/shared/markErrors.js
- tests/MarkServiceTest.php
- tests/RestReportsTest.php
- tests/RestMarksTest.php
- tests/RestReviewsTest.php

### Change Log

- 2026-05-17: Story 5.18 — coordinator review freeze when all panels frozen; unlock; marking_active sync; block attendance when locked.
