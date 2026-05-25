# Story 3.11: Per-review panel assignments and marking-active reviews

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want each review round to have its own student panel and reviewer roster (seeded from session defaults, copyable from the previous round), and to flag which reviews are open for marking,
So that Review 2 can reshuffle panels without changing Review 1, and reviewers only edit marks for reviews I have activated.

As a **reviewer**,
I want to see and enter marks only for review rounds that are confirmed, active for marking, and where I am assigned on that round’s panel roster,
So that I cannot mark the wrong round or a round the coordinator has closed.

## Acceptance Criteria

1. **Given** a review round on a session **When** the coordinator toggles **Marking active** (off/on) on the **final Reviews wizard step** **Then** `pr_reviews` persists `marking_active` (boolean; default `0` for newly created reviews until coordinator explicitly activates on that step) **And** `PUT /sessions/{id}/reviews/{review_id}` accepts `marking_active` **And** copy explains: “Reviewers can enter marks when the session is active, the rubric is confirmed, and marking is active for this round.”

2. **Given** a reviewer on `/reviews/mark/` **When** they load assignments **Then** a review appears as markable only if: session `status === active`, review rubric `status === confirmed`, and `marking_active === true` **And** they remain assigned on that review’s panel roster (AC3) **When** `marking_active === false` **Then** the assignment is listed under blocked with reason `marking_inactive` (or equivalent code) even if the rubric is confirmed **And** `POST`/`GET` marks for that review return `403` with the same code.

3. **Given** session-default panels and reviewers exist (`pr_session_students.panel_id`, `pr_panel_reviewers`) **When** the first review round is created or assignments are first materialized **Then** per-review rows are seeded from those session defaults into new tables (see Dev Notes) **And** `MarkService::validate_assignment()` resolves **panel_id** and reviewer membership from the **review-scoped** tables, not session enrolment alone (`unset($review_id)` in `validate_assignment` must be removed/fixed).

4. **Given** Review 1 assignments exist **When** the coordinator creates Review 2 (or clicks **Copy assignments from previous review** on the assignments UI) **Then** student panel placements and reviewer panel placements for Review 2 are copied from Review 1 **And** Review 1 assignments are unchanged **And** the coordinator may move students between panels and reviewers between panels for Review 2 only via REST/UI without affecting Review 1.

5. **Given** the session wizard **When** the coordinator completes **Panels** and **Reviewers** **Then** those steps define the **session default template** (wording: “Default for Review 1; later rounds start as a copy of the previous review”) **And** wizard order is **Students → Panels → Reviewers → Review assignments → Rubrics → Reviews** (Reviews is **last** — see Dev Notes) **And** a new **Review assignments** step lets the coordinator pick a review round and edit per-review student panel + reviewer roster, with **two** secondary actions (each behind `ConfirmDialog`): **Copy from previous review** and **Reset to session defaults** **And** `WizardNav` blocks Rubrics until every enrolled student has a panel on **every** review round in the session **And** the final **Reviews** step is blocked until Rubrics criteria exist for each review (or existing `review_count`/criteria gate).

6. **Given** per-review student on panel A and reviewer R on panel A for `review_id` **When** coordinator moves student to panel B for `review_id` only **Then** only `review_id` rows update **And** existing `pr_marks` for that student/review are not deleted (historical marks stay; new saves follow new assignment rules) **And** progress/report queries use per-review `panel_id` for grouping counts where applicable.

7. **Given** migration on existing sites **When** `dbDelta` / `ensure_schema_patches` runs **Then** new tables/columns are created **And** a one-time backfill copies current `pr_session_students.panel_id` and `pr_panel_reviewers` (+ `user_id`) into per-review rows for **each** existing `pr_reviews` row in that session **And** PHPUnit covers: seed on review create, copy review N→N+1, assignment guard uses review scope, reviewer list filters inactive reviews.

## Tasks / Subtasks

