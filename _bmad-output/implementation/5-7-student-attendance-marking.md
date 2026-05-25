# Story 5.7: Per-review student attendance (present / absent)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want to record whether each assigned student was present or absent for a review round,
So that absent students have no rubric scores, freeze validation treats them as complete, and coordinators see accurate reporting.

## Acceptance Criteria

1. **Attendance is required per student per review**
   - **Given** a student is assigned to a review round (`pr_review_student_panels` row exists, or falls back to session enrolment panel for that review)
   - **When** a reviewer saves marking data for that student on that review
   - **Then** `attendance_status` must be exactly `present` or `absent` (no other values)
   - **And** existing rows without a value are backfilled to `present` on schema upgrade (backward compatible)
   - **And** API rejects saves without attendance with `400` code `attendance_required`

2. **Absent students have null scores**
   - **Given** `attendance_status` is `absent`
   - **When** marks are saved (draft or any future path)
   - **Then** all criterion rows for that `session_id` + `review_id` + `student_id` + **current reviewer** are persisted with `score = NULL` (not zero)
   - **And** any previously entered numeric scores for that reviewer are cleared when switching from `present` → `absent`
   - **And** `ScoreService` continues to skip null scores (no change to weight math — absent reviewers contribute no criterion weight for that student)

3. **Present students behave as today**
   - **Given** `attendance_status` is `present`
   - **When** the reviewer saves draft scores
   - **Then** existing partial-save and max_marks validation apply unchanged
   - **And** at least one criterion score is still required for a non-empty draft save (current `RubricForm` rule) unless product later allows empty present rows

4. **Reviewer UI — grid and modal form**
   - **Given** the marking grid (`MarkingGrid.jsx`) for an assignment
   - **When** data loads
   - **Then** each student row includes an attendance indicator (e.g. `StatusChip` “Present” / “Absent”, or compact column after reg no)
   - **And** criterion cells show em dash (`—`) when absent, regardless of stale client cache
   - **Given** the score entry modal (`RubricForm.jsx` inside `ScoreEntryModal.jsx`)
   - **When** opened for a student
   - **Then** a required **Attendance** control appears above rubric fields: radio group or select with `Present` and `Absent`
   - **When** **Absent** is selected
   - **Then** all criterion inputs are disabled and visually de-emphasized; Save persists attendance + null scores
   - **When** **Present** is selected
   - **Then** criterion inputs are enabled and validation runs as today
   - **And** Save sends `attendance_status` in the marks POST body together with criteria (or attendance-only payload when absent)

5. **Freeze treats absent students as complete**
   - **Given** the reviewer clicks **Freeze scores** for a panel/review
   - **When** `MarkService::freeze_review_marks()` validates completeness
   - **Then** students with `attendance_status = absent` are counted as complete **without** numeric scores for every criterion
   - **And** students with `attendance_status = present` still require a valid numeric score per criterion (current `student_marks_complete()` behavior)
   - **And** after freeze, absent students still have `submitted` mark rows with `score = NULL` if the product requires submitted rows for audit — **or** no mark rows at all; **choose one** in implementation (recommended: upsert all criteria as `submitted` + `NULL` on freeze for absent students so progress counts stay consistent)

6. **REST contract**
   - **GET** `.../reviewer/assignments/{session}/{review}/{panel}/students` — each student includes `attendance_status: "present" | "absent"`
   - **GET** `.../sessions/{session}/reviews/{review}/students/{student}/marks` — response includes `attendance_status` for coordinator/reviewer reads
   - **POST** `.../marks` — body accepts `attendance_status` plus existing `status` + `criteria`; server is source of truth
   - **And** PHPUnit coverage in `MarkServiceTest` and REST tests (extend `RestReviewerAssignmentsTest` / `RestMarksTest`)

7. **Regression / non-goals**
   - **And** coordinator rubric builder, session wizard panel assignment UI, and exports are unchanged except they may **read** attendance if already in assignment rows (export column is optional follow-up — do not block this story)
   - **Out of scope:** coordinator editing attendance after freeze, attendance on session enrolment (only per review), separate attendance per reviewer (attendance is per student per **review**, shared across reviewers on that review)

