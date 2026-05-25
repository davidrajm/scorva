# Story 3.12: Student project title (per review) + roster CSV + guarded removal

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **program coordinator**,
I want to capture and edit each student's **project title** (the work being reviewed) when re-enrolling the roster and on each review round's assignments,
So that panels and reports show the correct dissertation/project name per round, while students with existing scores cannot be removed from the project.

## Acceptance Criteria

1. **Terminology and scope**
   - **Given** coordinator-facing copy **When** this feature is shown **Then** **Project title** means the student's dissertation/project name being reviewed — **not** `pr_sessions.title` (the review event name on the dashboard).
   - **Given** a reviewer or panel head **When** they view assignment or report UIs **Then** they see project titles read-only (no edit controls).

2. **Data model — session default + per-review override**
   - **Given** migrations run **When** `ensure_schema_patches` completes **Then**:
     - `pr_session_students` has nullable `project_title` `varchar(500)` (session roster default).
     - `pr_review_student_panels` has nullable `project_title` `varchar(500)` (effective title for that review round).
   - **Given** Review 1 assignments are seeded from session defaults (`seed_from_session_defaults`) **When** a student has `project_title` on enrolment **Then** Review 1's `pr_review_student_panels.project_title` is set from session default.
   - **Given** Review N+1 is created or **Copy from previous review** runs **When** assignments copy **Then** `project_title` copies with panel placement (same as `attendance_status` copy pattern in `copy_from_review`).
   - **Given** coordinator edits title on Review 2 only **Then** Review 1 title is unchanged.

3. **Wizard — Project roster (Students / re-enrol)**
   - **Given** the Students step with enrolled students **When** the coordinator views the roster **Then** a table/grid shows: reg no, student name, **Project title** (editable text input), panel name (if assigned), and **Remove**.
   - **Given** the coordinator edits a project title and blurs or saves **When** `PUT /sessions/{id}/students/{student_id}` includes `project_title` **Then** session default persists **And** all existing per-review rows for that student in this session sync the same title **unless** a review already had a distinct title edited on the Assignments step (see Dev Notes — recommended: updating roster title overwrites per-review titles only when they still match the previous session default; simpler MVP: roster PUT updates session column + `sync_project_title_to_all_reviews` for all reviews).
   - **Given** re-enrol CSV import (`session-enrol`) **When** optional column `project_title` is mapped **Then** import sets session `project_title` and syncs to per-review rows for enrolled/updated students.
   - **Given** `CsvImportMapper` for `session-enrol` **When** coordinator downloads the template **Then** a dedicated template is offered (`reg_no`, `panel`, `project_title`) — not the registry students template alone.

4. **Wizard — Review assignments**
   - **Given** the Review assignments step for a selected review round **When** the coordinator views the student list **Then** each row shows reg no, name, **Project title** (editable), and panel `<select>` **And** title field is pre-filled from the previous review when **Copy from previous review** was used (via `copy_from_review`).
   - **Given** the coordinator changes panel and/or title **When** they click **Save** on that row (or a single **Save changes** for the table — implementer picks one; prefer per-row **Save** next to panel+title to match dense wizard patterns) **Then** one `PUT .../assignments/students` persists **both** `panel_id` and `project_title` for that student on the selected review only **And** panel is **not** auto-saved on `<select>` change alone (remove current immediate `saveStudentPanel` on change behaviour).
   - **Given** Review 1 **When** coordinator has not set a per-review title **Then** display falls back to session roster `project_title` (API returns resolved `project_title` on assignment list).

5. **Coordinator-only writes**
   - **Given** a user with `pr_manage_sessions` (coordinator) **When** they call title update endpoints **Then** writes succeed.
   - **Given** a reviewer **When** they call the same endpoints **Then** `403` (existing REST capability guards).