- [x] Schema: add `pr_reviews.marking_active`; add `pr_review_student_panels` and `pr_review_panel_reviewers` (names per Dev Notes); `Install::ensure_schema_patches()` + backfill migration
- [x] Repository: `ReviewAssignmentRepository` (or extend `ReviewRepository` / `SessionRepository`) — get/set student panel per review, list/copy reviewer roster per review, `copy_from_review($from, $to)`, `seed_from_session_defaults($review_id)`
- [x] Wire `ReviewRepository::create()` to call seed (session defaults for first review; copy previous for subsequent — match product rule in Dev Notes)
- [x] REST: extend review `PUT` with `marking_active`; new routes under `/sessions/{id}/reviews/{review_id}/assignments/...` (students bulk panel update, reviewers CRUD mirroring panel reviewers)
- [x] `MarkService`, `Rest_Reviewer_Assignments`, `Rest_Marks`, `ScoreService`, `ReportQueryService` — resolve assignments per `review_id`
- [x] Coordinator: `ReviewAssignmentsStep.jsx` (copy + reset buttons); reorder wizard to **Students → Panels → Reviewers → Assignments → Rubrics → Reviews**; slim `ReviewRoundsStep` to final-step only (round CRUD + `marking_active`); auto-create Review 1 on session create
- [x] Reviewer: `MarkAssignments.jsx` + `markErrors.js` blocked copy for `marking_inactive`
- [x] Tests: repository + REST + `MarkServiceTest` regression; update `tests/sql/01_seed_demo_session.sql` if needed
- [x] Run `composer test` and `npm run build`

## Dev Notes

### Product intent (user request)

Coordinators run **multiple review rounds** (Review 1, Review 2, …) on one project. Today:

| Concern | Current behaviour | Required |
|--------|-------------------|----------|
| Who marks when | Rubric `confirmed` + session `active` | Also **`marking_active`** on the review round |
| Student → panel | One `panel_id` on `pr_session_students` for all reviews | **Per review** — e.g. move student to another panel for Review 2 only |
| Reviewer → panel | `pr_panel_reviewers` only (session/panel) | **Per review** — same reviewers as Review 1 by default, movable for Review 2 |
| Reviewer login | Uses session panel + all confirmed reviews | Only **marking-active** reviews + per-review roster |

**Session defaults:** Wizard **Panels** + **Reviewers** remain the template. **Review 1** assignments initialize from that template. **Review 2+** default to a **copy of the previous review’s** assignments on create. Coordinators can always run **Reset to session defaults** or **Copy from previous review** on the Review assignments step (both required; confirm before overwrite).

### Critical code gap (do not miss)

```232:238:includes/services/MarkService.php
    private function validate_assignment(
        int $session_id,
        int $review_id,
        int $student_id,
        int $reviewer_user_id
    ): ?\WP_Error {
        unset($review_id);
```

`review_id` is currently ignored; all marking uses session-level `panel_id`. This story **must** use `review_id` when checking enrolment and reviewer-on-panel.

`Rest_Reviewer_Assignments::list_assignments` iterates all reviews and uses `panels_for_user()` from session panels only — must intersect with per-review reviewer rows and `marking_active`.

### Schema proposal (implementer may adjust names; keep semantics)

**`pr_reviews`** — add column:

- `marking_active` `tinyint(1) NOT NULL DEFAULT 1`

**`pr_review_student_panels`** — effective student grouping per round:

| Column | Notes |
|--------|--------|
| `review_id`, `student_id`, `panel_id` | UNIQUE (`review_id`, `student_id`) |
| FK logical to `pr_reviews`, `pr_students`, `pr_panels` (same session) |

**`pr_review_panel_reviewers`** — effective reviewer roster per round:

| Column | Notes |
|--------|--------|
| `review_id`, `panel_id`, `user_id`, `weight` | UNIQUE (`review_id`, `panel_id`, `user_id`) |
| Optional `panel_reviewer_id` link to `pr_panel_reviewers.id` for traceability — not required if `user_id` is enough |

**Keep** `pr_session_students.panel_id` and `pr_panel_reviewers` as **session default template** (do not delete in this story). **`pr_review_reviewer_overrides`** only stores weight overrides today — either migrate weights into `pr_review_panel_reviewers` or keep overrides for weight-only; avoid two competing sources of truth (document choice in completion notes).

### Seeding rules

| Event | Action |
|-------|--------|
| Review 1 created | `seed_from_session_defaults(review_id)` — all enrolled students → panels from `pr_session_students`; all `pr_panel_reviewers` → `pr_review_panel_reviewers` (require `user_id` linked; skip unlinked rows with coordinator notice) |
| Review N+1 created | `copy_from_review(previous_review_id, new_review_id)` |
| Manual **Copy from previous review** (UI) | `copy_from_review(previous_review_id, target_review_id)` |
| Manual **Reset to session defaults** (UI) | `reset_to_session_defaults(target_review_id)` |
| Student added to session after reviews exist | Add row on **every** existing review’s assignment table using session default `panel_id` from `pr_session_students` (keep in sync when default panel changes before first per-review edit) |

### Wizard flow (updated — Reviews last, product-approved)