## Tasks / Subtasks

- [x] **Schema:** Add `attendance_status` to `pr_review_student_panels` in `Install::table_review_student_panels()`; `ensure_attendance_status_column()` patch defaulting existing rows to `present`; mirror in `InstallSchemaPatchTest` / `InstallSchemaTest`
- [x] **Repository:** `ReviewAssignmentRepository::set_attendance_status()`, read via `get_student_panel()`; preserve on `set_student_panel()` panel-only updates; copy on `copy_from_review()`
- [x] **MarkService:** Accept `attendance_status` on `save_marks()`; validate; branch absent → clear/null all criteria for reviewer; integrate with frozen guard; update `student_marks_complete()` to check assignment attendance
- [x] **Freeze:** Update `freeze_review_marks()` loop — absent students skip numeric completeness; optionally bulk-submit null marks on freeze
- [x] **REST:** `Rest_Marks::save_marks()` pass attendance; `Rest_Reviewer_Assignments::list_students()` include field; `get_marks()` include field
- [x] **Frontend:** `RubricForm.jsx` attendance control + disabled criteria when absent; `MarkingGrid.jsx` column/chip; `markValidation.js` helper `validateAttendanceForSave()`; no score validation when absent
- [x] **Errors:** `attendance_required`, `invalid_attendance` in `src/shared/markErrors.js`
- [x] **Tests:** MarkService absent save clears scores; freeze with mix present/absent; REST POST without attendance → 400; seed SQL if needed
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

For each student in the review, attendance status is required: **present** or **absent**. Score should be **null** for absent students. The score entry form must include and update the attendance field.

### Why `pr_review_student_panels` (not `pr_marks`)

| Option | Pros | Cons |
|--------|------|------|
| Column on `pr_review_student_panels` | One value per student per review; matches “were they at the review event?”; all reviewers see same attendance | Must join assignment row in MarkService |
| Column on each `pr_marks` row | Local to mark grain | Redundant; reviewers could disagree |

**Use `pr_review_student_panels.attendance_status`** (`varchar(16) NOT NULL DEFAULT 'present'`, check in PHP).

### Storage constants (PHP)

```php
// ReviewAssignmentRepository or small Attendance value object
public const ATTENDANCE_PRESENT = 'present';
public const ATTENDANCE_ABSENT  = 'absent';
```

### Mark save flow (server)

```text
POST .../marks
{ "status": "draft", "attendance_status": "absent", "criteria": [] }

→ validate assignment + not frozen
→ set attendance on review_student_panels
→ if absent: foreach rubric criterion → upsert mark(score=null, status=draft)
→ if present: existing criteria loop + validation
```

When switching **absent → present**, do not auto-fill scores; leave null until reviewer enters values.

### Freeze completeness (pseudocode)

```php
foreach ($student_ids as $student_id) {
    $attendance = $assignments->get_attendance_status($review_id, $student_id);
    if ($attendance === 'absent') {
        continue; // complete
    }
    if (!$this->student_marks_complete(...)) {
        ++$incomplete;
    }
}
```

### API response sketch

**GET** `.../students` student object:

```json
{
  "id": 5,
  "reg_no": "S001",
  "name": "Ada",
  "attendance_status": "absent",
  "mark_status": "draft",
  "scores": { "1": null, "2": null },
  "flagged": { "1": false, "2": false }
}
```

**POST** marks body:

```json
{
  "status": "draft",
  "attendance_status": "present",
  "criteria": [{ "criterion_id": 1, "score": 8 }]
}
```

### Critical files

| Layer | File |
|-------|------|
| Schema | `includes/Install.php` |
| Repo | `includes/repositories/ReviewAssignmentRepository.php` |
| Service | `includes/services/MarkService.php` |
| REST | `includes/rest/class-rest-marks.php`, `includes/rest/class-rest-reviewer-assignments.php` |
| UI | `src/reviewer/components/RubricForm.jsx`, `MarkingGrid.jsx`, `ScoreEntryModal.jsx` |
| Shared | `src/shared/markValidation.js`, `src/shared/markErrors.js` |
| Tests | `tests/MarkServiceTest.php`, `tests/RestMarksTest.php`, `tests/InstallSchemaPatchTest.php` |