6. **Student removal guard (scores)**
   - **Given** a student enrolled in the project **When** the coordinator clicks **Remove** **Then** the server checks for any **assigned numeric score** in this session: `pr_marks` where `session_id` = project and `student_id` = student and `score IS NOT NULL`.
   - **Given** at least one such mark exists **When** `DELETE /sessions/{id}/students/{student_id}` runs **Then** `409` with code `pr_student_has_scores` and message explaining removal is blocked because marking has started.
   - **Given** no numeric scores exist for that student in the session (draft rows with null scores, or no rows) **When** delete runs **Then** enrolment is removed, `remove_student_from_all_reviews` runs (panel links, per-review assignment rows, attendance-by-reviewer rows for that student) **And** UI shows success.
   - **Given** removal is blocked **When** the coordinator views the roster **Then** **Remove** is disabled with tooltip/helper: “Cannot remove: this student has scores in one or more review rounds.”

7. **Downstream consumers (alignment)**
   - **Given** panel report / marks matrix / exports that show project title (story **11-1**, **7-5**) **When** this story ships **Then** `ReportsViewService` (and related formatters) resolve **project title** in order: per-review `pr_review_student_panels.project_title` → session `pr_session_students.project_title` → registry `pr_student_meta` key `project_title` (if present) → empty string.
   - **And** no regression to attendance columns or reviewer slots from **7-8**.

8. **Tests**
   - **Given** PHPUnit suite **When** this story is complete **Then** tests cover: schema patch, roster PUT with title, CSV import with `project_title`, copy-from-previous copies titles, assignments PUT with title+panel, delete blocked with scores / allowed without, reviewer cannot PUT title.

## Tasks / Subtasks

- [x] **Schema:** `Install::ensure_schema_patches()` add `project_title` to `pr_session_students` and `pr_review_student_panels`; backfill NULL; bump `pr_db_version` if project uses version bump pattern
- [x] **Repositories:** `SessionRepository` — read/write `project_title` on enrol/import; `ReviewAssignmentRepository` — `set_student_panel` / `bulk_set` accept optional `project_title`; `copy_from_review` + `seed_from_session_defaults` propagate title; `MarkRepository::student_has_numeric_scores_in_session($session_id, $student_id): bool`
- [x] **REST:** extend `list_enrolled_students`, `update_enrolled_student`, `import_enrolment` with `project_title`; guard `remove_enrolled_student` with score check; extend `Rest_Review_Assignments::update_students` payload with `project_title` per row; `format_assignments` returns `project_title` (resolved fallback)
- [x] **Assets:** `assets/csv/session-enrol-template.csv` (`reg_no,panel,project_title`); register URL in app shell / `prAppData` (mirror `studentImportTemplateUrl` pattern)
- [x] **UI — Students step:** roster table with editable project title; wire PUT; disabled Remove + tooltip when `has_scores` on list payload
- [x] **UI — `CsvImportMapper`:** `session-enrol` optional field `project_title`; template download link to new CSV
- [x] **UI — `ReviewAssignmentsStep`:** title column + panel select; explicit Save (no auto-save on panel change); local dirty state optional
- [x] **Reports:** `ReportsViewService` title resolution order (AC7)
- [x] **Tests:** repository + REST + import row; `RestSessionsTest`, `ReviewAssignmentRepositoryTest`, extend `RestReviewAssignmentsTest` if present
- [x] Run `composer test` and `npm run build`

## Dev Notes

### Product intent (user request)

Coordinators need the **name of the student's project** (thesis/dissertation title) visible when setting up the cohort and when configuring each review round. Titles **copy forward** across rounds but may be **edited per review** (e.g. revised title for Review 2). Only the **program coordinator** edits titles; reviewers read them on reports/assignments.

Student **removal** from the project must stay safe: if **any numeric score** exists in **any** review round in that project, removal is refused; otherwise removal clears project enrolment and all per-review panel links (existing cascade).

### Disambiguation: three “titles”

| Field | Meaning |
|-------|---------|
| `pr_sessions.title` | Review **event** name on dashboard (“MDT 2026 Review”) |
| `pr_session_students.project_title` | Session roster **default** project title for a student |
| `pr_review_student_panels.project_title` | **Effective** title for that student in that review round |

Do **not** rename session columns. UI label: **Project title**.

### What already exists (extend, do not reinvent)

