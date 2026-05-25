# Story 5.17: Coordinator attendance correction when panel consensus blocks change

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **project coordinator**,
I want to correct a student’s review-level attendance when all panel reviewers have recorded the same (wrong) status,
So that a mistaken unanimous “Present” (or “Absent”) can be fixed without every reviewer having to change their score form in lockstep.

As a **reviewer** who sees `attendance_conflict`,
I want clear guidance when every peer already agrees on a status I believe is wrong,
So that I know to ask the project coordinator instead of retrying the same save.

## Acceptance Criteria

1. **Problem recap (why this story exists)**
   - **Given** story 5.14 enforces panel consensus on every reviewer mark save
   - **When** every panel reviewer has asserted `present` (canonical and all rows in `pr_review_student_attendance_by_reviewer`)
   - **And** one reviewer tries to save with `absent`
   - **Then** save fails with `attendance_conflict` and nothing changes (by design)
   - **And** there is currently **no** role that can break unanimous consensus — coordinators were explicitly out of scope in 5.14

2. **Coordinator correction endpoint (server)**
   - **Given** a user with `pr_manage_sessions` for the project
   - **When** they `PUT` (or `POST`) `/sessions/{session_id}/reviews/{review_id}/students/{student_id}/attendance` with body `{ "attendance_status": "present" | "absent", "reason": "..." }`
   - **Then** canonical `pr_review_student_panels.attendance_status` is set to the new value
   - **And** for **every** reviewer on that student’s panel for that review, `pr_review_student_attendance_by_reviewer` is upserted to the **same** new status (panel-scoped via `get_student_panel` → `list_panel_reviewers_for_panel`)
   - **And** reviewers can subsequently save marks without `attendance_conflict` until someone diverges again
   - **When** `reason` is missing or shorter than 10 characters (match mark override UX-DR21)
   - **Then** `400` `reason_required` (or reuse override validation pattern)
   - **When** session is `closed`
   - **Then** `403` or `session_closed` (same as mark saves)
   - **When** student is not assigned to a panel on that review
   - **Then** `404` or `not_assigned`
   - **And** correction is allowed even when `coordinator_marks_locked` is true for that review (attendance fix is operational, not rubric scoring)

3. **Mark side-effects when correcting to absent**
   - **Given** correction sets attendance to `absent`
   - **When** the coordinator correction succeeds
   - **Then** for **each** panel reviewer on that student, all criterion marks for that `session_id` + `review_id` + `student_id` are set to `score = NULL` with existing draft/submitted rules mirrored from `MarkService::save_marks` absent branch (do not leave stale numeric scores)
   - **And** freeze/progress/reports continue to read canonical absent per 5.7

4. **Mark side-effects when correcting to present**
   - **Given** correction sets attendance to `present`
   - **When** the coordinator correction succeeds
   - **Then** canonical and assertions update only — **do not** auto-fill scores
   - **And** reviewers may enter scores on next save as today

5. **Audit trail**
   - **Given** a successful coordinator correction
   - **Then** `AuditService::log()` records action `attendance_correction`, entity `review_student` (or `session` with JSON payload), including `old_value` / `new_value` status and reason text, `actor_user_id`
   - **And** audit appears in session audit log UI (extend filter labels if needed)

6. **Coordinator UI**
   - **Given** coordinator on Session Progress (`SessionProgress.jsx`) or score breakdown for a student
   - **When** viewing a review round row that includes `attendance_status`
   - **Then** an **Correct attendance** control is available (secondary `Button` or link) opening `ConfirmDialog`
   - **And** dialog explains: updates attendance for all panel reviewers; if setting Absent, clears scores for all reviewers on that student for that review
   - **And** required reason textarea (min 10 chars, `aria-required`)
   - **And** Present / Absent choice matches reviewer labels
   - **On** success: refresh progress/breakdown; `Notice` success
   - **On** error: mapped `Notice`

7. **Reviewer UX when blocked by unanimous peers**
   - **Given** `RubricForm` receives `attendance_conflict`
   - **When** every **other** peer in `conflicts` shares one status and the reviewer’s attempted status is the minority (exactly one reviewer differs — the current user)
   - **Then** append helper copy: “All other reviewers recorded {Present|Absent}. Ask the project coordinator to correct attendance if this is wrong.”
   - **And** keep existing per-reviewer conflict list from 5.14
   - **When** true disagreement (multiple statuses among peers), keep today’s message only