**Rationale:** Coordinators configure the **project cohort and default rosters first**, then **per-round overrides**, then **rubrics**, and only at the end **open marking** (`marking_active`) and add extra rounds. That matches “setup everything, then go live.”

```text
Students (project roster)
  → Panels (session default student → panel)
  → Reviewers (session default reviewer → panel)
  → Review assignments (per-review roster; copy / reset actions)
  → Rubrics (criteria + confirm per review round)
  → Reviews (LAST: add/rename/reorder rounds, marking_active toggles)
```

**Prerequisite — auto Review 1:** Because Rubrics and Review assignments need a `review_id` before the final Reviews step, **`POST /sessions` must auto-create “Review 1”** (draft, `marking_active = 0`) and seed per-review assignments from session defaults once Panels + Reviewers exist (or on first visit to Review assignments). Remove the old gate that blocked Students when `review_count === 0` (story 3.7); replace with `review_count >= 1` guaranteed by auto-create.

**Split `ReviewRoundsStep` concerns:**

| Step | Responsibility |
|------|----------------|
| Review assignments | Per-review student/reviewer panels; **Copy from previous** + **Reset to session defaults** |
| Rubrics | Criteria + confirm/unlock (existing `ReviewRubricBlock` / `RubricsPanel` patterns) |
| Reviews (final) | Add Review 2+, rename, reorder, delete; **marking_active** only here |

**Dashboard deep-link:** New sessions open wizard at `?step=students` (not `reviews`).

Update `WizardNav.jsx` and `SessionWizard.jsx` `STEPS` to match order above; add `assignments` key between `reviewers` and `rubrics` (or fold rubrics into reviews route key if still combined — prefer explicit `rubrics` step key for nav clarity).

`wizard_state` additions (example): `assignments_complete`, `can_advance_to_rubrics`, `can_advance_to_reviews` (final step) — assignments complete when every enrolled student has `panel_id` on every review; final Reviews step complete when coordinator has visited it at least once or all `marking_active` choices are explicit (minimum: step reachable after rubrics).

### API sketch

| Method | Path | Purpose |
|--------|------|---------|
| PUT | `/sessions/{id}/reviews/{review_id}` | `marking_active`, existing fields |
| GET | `/sessions/{id}/reviews/{review_id}/assignments` | `{ students: [...], reviewers: [...] }` |
| PUT | `/sessions/{id}/reviews/{review_id}/assignments/students` | bulk `{ student_id, panel_id }[]` |
| POST/PUT/DELETE | `/sessions/{id}/reviews/{review_id}/assignments/reviewers` | mirror `class-rest-reviewers.php` |
| POST | `/sessions/{id}/reviews/{review_id}/assignments/copy-from/{source_review_id}` | **Copy from previous review** (UI: previous round by `sort_order`, or explicit source id) |
| POST | `/sessions/{id}/reviews/{review_id}/assignments/reset-to-session-defaults` | **Reset to session defaults** — overwrite per-review students/reviewers from `pr_session_students` + `pr_panel_reviewers` |

Capability: `pr_manage_panels` + `pr_assign_reviewers` for write; reviewers read only via existing assignment endpoints.

### Services to update (checklist)

| Component | Change |
|-----------|--------|
| `MarkService::validate_assignment` | Per-review student panel + per-review reviewers |
| `Rest_Reviewer_Assignments` | Filter `marking_active`; per-review roster |
| `ScoreService::calculate_review_score` | Panel reviewer weights from `pr_review_panel_reviewers` for that `review_id` |
| `ScoreService` progress aggregation | Group students by per-review panel |
| `ReportQueryService` | Panel column per review where reports are review-aware |
| `ReviewRoundsStep` | Toggle marking active |
| `SessionWizard` | New step + gates |

### UX

- **Reviews step (final):** List rounds with `StatusChip` for rubric status + switch “Marking active” / “Marking paused”; subcopy: “Turn marking on when the rubric is confirmed and you are ready for reviewers to score this round.”
- **Review assignments step:** Review round `<select>` or tabs; subcopy: “Changes here apply only to the selected review round.” Toolbar: **Copy from previous review** | **Reset to session defaults** (destructive confirm: “This replaces assignments for [Review N] only.”)
- **Reviewer blocked banner:** “This review round is not open for marking.” (`marking_inactive`)
- Reuse dense reviewer table patterns from story **3-9** (`PanelReviewersStep.jsx`).

### Anti-patterns