| Area | Location | Notes |
|------|----------|--------|
| Roster enrol / CSV | `SessionRepository::import_enrolment`, `CsvImportMapper` `session-enrol` | Today: `reg_no`, `panel` only |
| Enrolled list REST | `Rest_Sessions::list_enrolled_students` | Add `project_title`, `has_scores` |
| Remove enrolment | `SessionRepository::remove_enrolment` | No score guard today — **add** |
| Per-review assignments | `ReviewAssignmentsStep.jsx`, `Rest_Review_Assignments` | Panel auto-saves on change — **change to Save** |
| Copy assignments | `ReviewAssignmentRepository::copy_from_review` | Copy `attendance_status`; add `project_title` |
| Registry meta | `pr_student_meta` + story **11-1** | Fallback only after session/per-review |
| Capabilities | `PR_CAP_MANAGE_SESSIONS` | Coordinator writes |

### Schema patch (implementer)

```sql
-- pr_session_students
ALTER ADD project_title varchar(500) NULL DEFAULT NULL;

-- pr_review_student_panels  
ALTER ADD project_title varchar(500) NULL DEFAULT NULL;
```

Use existing `Install::column_exists` + `dbDelta` / `ensure_schema_patches` pattern (see `marking_active`, `attendance_status` migrations).

### Score guard for removal

```php
// MarkRepository — new method
public function student_has_numeric_scores_in_session(int $session_id, int $student_id): bool
{
    // SELECT 1 FROM pr_marks
    // WHERE session_id = %d AND student_id = %d AND score IS NOT NULL LIMIT 1
}
```

- **Absent students** with `score IS NULL` do **not** block removal (5-7).
- **Do not** delete historical `pr_marks` in this story when removal is allowed and no scores — if marks rows exist with all null scores, product may still allow removal (user: “score is not assigned”); document in completion notes.
- On successful delete: existing `remove_student_from_all_reviews` + delete enrolment; consider deleting `pr_review_student_attendance_by_reviewer` rows for that student/session reviews (mirror panel row cleanup).

### Title sync rules (recommended)

| Action | Effect |
|--------|--------|
| Set title on **Students** roster (PUT) | Update `pr_session_students.project_title` + set same on **all** `pr_review_student_panels` for that student in session |
| Set title on **Assignments** (PUT per review) | Update **only** that review's `pr_review_student_panels.project_title` |
| **Copy from previous review** | Copy `project_title` from source rows (already in `SELECT *` loop once column exists) |
| **Seed from session defaults** | Copy `project_title` from enrolment row when creating review student panel |
| New student enrolled mid-project | `enrol_student` + `sync_student_to_all_reviews` should pass session `project_title` |

### API sketch

| Method | Path | Change |
|--------|------|--------|
| GET | `/sessions/{id}/students` | Each item: `project_title`, `has_scores` (bool) |
| PUT | `/sessions/{id}/students/{student_id}` | Body: `panel_id`, `project_title` (optional keys) |
| POST | `/sessions/{id}/enrol` | Rows may include `project_title` |
| DELETE | `/sessions/{id}/students/{student_id}` | `409 pr_student_has_scores` when blocked |
| PUT | `/sessions/{id}/reviews/{review_id}/assignments/students` | Each row: `{ student_id, panel_id, project_title? }` |
| GET | `/sessions/{id}/reviews/{review_id}/assignments` | Students include `project_title` (resolved) |

### UI patterns

**Students step (`SessionWizard.jsx`):** Replace plain `<ul>` with a compact table (`TABLE_BODY_ROW_SOFT` from `tableStyles.js`). Columns: Reg no | Name | Project title | Panel | Actions. Title: `<input type="text" className="..." />` with debounced save or blur-to-PUT. Remove: `disabled={ row.has_scores }` + `title` tooltip.

**CsvImportMapper `session-enrol`:** Add optional mapping target:

```javascript
{ key: 'project_title', label: 'Project title' },
```

Template file: `assets/csv/session-enrol-template.csv`:

```csv
reg_no,panel,project_title
25MDT1001,Panel A,Machine Learning for Healthcare
```

Expose via `window.prAppData.sessionEnrolTemplateUrl` in coordinator enqueue (same pattern as `studentImportTemplateUrl` in `class-plugin.php` or routes).

**ReviewAssignmentsStep:** Table header: Student | Project title | Panel | (Save). Hold local edits in state; **Save** calls PUT with full row. Remove `onChange` → immediate `saveStudentPanel` — only update local state until Save.

