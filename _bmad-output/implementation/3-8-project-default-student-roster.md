# Story 3.8: Project default student roster (attached cohort for all review rounds)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want each project (review session) to have an attached list of students that is used by default for every review round in that project,
So that I define the marking cohort once per project instead of re-configuring students for each review.

## Acceptance Criteria

1. **Given** a coordinator creates a project via the dashboard **When** they optionally attach students at creation (multi-select from registry and/or CSV) **Then** those students are persisted as session enrolments in `pr_session_students` **And** `POST /project-reviews/v1/sessions` accepts optional `student_ids: number[]` (validated registry IDs; skip unknown/duplicate with clear partial-success response) **And** creating with only a title still works (empty roster allowed in `draft`).

2. **Given** a project with an attached roster **When** the coordinator views the dashboard session list **Then** each `SessionCard` shows roster size (e.g. “12 students”) from `enrolled_count` on the session payload **And** `GET /sessions` includes `enrolled_count` per session without N+1 query explosions (use aggregate/count query in repository, not per-card REST calls).

3. **Given** one or more review rounds exist for the project **When** reviewers mark or coordinators view progress/reports **Then** only students on the project roster (enrolled + panel-assigned where required) participate **And** no per-review enrolment table is introduced — the same roster applies to **all** review rounds (design spec §2; story 3.7 constraint).

4. **Given** the session wizard **When** the coordinator is on **Students** (or creates a project with an initial roster) **Then** UI copy uses **project** / **project roster** language consistently **And** explains: “This roster is the default for every review round in this project.” **And** enrolled list remains editable (add/remove/CSV re-enrol) as today.

5. **Given** `GET /sessions/{id}/wizard-state` **When** `enrolled_count === 0` **Then** existing gates still block Panels / Reviewers / Rubrics **When** `enrolled_count > 0` **Then** `can_advance_to_panels` behaves as today **And** PHPUnit covers optional `student_ids` on create and `enrolled_count` on list.

6. **Given** a student is removed from the project roster **When** they had panel assignment **Then** enrolment row is removed (existing `DELETE /sessions/{id}/students/{student_id}`) **And** marks for that student remain governed by existing rubric lifecycle rules (do not silently delete marks in this story unless product explicitly adds that — document behaviour in dev notes).

## Tasks / Subtasks

- [x] Extend `SessionRepository::create()` or add `enrol_students_bulk()` helper; call from `Rest_Sessions::create_session` when `student_ids` present
- [x] Add `SessionRepository::count_enrolled_for_sessions(array $ids)` or list query with `enrolled_count` for dashboard
- [x] Extend `format_session()` / `list_sessions` response with `enrolled_count`
- [x] Dashboard create modal: optional multi-select registry search (reuse debounced search pattern from `SessionWizard`) **or** “Add students after creation” link if scope is tight — minimum: support `student_ids` in API + wizard remains full editor
- [x] `SessionCard`: display `enrolled_count` when not null
- [x] Wizard Students step: headline **Project roster**; reinforce default-for-all-reviews copy (align with 3.7)
- [x] Tests: `RestSessionsTest` — create with `student_ids`, list includes counts; regression on wizard gates
- [x] Run `composer test` and `npm run build`

## Dev Notes

### Product intent (user request)

Coordinators think in **projects** (plugin term: **review session**). Each project must own a **student list** that defines who gets marked. That list is the **default cohort for every review round** (Review 1, Review 2, …) in the project — not a separate roster per review.

### What already exists (do not reinvent)

| Capability | Location | Notes |
|------------|----------|--------|
| Session enrolment table | `pr_session_students` | `session_id`, `student_id`, `panel_id` |
| Enrol / list / remove REST | `includes/rest/class-rest-sessions.php` | `GET/POST/PUT/DELETE /sessions/{id}/students`, CSV `POST .../enrol` |
| Repository | `includes/repositories/SessionRepository.php` | `enrol_student()`, `list_enrolled()`, `count_enrolled()` |
| Wizard Students step | `src/coordinator/pages/SessionWizard.jsx` | Registry search + `CsvImportMapper` `session-enrol` |
| Review-round-first flow | Story 3.7 — `ReviewRoundsStep`, `review_count` gates | Roster still configured on Students step |
| Marking scope guard | `MarkService::validate_save_context()` | Uses `find_enrolment($session_id, $student_id)` |
| Progress / scoring | `ScoreService` | Iterates `list_enrolled($session_id)` |

