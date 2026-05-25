# Story 4.6: Per-review rubric setup in wizard — editable until scoring starts

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want to define and edit rubric criteria **for each review round** in session setup, including when I return to the wizard after assigning reviewers,
So that every review has the right criteria before marking opens, and criteria cannot change once reviewers have started scoring.

## Acceptance Criteria

1. **Given** a project with one or more review rounds (`pr_reviews`) **When** the coordinator opens the wizard **Rubrics** step (or standalone `#/session/:id/rubrics`) **Then** they see one rubric editor (`RubricTable`) **per review round** (not a single session-wide rubric) **And** each table shows that round’s label, criteria rows, and lifecycle status chip.

2. **Given** the coordinator completed **Reviewers** (or any earlier wizard step) **When** they navigate back to **Rubrics** via wizard nav or **Continue to Rubrics** **Then** criteria for each review in `draft` or `unlocked` status remain editable (label, max_marks, weight) **And** **Save** persists via `PUT /sessions/{id}/reviews/{review_id}/criteria` **And** they can return to Reviewers or other completed steps and come back without losing draft criteria.

3. **Given** a review rubric is still in setup **When** the coordinator has not yet opened marking for that round **Then** they may add, edit, and remove criteria rows freely **And** **Confirm** remains the explicit action that opens marking for that review (`marking_allowed` / `RubricLifecycleService::is_marking_allowed`).

4. **Given** a review rubric is **confirmed** and **no marks** exist yet for that review (`has_marks === false`) **When** the coordinator adjusts criteria **Then** saves are allowed (post-confirm typo fixes before anyone scores) **And** reviewers still cannot save marks until rubric is confirmed (unchanged guard).

5. **Given** at least one mark row exists for a review (`has_marks === true`) — scoring has started **When** the coordinator attempts to save criteria **Then** REST returns `409` with code `pr_rubric_locked` **And** `RubricTable` is read-only unless the rubric was **unlocked** via the existing unlock → edit → re-confirm flow (keep_flag / clear) **And** error copy references that scoring has started, not merely that the rubric was confirmed.

6. **Given** the session wizard **When** prerequisites for rubrics are met (roster + review rounds + all students panel-assigned) **Then** the coordinator can reach the Rubrics step via **Continue to Rubrics** from Reviewers **And** `WizardNav` allows clicking **Rubrics** when the step is not blocked (not only when it was previously “completed”) **And** `GET /sessions/{id}/wizard-state` exposes `can_advance_to_rubrics` for UI gates.

7. **Given** PHPUnit and front-end build **When** implementation is complete **Then** tests cover: criteria save allowed for `confirmed` + no marks; criteria save rejected for `confirmed` + `has_marks` without unlock; wizard-state flag; regression on confirm/unlock/re-confirm (`RubricLifecycleServiceTest`, `RestReviewsTest`) **And** `composer test` + `npm run build` pass.

## Tasks / Subtasks

- [x] Add `criteria_editable` (or equivalent) to `format_review()`; align `review_is_editable()` with AC4–5 (`has_marks` lock)
- [x] Update `RubricTable` / `RubricsPanel` to use API `criteria_editable` (or `has_marks` + status) for read-only state and helper copy
- [x] Fix wizard navigation: `can_advance_to_rubrics` in `wizard_state`; **Continue to Rubrics** on Reviewers step; `WizardNav` click rules for accessible steps
- [x] Rubrics step intro copy: per-review criteria; editable until scoring starts; Confirm opens marking
- [x] Extend `RestReviewsTest` for confirmed/no-marks vs confirmed/has-marks criteria saves; wizard-state test in `RestSessionsTest`
- [x] Run `composer test` and `npm run build`

## Dev Notes

### Product intent (user request)

Coordinators configure marking **per review round** (Review 1, Review 2, …). They must be able to:

1. Set criteria for each review in setup.
2. Leave the wizard (e.g. after provisioning reviewers), return later, and keep editing criteria.
3. **Confirm** when ready to open that round for marking.
4. **Stop** criteria edits once **scoring has started** (any mark saved for that review), except via the governed unlock / re-confirm path.

“Opening for scoring” = **Confirm** (review status → `confirmed`, reviewers may mark).  
“Scoring started” = **`has_marks`** on that review (`ReviewRepository::count_marks_for_review` > 0).

### Gap analysis (current code vs intent)

| Area | Today | Gap |
|------|--------|-----|
| Per-review criteria | `RubricsPanel` maps `reviews` → `RubricTable` | Largely done; ensure copy and API flags are explicit |
| Criteria lock | `review_is_editable()` = `draft` \| `unlocked` only | **`confirmed` locks criteria even with zero marks** — user wants lock on **`has_marks`**, not on confirm alone |
| Wizard → Rubrics | No **Continue** from Reviewers; `rubrics` never in `completedSteps` | **Cannot reach Rubrics via nav** from Reviewers (only URL `?step=rubrics`) |
| Wizard-state | No `can_advance_to_rubrics` | Add flag: enrolled + review_count + unassigned === 0 |

### Lock rule (implement consistently in PHP + React)

```text
criteria_editable(review) =
  status ∈ { draft, unlocked }
  OR (status === confirmed AND NOT has_marks)

criteria_locked(review) =
  has_marks AND status === confirmed   → require Unlock first
  (unlocked always editable until re-confirm — existing flow)
```