### Coordinator-only

All write routes already use `pr_manage_sessions` / `pr_manage_panels`. Reviewers never receive title fields in write APIs. Read paths for reports/assignments may include `project_title` for display.

### Relationship to story 11-1

Story **11-1** planned registry-only `project_title` meta for PDF. **This story** is the source of truth for **per-review** titles in the marking workflow. Update `ReportsViewService::scores_matrix_for_panel` (and PDF data builder) to prefer assignment title (AC7). If 11-1 is implemented first with meta-only, this story supersedes display precedence — do not duplicate two editable sources in the UI.

### Anti-patterns

- Do not store project title only on `pr_students` registry (roster is per project).
- Do not allow reviewers to edit titles via marks API.
- Do not auto-save panel assignment without explicit Save on Assignments step (user requirement).
- Do not delete `pr_marks` when blocking removal — only block the DELETE.
- Do not use `pr_sessions.title` for student project names in CSV headers.

### Testing checklist

1. Enrol student, set title on roster → appears on Review 1 assignments GET.
2. CSV import with `project_title` column → titles on roster and Review 1.
3. Copy Review 1 → Review 2 → titles match; edit Review 2 title only → Review 1 unchanged.
4. Save panel+title together on Assignments → both persist.
5. Enter numeric mark for student → DELETE enrolment returns 409; Remove disabled in UI.
6. Student with no numeric scores → DELETE succeeds; student gone from assignments lists.
7. Reports matrix shows per-review title when set.

### References

- [Source: _bmad-output/implementation/3-3-wizard-students-enrolment.md — re-enrol CSV]
- [Source: _bmad-output/implementation/3-8-project-default-student-roster.md — roster scope]
- [Source: _bmad-output/implementation/3-11-per-review-assignments-marking-active.md — assignments step, copy_from_review]
- [Source: _bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md — project_title display in reports/PDF]
- [Source: _bmad-output/implementation/5-7-student-attendance-marking.md — null scores when absent]
- [Source: includes/repositories/SessionRepository.php — `remove_enrolment`, `import_enrolment`]
- [Source: includes/repositories/ReviewAssignmentRepository.php — `copy_from_review`, `set_student_panel`]
- [Source: src/coordinator/components/ReviewAssignmentsStep.jsx — panel auto-save today]
- [Source: src/coordinator/pages/SessionWizard.jsx — roster list]
- [Source: src/coordinator/components/CsvImportMapper.jsx — `session-enrol` config]

## Dev Agent Record

### Agent Model Used

Auto (dev-story)

### Debug Log References

### Completion Notes List

- Added `project_title` on session enrolment and per-review assignments; roster PUT syncs title to all review rows; assignments PUT updates one review only.
- Removal blocked with `409 pr_student_has_scores` when any numeric mark exists; draft/null scores do not block. Attendance-by-reviewer rows cleared on successful removal.
- Reports expose resolved `project_title` (per-review → session → registry meta).
- Verification: `./vendor/bin/phpunit` (251 tests), `npm run build`.

### File List

- includes/Install.php
- includes/repositories/SessionRepository.php
- includes/repositories/ReviewAssignmentRepository.php
- includes/repositories/MarkRepository.php
- includes/rest/class-rest-sessions.php
- includes/rest/class-rest-review-assignments.php
- includes/routes.php
- includes/services/ReportsViewService.php
- assets/csv/session-enrol-template.csv
- src/coordinator/pages/SessionWizard.jsx
- src/coordinator/components/CsvImportMapper.jsx
- src/coordinator/components/ReviewAssignmentsStep.jsx
- tests/FakeWpdb.php
- tests/InstallSchemaPatchTest.php
- tests/ReviewAssignmentRepositoryTest.php
- tests/RestSessionsTest.php
- tests/RestReviewAssignmentsTest.php

### Change Log

- 2026-05-17: Story created — per-review student project title, re-enrol CSV/grid, assignments Save, score-guarded roster removal (Epic 3.12). User request via create-story.
- 2026-05-17: Implemented schema, repositories, REST, coordinator UI, reports resolution, and tests (dev-story).