**Gap:** Project creation only sent `{ title }`. Dashboard cards did not show roster size. The mental model “students belong to the project” was implicit in the wizard, not visible at create/list time.

### Data model — do not break

- **Do not** add `pr_review_students` or per-review enrolment unless product explicitly requests a schema change (see 3.7 dev notes).
- **Panel** assignment stays on `pr_session_students.panel_id` and applies to all reviews in the session.
- Marks grain remains: **session × review × student × reviewer × criterion** (`pr_marks`).

### Roster removal behaviour (AC6)

Removing a student via `DELETE /sessions/{id}/students/{student_id}` deletes the `pr_session_students` row only. Existing marks in `pr_marks` are **not** deleted by this story; rubric lifecycle and reporting rules continue to apply to historical marks.

### Implementation sketch

1. **`POST /sessions` body** — optional `student_ids: int[]`. After `create()`, loop unique positive IDs; `StudentRepository::find_by_id`; `enrol_student($session_id, $student_id)`; return `{ ...session, enrolled_count, enrolled_student_ids? }`.

2. **List performance** — single query:

   ```sql
   SELECT session_id, COUNT(*) AS enrolled_count
   FROM pr_session_students
   WHERE session_id IN (...)
   GROUP BY session_id
   ```

   Merge into `format_session()` results.

3. **Dashboard create UX** (minimum viable):
   - Expand create form: title + optional “Add students from registry” (debounced search, chips, submit passes `student_ids`).
   - On success, navigate to `?step=reviews` (keep 3.7 default); roster already attached when user reaches Students step.

4. **Copy** — prefer **project** in coordinator-facing strings where it means session; keep API resource name `sessions` unchanged.

### Anti-patterns

- Do not duplicate enrolment logic in React only — server must persist on create.
- Do not fetch `/sessions/{id}/students` per card on the dashboard list.
- Do not require students at create time (draft projects may start empty).
- Do not implement per-review student overrides in this story.

### Testing

- Create session with `student_ids` → `count_enrolled` matches.
- Create session without `student_ids` → `enrolled_count: 0`.
- List sessions returns correct `enrolled_count` for multiple sessions.
- Wizard gates unchanged when `enrolled_count === 0`.
- Marking still rejected for non-enrolled student (existing `RestMarksTest` / `MarkServiceTest` regression).

### References

- [Source: _bmad-output/planning/epics.md — FR4, Epic 3]
- [Source: themes/david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §2 Concepts, §5.1 `pr_session_students`]
- [Source: _bmad-output/implementation/3-3-wizard-students-enrolment.md]
- [Source: _bmad-output/implementation/3-7-wizard-review-rounds.md — session-scoped roster constraint]
- [Source: _bmad-output/planning/ux-design-specification.md — UX-DR9 wizard, session wizard hero flow]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

- PHPUnit: `./vendor/bin/phpunit` — 108 tests, 366 assertions, all pass
- Frontend: `npm run build` — webpack compiled successfully

### Completion Notes List

- Added `SessionRepository::enrol_students_bulk()` and `count_enrolled_for_sessions()` for create-time roster attach and dashboard list aggregates (no N+1).
- `POST /sessions` accepts optional `student_ids`; response includes `enrolled_count` and `enrolment` partial-success payload (`enrolled`, `skipped` with reasons).
- `GET /sessions`, `GET /sessions/{id}`, and `PUT /sessions/{id}` include `enrolled_count`.
- Dashboard create form: debounced registry search, chip multi-select, passes `student_ids` on create; navigates to `?step=reviews`.
- `SessionCard` shows roster size; wizard Students step uses “Project roster” copy.
- Extended `RestSessionsTest` and `SessionRepositoryTest`; wizard gate regression when roster populated.

### File List

- includes/repositories/SessionRepository.php
- includes/rest/class-rest-sessions.php
- src/coordinator/pages/Dashboard.jsx
- src/coordinator/pages/SessionWizard.jsx
- src/shared/components/SessionCard.jsx
- tests/FakeWpdb.php
- tests/SessionRepositoryTest.php
- tests/RestSessionsTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php

### Change Log

- 2026-05-17: Story created — project-level default student roster visible at create/list; session enrolment applies to all review rounds (Epic 3.8).
- 2026-05-17: Implemented API bulk enrol on create, list `enrolled_count`, dashboard create roster picker, SessionCard roster label, wizard copy, tests.