- Do not overload `pr_reviews.status` (`draft`/`confirmed`/`unlocked`) to mean marking open/closed — rubric lifecycle stays separate.
- Do not remove session-level panels/reviewers; they are the default template.
- Do not silently delete marks when reassigning panels for a review.
- Do not implement per-review **enrolment** (student on/off project) — roster remains session-scoped (`pr_session_students`); only **panel placement** varies per review.

### Supersedes (explicit)

Stories **3.7** and **3.8** stated session-scoped panel assignment for all review rounds. **This story changes that product rule** for panel placement and reviewer placement only; project roster (who is enrolled) remains session-scoped.

### Testing

- Create session → 2 reviews → different student `panel_id` per review → reviewer A sees student only on correct review assignment.
- `marking_active = 0` → assignment blocked; marks POST returns 403.
- Copy Review 1 → Review 2 → edit Review 2 only → Review 1 unchanged.
- Backfill migration: existing demo session gets per-review rows matching old session panels.
- Regression: `RestMarksTest`, `ScoreServiceTest`, `RestReviewersTest` (session template endpoints still work).

### References

- [Source: _bmad-output/planning/epics.md — FR6, FR10, FR14; Phase 2 per-review overrides]
- [Source: themes/david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §2, §5.1 `pr_review_reviewer_overrides`, §7 assignment scoping]
- [Source: _bmad-output/implementation/3-7-wizard-review-rounds.md — superseded panel scope note]
- [Source: _bmad-output/implementation/3-8-project-default-student-roster.md — enrolment stays session-scoped]
- [Source: _bmad-output/implementation/3-9-panel-reviewers-dense-roster-ui.md — UI patterns]
- [Source: _bmad-output/implementation/5-2-marks-rest-scoping.md, 5-3-reviewer-assignments-ui.md]
- [Source: includes/services/MarkService.php — `validate_assignment` ignores `review_id` today]
- [Source: includes/rest/class-rest-reviewer-assignments.php]

## Dev Agent Record

### Agent Model Used

Auto (dev-story)

### Debug Log References

### Completion Notes List

- Per-review assignment tables (`pr_review_student_panels`, `pr_review_panel_reviewers`) with backfill for existing reviews; `marking_active` on `pr_reviews` (default 0).
- `ReviewAssignmentRepository` seeds from session defaults for Review 1, copies previous review for Review N+1; `ReviewRepository::create()` wires seed/copy automatically.
- Reviewer weights for scoring use `pr_review_panel_reviewers.weight` as source of truth (session `pr_panel_reviewers` used only for display names when listing assignment reviewers).
- Wizard reordered: Students → Panels → Reviewers → Assignments → Rubrics → Reviews; `POST /sessions` auto-creates Review 1; final Reviews step toggles `marking_active` only.
- PHPUnit: 132 tests pass; `npm run build` succeeds.

### File List

- includes/install.php
- includes/repositories/ReviewAssignmentRepository.php
- includes/repositories/ReviewRepository.php
- includes/repositories/SessionRepository.php
- includes/services/MarkService.php
- includes/services/ScoreService.php
- includes/rest/class-rest-review-assignments.php
- includes/rest/class-rest-bootstrap.php
- includes/rest/class-rest-reviews.php
- includes/rest/class-rest-reviewer-assignments.php
- includes/rest/class-rest-sessions.php
- src/coordinator/components/ReviewAssignmentsStep.jsx
- src/coordinator/components/ReviewRubricsStep.jsx
- src/coordinator/components/ReviewRoundsStep.jsx
- src/coordinator/components/PanelsStep.jsx
- src/coordinator/pages/SessionWizard.jsx
- src/shared/components/WizardNav.jsx
- src/shared/markErrors.js
- src/reviewer/pages/MarkAssignments.jsx
- tests/ReviewAssignmentRepositoryTest.php
- tests/FakeWpdb.php
- tests/MarkServiceTest.php
- tests/ScoreServiceTest.php
- tests/RestMarksTest.php
- tests/RestSessionsTest.php
- tests/InstallSchemaTest.php
- tests/InstallUpgradeTest.php
- build/coordinator.js
- build/reviewer.js

### Change Log

- 2026-05-17: Story created — per-review panel assignments, marking-active flag, reviewer scoping (Epic 3.11). User request: tie assignments to review rounds; Review 2 reshuffle; only active reviews editable in reviewer login.
- 2026-05-17: User feedback — require both **Reset to session defaults** and **Copy from previous review**; move **Reviews** wizard step to **end** (after Rubrics); auto-create Review 1 on session create.