8. **Out of scope**
   - Panel-head-only correction without coordinator cap (defer; coordinator is sufficient for MVP)
   - Reviewer-initiated attendance change request queue (like unfreeze) — use coordinator correction instead
   - Changing attendance after session export PDF freeze beyond normal session closed rules

9. **Tests and build**
   - **And** `MarkServiceTest` or dedicated service test: two reviewers both `present` → third attempt absent fails → coordinator correction to `absent` → reviewer save absent succeeds; assertions all `absent`
   - **And** correction to absent nulls all reviewers’ scores
   - **And** REST test for endpoint auth (reviewer `403`, coordinator `200`) and `reason_required`
   - **And** `composer test` and `npm run build`

## Tasks / Subtasks

- [x] **Repository:** `ReviewAssignmentRepository::sync_panel_attendance_assertions(int $review_id, int $student_id, int $panel_id, string $status)` — upsert for each `list_panel_reviewers_for_panel` user_id
- [x] **Service:** `MarkService::correct_attendance_by_coordinator(...)` — guards, set canonical, sync assertions, absent → null marks per reviewer, audit log; **bypass** `validate_attendance_consensus` (do not call reviewer save path)
- [x] **REST:** New route on `Rest_Sessions` or `Rest_Reviews` / `Rest_Marks` coordinator namespace; register in `class-rest-bootstrap.php`; capability `pr_manage_sessions`
- [x] **Frontend:** Session Progress and/or `ScoreBreakdown` — Correct attendance dialog; API helper in `src/shared/api.js` if needed
- [x] **Frontend:** `RubricForm.jsx` unanimous-conflict helper text (use `conflicts` + current attempted status)
- [x] **Audit:** `attendance_correction` label in `AuditLog.jsx` if action filter is enumerated
- [x] **Tests:** PHPUnit + build

## Dev Notes

### User request (source)

> While updating the attendance, there is a conflict. How to change the attendance for a student if all the reviewers have marked present?

This is the expected outcome of story **5.14**: consensus blocks a lone dissenting save. The gap is an **authorized correction** path when the whole panel is wrong, not a bug in conflict detection.

### Current behaviour (do not break)

```1479:1513:includes/services/MarkService.php
    private function validate_attendance_consensus(
        ...
        foreach ($status_by_reviewer as $peer_id => $peer_status) {
            if ($peer_id === $reviewer_user_id) {
                continue;
            }
            if ($peer_status !== $attendance_status) {
                return $this->attendance_conflict_error($panel_id, $status_by_reviewer);
            }
        }
        return null;
    }
```

Self-correction (5.14 AC5) only helps when **peers already match your new value** — e.g. everyone else absent, you fix your assertion to absent. It does **not** help when everyone is `present` and you need `absent`.

### Recommended correction flow

```text
PUT .../students/{student_id}/attendance
{ "attendance_status": "absent", "reason": "Student did not attend oral review." }

→ validate session open, assignment exists, reason length
→ panel_id = resolve_student_panel_id(...)
→ assignments.set_attendance_status(review_id, student_id, absent)
→ assignments.sync_panel_attendance_assertions(review_id, student_id, panel_id, absent)
→ foreach reviewer in panel:
      marks: null all criteria for session/review/student/reviewer (reuse private helper from save_marks absent branch)
→ AuditService::log('attendance_correction', ...)
```

**Do not** delete assertion rows only — that would leave peers inferred as `present` via `panel_attendance_status_map` canonical fallback (`reviewer_has_mark_activity`).

### REST sketch

```json
// Request
{ "attendance_status": "absent", "reason": "Verified with panel chair: student was ill." }

// Response 200
{
  "attendance_status": "absent",
  "review_id": 3,
  "student_id": 12,
  "panel_id": 2,
  "reviewers_updated": 3
}
```

### UI placement

| Surface | Rationale |
|---------|-----------|
| `SessionProgress.jsx` + student score breakdown | Coordinator already diagnoses per-student per-review state |
| Optional: Reports marks row | Lower priority; progress is enough for MVP |

Reuse `ConfirmDialog` + reason textarea pattern from mark override / unfreeze (9.2, 5.8).

### Reviewer helper (unanimous conflict)

In `RubricForm` error block, after mapping `attendance_conflict`:

```javascript
const others = ( error.conflicts ?? [] ).filter(
  ( row ) => row.reviewer_user_id !== currentUserId
);
const otherStatuses = new Set( others.map( ( r ) => r.attendance_status ) );
const unanimous =
  otherStatuses.size === 1 &&
  others.length > 0 &&
  ![ ...otherStatuses ][ 0 ] === attemptedAttendance;
// → show coordinator guidance Notice
```

Expose `currentUserId` from `window.projectReviews?.userId` or existing reviewer bootstrap if already available; otherwise compare by excluding the row matching attempted status when `conflicts.length === panel reviewer count` and all others share one status.

### Files to touch (expected)

| Area | File |
|------|------|
| Repo | `includes/repositories/ReviewAssignmentRepository.php` |
| Service | `includes/services/MarkService.php` |
| REST | `includes/rest/class-rest-sessions.php` or new `class-rest-review-attendance.php` |
| Bootstrap | `includes/rest/class-rest-bootstrap.php` |
| UI | `src/coordinator/pages/SessionProgress.jsx`, `src/coordinator/components/ScoreBreakdown.jsx` (if breakdown shows attendance) |
| Reviewer | `src/reviewer/components/RubricForm.jsx` |
| Audit | `src/coordinator/pages/AuditLog.jsx` (optional label) |
| Tests | `tests/MarkServiceTest.php`, new `tests/RestReviewAttendanceTest.php` |

### Regression guards

- Reviewer consensus on normal saves unchanged (5.14).
- Do not allow reviewers to call coordinator correction endpoint.
- Absent freeze completeness (5.7) still uses canonical only.
- Panel freeze / personal freeze do not block coordinator correction unless product explicitly adds that guard later.

### Previous story intelligence

| Story | Learning |
|-------|----------|
| 5.7 | Canonical on `pr_review_student_panels`; absent → null scores per **current** reviewer on save |
| 5.14 | Assertions table + `attendance_conflict`; coordinator override deferred — **this story delivers it** |
| 5.15 | Panel head vs project coordinator authority chains — attendance correction stays **project coordinator** (`pr_manage_sessions`) |
| 9.2 | Reason ≥ 10 chars for audited changes |

### References

- [Source: `_bmad-output/implementation/5-14-review-attendance-consensus-validation.md`]
- [Source: `_bmad-output/implementation/5-7-student-attendance-marking.md`]
- [Source: `includes/services/MarkService.php` — `validate_attendance_consensus`, absent branch in `save_marks`]
- [Source: `includes/repositories/ReviewAssignmentRepository.php` — `upsert_reviewer_attendance_assertion`, `set_attendance_status`]
- [Source: `src/reviewer/components/RubricForm.jsx` — conflict list UI]
- [Source: `_bmad-output/planning/epics.md` — Epic 5, FR15/FR16; Epic 9 audit]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

- FakeWpdb `get_row` matched a 2-column `SELECT id` before the 3-column attendance assertion lookup, so panel sync only kept one assertion row in tests; reordered patterns.

### Completion Notes List

- Added `PUT /sessions/{session_id}/reviews/{review_id}/students/{student_id}/attendance` for coordinators (`pr_manage_sessions`) with mandatory reason (≥10 chars).
- `MarkService::correct_attendance_by_coordinator` sets canonical attendance, syncs all panel reviewer assertions, nulls all reviewers’ scores when correcting to absent (preserving draft/submitted per criterion), and logs `attendance_correction` on the session audit trail. Bypasses consensus validation and coordinator marks lock.
- Score breakdown shows per-review attendance with **Correct attendance** dialog on Session Progress.
- Reviewers see coordinator guidance on unanimous `attendance_conflict` when all peers share one status.
- PHPUnit: 233 tests OK; `npm run build` OK.

### File List

- includes/repositories/ReviewAssignmentRepository.php
- includes/services/MarkService.php
- includes/services/ScoreService.php
- includes/rest/class-rest-marks.php
- src/coordinator/components/CorrectAttendanceDialog.jsx
- src/coordinator/components/ScoreBreakdown.jsx
- src/coordinator/pages/SessionProgress.jsx
- src/coordinator/pages/AuditLog.jsx
- src/reviewer/components/RubricForm.jsx
- src/shared/markErrors.js
- tests/MarkServiceTest.php
- tests/RestMarksTest.php
- tests/FakeWpdb.php

### Change Log

- 2026-05-17: Coordinator attendance correction when panel consensus blocks change (story 5.17).