### UI hints (RubricForm)

- Place attendance in a `fieldset` with `legend` “Attendance” and `role="radiogroup"` + `aria-required="true"`.
- On absent: `readOnly` on inputs is insufficient — use `disabled` and exclude from `validateMarksForSave`.
- After save: call `onSaved()` so grid refreshes attendance chip and dashes.

### Grid hints (MarkingGrid)

- Add column **Attendance** between reg no and first criterion (or combine with status chip: “Absent · Draft”).
- `studentStatusChip` unchanged for draft/frozen; attendance is separate dimension.

### Reports / scores (read-only impact)

- `pr_rubric_scores` view already exposes `score` nullable — absent rows export as empty/null.
- `ScoreService::calculate_reviewer_total()` already `continue`s on null score — absent students won’t inflate totals.
- Coordinator progress % (`ReportQueryService::count_submitted_marks`) — confirm absent+frozen students count toward completion; adjust count query only if freeze does not create submitted rows.

### Previous story intelligence (5.6)

- Marking is **Save draft only** + **Freeze** bulk `submitted` — attendance must participate in freeze completeness, not per-student submit.
- `RubricForm` filters empty criteria before POST — absent save should POST `criteria: []` + `attendance_status: "absent"` and let server write nulls for all criteria.
- Deep link `?student=` modal pattern: preload attendance from list payload to avoid flash.

### Testing checklist

1. Save present with scores → GET marks returns scores + `attendance_status: present`
2. Save absent → all criterion scores null for that reviewer
3. Present with scores → save absent → scores cleared
4. Freeze panel: one present incomplete + one absent → `incomplete_marks` only for present
5. Freeze panel: all present complete + one absent → success
6. Missing `attendance_status` on POST → `attendance_required`

### References

- [Source: `_bmad-output/implementation/5-6-reviewer-marking-grid-freeze.md`] — grid, modal, freeze
- [Source: `includes/Install.php` — `table_review_student_panels`, `table_marks` nullable score]
- [Source: `includes/services/MarkService.php` — `save_marks`, `freeze_review_marks`, `student_marks_complete`]
- [Source: `src/reviewer/components/RubricForm.jsx` — save draft payload]
- [Source: `_bmad-output/planning/epics.md` — Epic 5 FR15/FR16]

## Dev Agent Record

### Agent Model Used

Auto (dev-story workflow)

### Debug Log References

### Completion Notes List

- Added `attendance_status` (`present`/`absent`) on `pr_review_student_panels` with schema patch backfill to `present`.
- `MarkService::save_marks()` requires attendance; absent path nulls all criterion scores for the reviewer; freeze treats absent students complete and seeds draft null marks before bulk submit.
- Fixed `MarkRepository::is_student_frozen_for_reviewer()` to allow submitted rows with null scores (absent + frozen).
- Reviewer UI: attendance column on marking grid, required radiogroup in score modal, criteria disabled when absent.
- PHPUnit: 154 tests OK; `npm run build` OK.

### File List

- includes/Install.php
- includes/repositories/ReviewAssignmentRepository.php
- includes/repositories/MarkRepository.php
- includes/services/MarkService.php
- includes/rest/class-rest-marks.php
- includes/rest/class-rest-reviewer-assignments.php
- src/shared/markErrors.js
- src/shared/markValidation.js
- src/reviewer/components/RubricForm.jsx
- src/reviewer/components/MarkingGrid.jsx
- tests/FakeWpdb.php
- tests/InstallSchemaTest.php
- tests/InstallSchemaPatchTest.php
- tests/MarkServiceTest.php
- tests/RestMarksTest.php
- tests/RestReviewerAssignmentsTest.php
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css
- build/reviewer.asset.php

### Change Log

- 2026-05-17: Story 5.7 — per-review student attendance (present/absent), null scores when absent, freeze/UI/REST integration.
