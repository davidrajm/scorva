# Story 3.7: Wizard step Reviews — create review rounds before roster setup

Status: done

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want to define Review 1, Review 2, … immediately after creating a session (project),
So that the session wizard follows the real marking lifecycle before I enrol students and assign panels.

## Acceptance Criteria

1. **Given** a draft session opened in the session wizard **When** the coordinator lands on the first setup step **Then** they see a **Reviews** step to add, rename, reorder, and remove review rounds (`pr_reviews` rows) for that session **And** at least one review round must exist before the wizard allows advance to **Students** (visible blocker on later steps).

2. **Given** the coordinator adds “Review 1”, “Review 2”, … **When** they save **Then** rows persist via existing `POST /project-reviews/v1/sessions/{session_id}/reviews` (and `PUT` for label/sort updates) **And** new reviews default to `status: draft` with no criteria required on this step.

3. **Given** review rounds exist **When** the coordinator continues to **Students → Panels → Reviewers** **Then** enrolment and panel assignment behave as today (session-scoped `pr_session_students.panel_id`) **And** inline copy explains that the same student roster and panel groupings apply to **every** review round in this session (per design spec §2 — panel ≠ review round).

4. **Given** the wizard **Rubrics** step **When** the coordinator edits criteria or confirms rubrics **Then** existing `RubricsPanel` / `RubricTable` behaviour is unchanged **And** “Add review round” on the Rubrics step either remains as a shortcut or defers to the Reviews step (pick one; avoid duplicate divergent UIs).

5. **Given** `GET /sessions/{id}/wizard-state` **When** no review rounds exist **Then** response includes `review_count: 0` and `can_advance_to_students: false` **When** at least one review exists **Then** `can_advance_to_students: true` **And** PHPUnit covers the new gate flags.

6. **Given** `WizardNav` **When** rendered **Then** step order is **Reviews → Students → Panels → Reviewers → Rubrics** (aligns with UX-DR41 “students → panels → reviewers → reviews/rubrics” by placing review **definition** before roster work, and rubric **criteria** last).

## Tasks / Subtasks

- [x] Add **Reviews** wizard step UI (new component, e.g. `ReviewRoundsStep.jsx`) — list rounds, add, edit label, delete if no marks (reuse REST; mirror patterns from `RubricsPanel.handleCreateReview`)
- [x] Reorder `STEPS` in `SessionWizard.jsx` and `WizardNav.jsx`; update `completedSteps` / `blockedSteps` logic
- [x] Extend `wizard_state` in `class-rest-sessions.php` with `review_count`, `can_advance_to_students`
- [x] Gate Students step Continue + `WizardNav` blockers on `review_count >= 1`
- [x] Optional UX polish: on session create (`Dashboard` → wizard), deep-link `?step=reviews` instead of default `students`
- [x] Optional: auto-create default “Review 1” on `POST /sessions` (only if product wants zero-click path — otherwise require explicit add on Reviews step) — skipped; explicit add on Reviews step
- [x] Add PHPUnit for `wizard_state` review gate; extend or add REST test if delete/reorder endpoints are added
- [x] Run `composer test` and `npm run build`

## Dev Notes

### Flow verification (gap analysis — read before coding)

**Intended coordinator mental model (user-confirmed):**

```text
Create project (session)
  → Create review instances (Review 1, Review 2, …)
  → Attach students with panels (roster for marking)
  → Assign reviewers
  → Define / confirm rubric criteria per review round
```

**What exists today:**

| Step | Implemented? | Notes |
|------|----------------|-------|
| Create project | Yes | `Dashboard` → `POST /sessions` → navigate to wizard |
| Create review instances | **No (wizard)** | `pr_reviews` API exists; creation only in wizard **Rubrics** step via `RubricsPanel` / standalone Rubrics page |
| Students + panels | Yes | Wizard steps 1–2 (currently **first** steps) |
| Reviewers | Yes | Wizard step 3 |
| Rubric criteria + confirm | Yes | Wizard step 4 (`RubricsPanel`) |

**Root cause:** Epic 3 stories (3.3–3.6) implemented **Students → Panels → Reviewers** only. Review round **entity** creation was deferred to Epic 4 (`RubricsPanel`), so coordinators cannot declare how many marking rounds exist before roster setup — breaking the project → reviews → roster flow.

**Data model constraint (do not break):**

- `pr_session_students` and `pr_panels` are **session-scoped**, not per `review_id`. All review rounds in a session share the same enrolled students and panel assignments.
- Marks are per `session × review × student × reviewer × criterion` (`pr_marks`).
- “For each review, attach students with panels” in UI terms = configure the session roster once; every review round marks that roster. Do **not** add per-review enrolment tables in this story unless product explicitly requests a schema change.

