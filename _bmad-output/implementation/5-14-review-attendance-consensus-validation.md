# Story 5.14: Review-level attendance consensus on score update

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer** updating scores for a student on a review round,
I want the system to block my save when my attendance choice disagrees with other panel reviewers on that review,
So that attendance is one canonical fact per student per review and committees are not given conflicting present/absent signals.

## Acceptance Criteria

1. **Canonical attendance remains review-level (student × review)**
   - **Given** story 5.7 stored attendance on `pr_review_student_panels.attendance_status`
   - **When** all assigned panel reviewers agree on `present` or `absent` for a student on that review
   - **Then** that single value remains the canonical attendance used by freeze, progress, reports, and `ScoreService` (unchanged consumers)
   - **And** reviewers still POST `attendance_status` on mark save (`RubricForm` → **Update score**); the server enforces consensus before updating canonical storage

2. **Per-reviewer assertions tracked for conflict detection**
   - **Given** a reviewer saves marks for `session_id` + `review_id` + `student_id`
   - **When** the save succeeds
   - **Then** the server records that reviewer’s asserted `attendance_status` (`present` | `absent`) for that review + student (upsert by reviewer)
   - **And** canonical `pr_review_student_panels.attendance_status` is set to the agreed value when there is no conflict among recorded assertions on that student’s panel for that review
   - **And** scope is **panel peers only**: reviewers on the **same** `panel_id` for that student on that review (`ReviewAssignmentRepository::list_panel_reviewers_for_panel` or equivalent), not reviewers on other panels

3. **Reject save on attendance conflict (server)**
   - **Given** at least one **other** panel reviewer has already asserted attendance for this student on this review
   - **When** the current reviewer POSTs marks with a different `attendance_status`
   - **Then** `MarkService::save_marks()` returns `400` with code `attendance_conflict`
   - **And** the error message states that attendance must match across reviewers for this review
   - **And** `error.data.conflicts` is a non-empty array of `{ reviewer_user_id, reviewer_name, attendance_status }` listing **every** reviewer on that panel who has an assertion, including the current user’s attempted status vs others (so the UI can show who said present vs absent)
   - **And** canonical attendance and marks are **not** updated on conflict (transactional: validate → fail fast before `persist_attendance_status` / mark upserts)

4. **Reviewer UI — Update score modal**
   - **Given** `RubricForm` inside `ScoreEntryModal` (**Update score**)
   - **When** save returns `attendance_conflict`
   - **Then** `mapMarkApiError` surfaces a clear `Notice` with the server message
   - **And** a readable list of reviewer names and statuses (e.g. “Alex Chen — Present”, “Jordan Lee — Absent”) from `conflicts` in the error payload
   - **And** the reviewer can change their attendance radio to match peers and retry, or coordinate offline (no coordinator override in this story)

5. **Agreement and first-writer behaviour**
   - **Given** no other panel reviewer has asserted attendance yet for this student on this review
   - **When** the current reviewer saves with `present` or `absent`
   - **Then** save succeeds, assertion is stored, canonical attendance is set to that value
   - **Given** another reviewer already asserted the **same** status
   - **When** the current reviewer saves with that same status
   - **Then** save succeeds and canonical remains that status
   - **Given** the current reviewer changes their own assertion from `present` to `absent` (or reverse) while all **other** assertions already match the **new** value
   - **When** they save
   - **Then** save succeeds (self-correction allowed when aligned with peers)

6. **Freeze, progress, reports unchanged**
   - **Given** freeze (`freeze_review_marks`), progress, reports, `ScoreService`
   - **When** they read attendance
   - **Then** they continue to use canonical `get_attendance_status()` only — no change to completion rules from 5.7
   - **And** freeze may still fail on incomplete **present** students; absent students remain complete per 5.7

7. **Tests and build**
   - **And** `MarkServiceTest`: two reviewers, first present, second absent → `attendance_conflict` with both names; second saves present → success; canonical `present`
   - **And** `RestMarksTest`: POST marks returns `attendance_conflict` JSON shape
   - **And** optional: solo reviewer save still works
   - **And** run `composer test` and `npm run build`