**Do not** change when reviewers may mark: still requires `status === confirmed` and session not `closed` (`MarkService`, `RubricLifecycleService::is_marking_allowed`).

**Do not** remove unlock / re-confirm / keep_flag / clear — only narrow **when** confirm alone blocks criteria edits.

### What already exists (reuse)

| Asset | Location |
|-------|----------|
| Per-review criteria REST | `PUT .../reviews/{review_id}/criteria` — `Rest_Reviews::save_criteria` |
| Lifecycle | `RubricLifecycleService`, confirm/unlock/re-confirm |
| UI | `RubricsPanel.jsx`, `RubricTable.jsx`, wizard `?step=rubrics` |
| Marks guard | `MarkService::validate_save_context` — rubric confirmed |
| `has_marks` on review payload | `format_review()` — already returned |

### Implementation sketch

1. **`Rest_Reviews::review_is_editable()`** — implement rule above; update `pr_rubric_locked` message to mention scoring started when `has_marks`.

2. **`format_review()`** — add boolean `criteria_editable` for UI (derived from same helper).

3. **`RubricTable`** — `editable = review.criteria_editable ?? (legacy status check)`; when `confirmed && !has_marks`, show subtle Notice: “Marking is open; criteria remain editable until a score is saved.”

4. **`SessionWizard`**
   - `wizard_state`: `can_advance_to_rubrics` = same predicate as reviewers gate (enrolled, reviews, panels assigned).
   - `PanelReviewersStep`: accept `onContinue` → **Continue to Rubrics** (mirror Panels → Reviewers).
   - `WizardNav`: allow step click when `!blockedSteps[step]` AND (`completedSteps` includes step OR step is current OR step is target and prerequisites met). Simplest fix: treat `rubrics` as reachable when `!blockedSteps.rubrics` (not only when “complete”).

5. **Optional (out of scope unless trivial):** inline “Add criterion” on `ReviewRoundsStep` — not required; Rubrics step is the criteria home.

### Anti-patterns

- Do not add session-wide rubric criteria (criteria must stay on `review_id`).
- Do not allow criteria edits when `has_marks` without going through **Unlock** (no silent `replace_criteria`).
- Do not block Confirm when criteria valid — Confirm still opens marking.
- Do not fetch marks per criterion on every wizard render (use `has_marks` on review list payload).

### Testing

- `confirmed` + 0 marks → `PUT criteria` → 200.
- `confirmed` + marks → `PUT criteria` → 409 `pr_rubric_locked`.
- `unlocked` + marks → `PUT criteria` → 200 (then re-confirm path unchanged).
- `wizard_state` includes `can_advance_to_rubrics: true` when panels assigned.
- Front-end: manual — Reviewers → Continue → Rubrics → back to Reviewers → return to Rubrics, criteria still editable in draft.

### Design spec note

Design spec §5.3 step 2 says confirm then reviewers may mark; it does not explicitly say criteria lock before first mark. This story **intentionally** allows post-confirm, pre-mark criteria edits. If product prefers lock-on-confirm, drop AC4 and keep only `has_marks` lock after unlock — but that matches old behaviour and not the user’s “scoring is starting” wording.

### References

- [Source: _bmad-output/planning/epics.md — FR10, FR11, Epic 4]
- [Source: themes/david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — §5.3 Rubric lifecycle]
- [Source: _bmad-output/implementation/4-1-rubric-lifecycle-rest.md]
- [Source: _bmad-output/implementation/4-2-rubric-builder-ui.md]
- [Source: _bmad-output/implementation/4-3-rubric-confirm-dialog.md]
- [Source: _bmad-output/implementation/3-7-wizard-review-rounds.md]
- [Source: includes/rest/class-rest-reviews.php — `review_is_editable`, `save_criteria`]
- [Source: src/coordinator/pages/SessionWizard.jsx — wizard gates]

## Dev Agent Record

### Agent Model Used

(create-story workflow)

### Debug Log References

### Completion Notes List

- Criteria lock now follows `has_marks`: `draft`/`unlocked` always editable; `confirmed` editable only until first mark; `409 pr_rubric_locked` mentions scoring when marks exist.
- `format_review()` exposes `criteria_editable` for React; `RubricTable` uses it with pre-mark notice on confirmed rubrics.
- Wizard: `can_advance_to_rubrics` in `wizard_state`, **Continue to Rubrics** on Reviewers, `WizardNav` allows any unblocked step.
- PHPUnit: 3 new `RestReviewsTest` cases + extended `RestSessionsTest` wizard gate; 114 tests pass; `npm run build` OK.

### File List

- includes/rest/class-rest-reviews.php
- includes/rest/class-rest-sessions.php
- src/coordinator/components/RubricTable.jsx
- src/coordinator/components/RubricsPanel.jsx
- src/coordinator/components/PanelReviewersStep.jsx
- src/coordinator/pages/SessionWizard.jsx
- src/shared/components/WizardNav.jsx
- tests/RestReviewsTest.php
- tests/RestSessionsTest.php
- build/coordinator.js
- build/coordinator-rtl.css
- build/coordinator.asset.php
- build/reviewer.js
- build/reviewer-rtl.css
- build/reviewer.asset.php

### Change Log

- 2026-05-17: Story created from coordinator request — per-review rubric setup, wizard return navigation, criteria lock when scoring starts (`has_marks`).
- 2026-05-17: Implemented criteria_editable API, wizard rubrics navigation, and tests.