### Reuse (do not reinvent)

| Asset | Location |
|-------|----------|
| Review CRUD REST | `includes/rest/class-rest-reviews.php` — `GET/POST /sessions/{id}/reviews`, `PUT /sessions/{id}/reviews/{review_id}` |
| Repository | `includes/repositories/ReviewRepository.php` — `create()`, `list_for_session()`, `STATUS_DRAFT` |
| Rubrics UI patterns | `src/coordinator/components/RubricsPanel.jsx` — `handleCreateReview`, list rendering |
| Wizard shell | `src/coordinator/pages/SessionWizard.jsx`, `src/shared/components/WizardNav.jsx` |
| Wizard gates | `includes/rest/class-rest-sessions.php` — `wizard_state()` |
| Tests | `tests/RestReviewsTest.php`, `tests/RestSessionsTest.php` (add wizard-state case) |

### Implementation sketch

1. **`ReviewRoundsStep`** — props: `sessionId`, `reviews`, `onReload`, `onNotice`. Fetch `GET /sessions/{id}/reviews` on mount (or receive from parent `loadAll`). Primary actions: Add review (default label `Review N`), inline rename, delete only when `status === draft` and no marks (check existing REST rules / add guard if missing).

2. **`SessionWizard.loadAll`** — also fetch reviews list (or rely on step-local fetch). Include `review_count` from `wizard-state`.

3. **`blockedSteps`** — when `review_count === 0`, block `students`, `panels`, `reviewers`, `rubrics` with message: “Add at least one review round first.”

4. **`completedSteps`** — mark `reviews` complete when `review_count >= 1`.

5. **`RubricsPanel`** — keep criteria/confirm/weights; consider removing duplicate “Add review round” button or linking it to wizard Reviews step for consistency.

### UX / copy

- Reviews step headline: **“Review rounds”**
- Subcopy: “Define how many times students will be marked (e.g. Review 1, Review 2). Students and panels in the next steps apply to all rounds.”
- Use `StatusChip` for draft reviews; do not require criteria on this step.

### Testing

- `wizard_state` returns correct flags for 0 vs 1+ reviews.
- Wizard cannot navigate to Students when `review_count === 0`.
- Creating two reviews via REST still allows single shared enrolment list (regression: existing session student tests pass).

### References

- [Source: _bmad-output/planning/epics.md — FR10, Epic 3, UX flow]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §2 Concepts, §5.1 Tables]
- [Source: _bmad-output/planning/ux-design-specification.md — UX-DR9 WizardNav, session wizard hero flow]
- [Source: _bmad-output/implementation/3-3-wizard-students-enrolment.md]
- [Source: _bmad-output/implementation/4-2-rubric-builder-ui.md]

## Dev Agent Record

### Agent Model Used

(create-story workflow)

### Debug Log References

- 2026-05-17: Confirm rubric dialog broken — `ReviewRubricBlock` portaled `ConfirmDialog` to `document.body` outside `#pr-root`, so Tailwind (`important: '#pr-root'`) never applied `fixed`/`z-index`. Fixed by portaling inside `ConfirmDialog` to `#pr-root` at `z-[150]` (above topbar).

### Completion Notes List

- Added Reviews as first wizard step (`ReviewRoundsStep`) with add/rename/reorder/delete (draft, no marks).
- Extended `wizard_state` with `review_count` and `can_advance_to_students`; gates later steps.
- `DELETE /sessions/{id}/reviews/{review_id}` and `sort_order` on PUT for reorder.
- Rubrics wizard step defers review creation to Reviews step (no duplicate create UI in compact mode).
- Dashboard deep-links new sessions to `?step=reviews`.

### File List

- `src/coordinator/components/ReviewRoundsStep.jsx` (new)
- `src/coordinator/pages/SessionWizard.jsx`
- `src/coordinator/pages/Dashboard.jsx`
- `src/coordinator/components/RubricsPanel.jsx`
- `src/shared/components/WizardNav.jsx`
- `includes/rest/class-rest-sessions.php`
- `includes/rest/class-rest-reviews.php`
- `includes/repositories/ReviewRepository.php`
- `tests/RestSessionsTest.php`
- `tests/RestReviewsTest.php`
- `src/shared/components/ConfirmDialog.jsx`
- `build/coordinator.js` (built)

### Change Log

- 2026-05-17: Story created to close wizard gap — review round creation before students/panels (Epic 3.7).
- 2026-05-17: Implemented Reviews wizard step, wizard_state gates, DELETE review endpoint.
- 2026-05-17: Fixed rubric Confirm dialog (portal target + z-index above topbar).