## Tasks / Subtasks

- [x] **Schema:** Add `pr_review_student_attendance_by_reviewer` (or equivalent name) with `review_id`, `student_id`, `reviewer_user_id`, `attendance_status`, `updated_at`; unique `(review_id, student_id, reviewer_user_id)`; `Install::ensure_*` patch + schema tests
- [x] **Repository:** `ReviewAssignmentRepository` (or dedicated small repo) — `upsert_reviewer_attendance_assertion`, `list_attendance_assertions_for_panel_student`
- [x] **MarkService:** `validate_attendance_consensus()` before `persist_attendance_status`; on success upsert assertion + set canonical; resolve `reviewer_name` via `PanelRepository::display_name_for_user($panel_id, $user_id)` using student’s panel on that review
- [x] **REST:** No new route required if error data passes through existing `Rest_Marks::save_marks` WP_Error envelope
- [x] **Frontend:** `markErrors.js` — `attendance_conflict` mapping; `RubricForm.jsx` — render conflict list from `error.data.conflicts`
- [x] **Tests:** PHPUnit as in AC 7
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

> Update score needs validation check on the attendance. If any of the reviewers, for the current review, marked the attendance of the student differently, then it should alert which reviewer have marked the status = present/absent. Only the same attendance status to be allowed per review for a student, for all the students. Treat the attendance status at the review level.

### Problem today (5.7 gap)

Story 5.7 stores **one** `attendance_status` on `pr_review_student_panels`, but **each** reviewer overwrites it on every `save_marks()` without checking peers:

```91:99:includes/services/MarkService.php
        $persist_attendance = $this->persist_attendance_status(
            $session_id,
            $review_id,
            $student_id,
            $attendance_status
        );
```

Last writer wins — coordinators and other reviewers can see a flip with no warning. This story adds **consensus validation** on the **Update score** save path (`RubricForm` draft save).

### Data model (recommended)

| Store | Purpose |
|-------|---------|
| `pr_review_student_panels.attendance_status` | Canonical review-level truth (existing) |
| `pr_review_student_attendance_by_reviewer` | Each panel reviewer’s last asserted status for conflict checks |

**Do not** put attendance on `pr_marks` rows (5.7 explicitly rejected redundant per-mark attendance).

Example assertion table:

```sql
CREATE TABLE {prefix}pr_review_student_attendance_by_reviewer (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  review_id bigint unsigned NOT NULL,
  student_id bigint unsigned NOT NULL,
  reviewer_user_id bigint unsigned NOT NULL,
  attendance_status varchar(16) NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY review_student_reviewer (review_id, student_id, reviewer_user_id)
);
```

Constants: reuse `ReviewAssignmentRepository::ATTENDANCE_PRESENT` / `ATTENDANCE_ABSENT`.

### Server flow (pseudocode)

```text
save_marks(..., attendance_status):
  validate_attendance_status(attendance_status)
  panel_id = resolve student panel for review (existing persist_attendance path)
  peers = list_attendance_assertions_for_panel_student(review_id, student_id, panel_id)
  conflicts = peers where status != attendance_status AND reviewer_user_id != current
  if conflicts not empty:
    return WP_Error('attendance_conflict', message, { conflicts: [...names...] })
  upsert_reviewer_attendance_assertion(review_id, student_id, current, attendance_status)
  persist_attendance_status(...)  // sets canonical
  ... existing present/absent mark branches ...
```

**Panel scope:** Only reviewers assigned to the student’s panel for that review (`pr_review_panel_reviewers` filtered by `panel_id` from `get_student_panel`). Cross-panel disagreement is out of scope (different physical panels).

**Backfill:** On deploy, assertions table may be empty. No automatic backfill required — conflicts appear only after two reviewers have saved post-release. Optional one-time backfill from canonical + mark rows is **not** required for MVP.

### REST error contract

```json
{
  "code": "attendance_conflict",
  "message": "Attendance must match for all reviewers on this review. Resolve the disagreement before saving.",
  "data": {
    "status": 400,
    "conflicts": [
      { "reviewer_user_id": 12, "reviewer_name": "Alex Chen", "attendance_status": "present" },
      { "reviewer_user_id": 34, "reviewer_name": "Jordan Lee", "attendance_status": "absent" }
    ]
  }
}
```

Include **all** panel reviewers with assertions (and optionally the attempted status for the current user in the message body). Sort `conflicts` by `reviewer_name` case-insensitive for stable UI.

### Frontend (`RubricForm.jsx`)

- Save path already calls `post(.../marks, { attendance_status, ... })`.
- On `attendance_conflict`, extend `mapMarkApiError` or local handler to append bullet list from `err.data?.conflicts`.
- Do **not** block opening the modal — only block save.
- Optional enhancement (not required): on load, GET could expose `attendance_assertions` for warning banner — skip unless trivial.

### Files to touch (expected)

| Area | File |
|------|------|
| Schema | `includes/Install.php`, `tests/InstallSchemaTest.php`, `tests/InstallSchemaPatchTest.php` |
| Repo | `includes/repositories/ReviewAssignmentRepository.php` (or new repo file) |
| Service | `includes/services/MarkService.php` |
| REST | `includes/rest/class-rest-marks.php` (only if error data needs shaping) |
| UI | `src/reviewer/components/RubricForm.jsx`, `src/shared/markErrors.js` |
| Tests | `tests/MarkServiceTest.php`, `tests/RestMarksTest.php` |

### Regression guards

- Do **not** change absent → null marks behaviour (5.7).
- Do **not** add coordinator attendance override UI.
- Do **not** change `MARK_SCORE_STEP` / half-point validation (5.10).
- `coordinator_marks_locked`, panel freeze, unfreeze flows unchanged.

### Previous story intelligence (5.7)

- Attendance required on every mark POST; `attendance_required` / `invalid_attendance` already mapped in `markErrors.js`.
- Absent: all criteria `score = NULL` for **current reviewer** only.
- Freeze skips numeric completeness for canonical absent students.
- 5.7 doc said “separate attendance per reviewer” was out of scope for **storage** — this story adds **assertion** rows for consensus only; canonical remains one column.

### References

- [Source: _bmad-output/implementation/5-7-student-attendance-marking.md]
- [Source: includes/services/MarkService.php — `save_marks`, `persist_attendance_status`]
- [Source: includes/repositories/ReviewAssignmentRepository.php — attendance constants]
- [Source: src/reviewer/components/RubricForm.jsx — Update score save]
- [Source: includes/repositories/PanelRepository.php — `display_name_for_user`]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Added `pr_review_student_attendance_by_reviewer` table and `ensure_review_student_attendance_by_reviewer_table()` schema patch.
- `MarkService::validate_attendance_consensus()` blocks save when another panel reviewer asserted a different status; returns `attendance_conflict` with sorted `conflicts` (names via `PanelRepository::display_name_for_user`).
- On success: upserts per-reviewer assertion then sets canonical `attendance_status` (unchanged consumers).
- `RubricForm` shows reviewer name + Present/Absent list on conflict; `mapMarkApiError` passes through `conflicts`.
- Tests: `MarkServiceTest::test_attendance_conflict_when_panel_reviewers_disagree`, `RestMarksTest::test_post_marks_returns_attendance_conflict_shape`, schema tests.
- `vendor/bin/phpunit` — 203 tests OK; `npm run build` OK.

### File List

- includes/Install.php
- includes/repositories/ReviewAssignmentRepository.php
- includes/services/MarkService.php
- src/shared/markErrors.js
- src/reviewer/components/RubricForm.jsx
- tests/FakeWpdb.php
- tests/InstallSchemaTest.php
- tests/InstallSchemaPatchTest.php
- tests/MarkServiceTest.php
- tests/RestMarksTest.php
- build/reviewer.js
- build/reviewer.asset.php

### Change Log

- 2026-05-17: Review-level attendance consensus validation on mark save (story 5-14).
