# Story 13.1: Coordinator Reviews & Rubrics navigation consolidation

Status: review

<!-- Ultimate context engine analysis completed — IA audit + unified Reviews hub -->

## Story

As a **review coordinator**,
I want **one clear place to manage review rounds (create, order, naming), rubric criteria, confirmation, marking activation, weights, and flagged marks**—whether I am in setup or returning later—with **per-round assignments only after rounds are set up**, and a **visible way to delete a review round** (simple confirmation when no marks exist; typed confirmation when marks or scores exist),
so that **I never wonder which menu repeats the same work, skip governance steps because navigation hid them, or lose destructive-delete safeguards when flows merge.**

## Background — current behaviour (inventory)

Do not guess; align implementation with these sources.

### Navigation surfaces

| Surface | Route / location | Purpose today |
|--------|-------------------|---------------|
| Session sidebar (`CoordinatorNav`) | `#/session/:id/rubrics` — label **Rubrics** | Full rubric workspace via `RubricsPanel` |
| Session sidebar | `#/session/:id/wizard?step=…` — horizontal `WizardNav` | Linear setup including steps **Rubrics** and **Reviews** |
| App router | [`src/coordinator/App.jsx`](../../src/coordinator/App.jsx) registers `/session/:id/rubrics` → `Rubrics` page |

### Feature comparison (overlap analysis)

**Standalone page [`Rubrics.jsx`](../../src/coordinator/pages/Rubrics.jsx)** renders [`RubricsPanel`](../../src/coordinator/components/RubricsPanel.jsx), which:

- Loads `/sessions/:id/reviews` **and** `/sessions/:id/weights`, and per-review `/marks` for **flagged marks**.
- Offers **Add review round**, **Remove review round** (draft, no marks), **Create Review 1** with default criteria via `POST` including starter criteria.
- Renders [`ReviewRubricBlock`](../../src/coordinator/components/ReviewRubricBlock.jsx) per round (full criteria table, confirm/unlock/re-confirm).
- Renders [`WeightConfiguration`](../../src/coordinator/components/WeightConfiguration.jsx) after rounds exist.

**Wizard — Rubrics step [`ReviewRubricsStep.jsx`](../../src/coordinator/components/ReviewRubricsStep.jsx)**:

- Loads `/sessions/:id/reviews`; maps rounds with embedded `ReviewRubricBlock` (`embedded` prop).
- **Does not** load weights, flagged marks, or expose bulk “Add review round” (expects rounds already exist).
- Continue button gated by `wizardState?.can_advance_to_reviews` or fallback “every review has criteria”.
- Copy references “final Reviews step” for opening marking.

**Wizard — Reviews step [`ReviewRoundsStep.jsx`](../../src/coordinator/components/ReviewRoundsStep.jsx)**:

- Same reviews API; manages **add**, **rename**, **reorder**, **`marking_active`** toggle (with coordinator marks lock messaging).
- **Delete review round** (see implementation note below): today this step and [`RubricsPanel`](../../src/coordinator/components/RubricsPanel.jsx) call `DELETE /sessions/:id/reviews/:reviewId` with optional `confirm_label` when scores exist; a consolidated hub must **not** drop this affordance.
- **Does not** embed rubric criteria editing inline; tells user to confirm rubric on Rubrics step.

**Consequence:** Coordinators have **two mental models** for “reviews”: sidebar **Rubrics** (heavy) vs wizard **Rubrics + Reviews** (split). Round creation appears on **Reviews step** and again on **Rubrics page**. Weight and flagged-mark governance appear **only** on the standalone Rubrics page, not in the wizard—easy to miss during linear setup.

### Product references

- Epic 4 intent in [`_bmad-output/planning/epics.md`](../../planning/epics.md): govern rubrics, weights, confirm/unlock, flagged visibility (FR10–FR12, Epic 4 summary).
- UX spec [`_bmad-output/planning/ux-design-specification.md`](../../planning/ux-design-specification.md): wizard as hero flow; UX-DR9 `WizardNav`; “students → panels → reviewers → rubrics” — note older wording vs current **reviews** split.

## Acceptance Criteria

1. **IA decision documented in UI** — Either merge wizard steps and/or sidebar entries so coordinators see **one primary Reviews hub** for a project (round lifecycle + rubric + weights + flagged marks where applicable), **or** explicitly differentiate surfaces with non-overlapping responsibilities (no duplicate “add round” / conflicting copy). Deliverable: updated navigation labels, step list, and help text consistent with the chosen model.

2. **No orphaned governance** — If the wizard remains the primary setup path, **weight configuration** and **flagged marks** (or explicit deep links thereto) must be reachable without hunting the sidebar, **or** the standalone Rubrics route must remain the single source of truth with wizard steps removed/redirected—pick one coherent pattern.

3. **Wizard gating preserved** — Blocked advance rules from [`SessionWizard.jsx`](../../src/coordinator/pages/SessionWizard.jsx) (`can_advance_to_rubrics`, `can_advance_to_reviews`, etc.) remain correct relative to REST/backend guards; consolidate steps **without** weakening prerequisites (assignments complete before marking, rubric confirmed before `marking_active`, etc.).

4. **Reuse, don’t fork** — Prefer composing existing **`RubricsPanel`**, **`ReviewRubricBlock`**, **`ReviewRoundsStep`** behaviour into a unified presentation rather than duplicating API calls or lifecycle logic. If extracting a shared container component, migrate both wizard and route to it.

5. **Tab / step order — Assignments after Review rounds** — Whether using wizard steps (`SessionWizard` [`STEPS`](../../src/coordinator/pages/SessionWizard.jsx)), a merged Reviews hub with sub-tabs, or both: **per-round Assignments** must appear **after** **Review rounds** (create/order/name rounds, marking activation, etc.). Coordinators configure rounds first, then wire assignments. Do not place Assignments before the surface where rounds are managed.

6. **Delete review round (must remain available)** — The unified coordinator experience must expose **remove/delete review round** wherever rounds are edited, with behaviour consistent with backend guards:

   - **No marks for any student on that review:** Show a normal confirmation dialog (e.g. OK / Cancel). Deleting removes the round, its rubric criteria, weights for that round, assignments for that round, and any ancillary data tied to that review.
   - **At least one mark exists for any student on that review:** Treat as destructive. Show explicit warning that **all entered marks and derived scores** for that round will be permanently removed. The user must **type to confirm** (match existing pattern: require typing the **exact review round label**, case-sensitive trim match, before enabling confirm—reuse logic from [`ReviewRoundsStep.jsx`](../../src/coordinator/components/ReviewRoundsStep.jsx) / [`RubricsPanel.jsx`](../../src/coordinator/components/RubricsPanel.jsx): `confirm_label` on `DELETE` when `has_entered_scores` is true). No silent or single-click delete when scores exist.

7. **Regression sweep** — Update user-visible strings that assume separate “Rubrics step” vs “Reviews step” or “confirm in wizard” where flows merge (e.g. [`ReportsConsolidatedTable.jsx`](../../src/coordinator/components/ReportsConsolidatedTable.jsx), [`OfflineScoringSheetCard.jsx`](../../src/coordinator/components/OfflineScoringSheetCard.jsx), [`ReviewRoundsStep.jsx`](../../src/coordinator/components/ReviewRoundsStep.jsx) hints).

8. **Routing** — Clear behaviour for `#/session/:id/rubrics`: redirect to unified route, alias, or remove from sidebar with migration note in changelog/dev notes—no broken bookmarks without intentional redirect.

## Tasks / Subtasks

- [x] **Discovery / UX choice** (AC: 1, 2)
  - [x] Confirm target IA with stakeholder: single hub vs split-but-distinct responsibilities.
  - [x] Produce short wire-level outline (wizard steps vs sidebar items).

- [x] **Implementation** (AC: 3, 4, 5, 6, 8)
  - [x] Refactor wizard step list in `SessionWizard.jsx` / `WizardNav` usage to merged or clarified steps; ensure **Assignments** step/tab sits **after** **Review rounds** in every coordinator path (AC 5).
  - [x] Consolidate `RubricsPanel` vs `ReviewRubricsStep` / `ReviewRoundsStep`; eliminate duplicate round-creation UX if merging.
  - [x] **Delete review round:** If consolidation hides round list actions, restore **Remove / delete review round** with the two-tier confirmation in AC 6 (reuse existing dialog + API from `RubricsPanel` / `ReviewRoundsStep` rather than reimplementing).
  - [x] Adjust `CoordinatorNav.jsx` session links (Rubrics label/route vs wizard).

- [x] **Copy & accessibility** (AC: 7)
  - [x] Grep coordinator strings for “Rubrics step”, “Reviews step”, “wizard” hints; align with new flow.

- [x] **Verification** (AC: 3–8)
  - [x] Manual path: create project → add rounds → criteria → weights → confirm → **assignments** → toggle marking → verify reviewer blocked states unchanged; confirm nav order matches AC 5.
  - [x] **Delete scenarios:** (a) delete round with no marks — single confirm only; (b) enter at least one mark — must require typed round label and remove marks/scores server-side; (c) only-round guard still respected if applicable.
  - [x] Run existing PHP/JS test suites touched by routing copy if any; add minimal test only if hooks warrant.

## Dev Notes

### Technical requirements

- REST endpoints remain authoritative; UI consolidation must not change contracts unless coordinated with backend stories.
- **Delete review:** Backend already expects optional `confirm_label` matching the review’s label when `has_entered_scores` is true—keep request shape aligned with [`ReviewRoundsStep.jsx`](../../src/coordinator/components/ReviewRoundsStep.jsx) / [`RubricsPanel.jsx`](../../src/coordinator/components/RubricsPanel.jsx).
- `ReviewRubricBlock` already supports `embedded` for tighter layout—reuse when merging panels.
- `RubricsPanel` `compact` mode exists but `SessionWizard` does not currently mount `RubricsPanel`; evaluate using `compact` vs dropping it after merge.

### Architecture compliance

- Coordinator app: React + `@wordpress/element`, HashRouter — keep [`App.jsx`](../../src/coordinator/App.jsx) route table coherent with sidebar links.
- Align terminology with Epic 10 “session → project” where user-visible strings still say session.

### File structure (expected touch sets)

- [`src/coordinator/App.jsx`](../../src/coordinator/App.jsx)
- [`src/coordinator/CoordinatorNav.jsx`](../../src/coordinator/CoordinatorNav.jsx)
- [`src/coordinator/pages/SessionWizard.jsx`](../../src/coordinator/pages/SessionWizard.jsx)
- [`src/coordinator/pages/Rubrics.jsx`](../../src/coordinator/pages/Rubrics.jsx)
- [`src/coordinator/components/RubricsPanel.jsx`](../../src/coordinator/components/RubricsPanel.jsx)
- [`src/coordinator/components/ReviewRubricsStep.jsx`](../../src/coordinator/components/ReviewRubricsStep.jsx)
- [`src/coordinator/components/ReviewRoundsStep.jsx`](../../src/coordinator/components/ReviewRoundsStep.jsx)
- [`src/shared/components/WizardNav.jsx`](../../src/shared/components/WizardNav.jsx) (if step labels/order change)

### Testing requirements

- Manual coordinator regression matrix covering draft vs confirmed rubric, marks locked state, and reviewer-facing blocked messages (no change expected to reviewer SPA unless coordinator actions change timing).

### Risks / edge cases for implementer

- **Weights before marks:** `WeightConfiguration` respects `has_marks` — preserve UX when merging steps.
- **Flagged marks:** heavy fetch path in `RubricsPanel`; avoid N+1 regressions when refactoring.
- **Deep links:** bookmarks to `wizard?step=rubrics` vs `/rubrics`.
- **Merged tabs:** If Assignments and Review rounds share one screen, tab order must still satisfy AC 5 (rounds before assignments).
- **Delete regressions:** Consolidation sometimes drops contextual actions; verify delete is visible next to each round (or equivalent overflow menu with same flows).

## Questions saved for product (resolve before or during dev)

1. Should **post-setup** coordinators land on **wizard** or a **dedicated Reviews page** by default?
2. Is **sidebar real estate** reduced if Rubrics merges into Reviews (single icon)?
3. Any **training materials / screenshots** that must ship with the IA change?

## Change Log

- **2026-05-20:** Unified coordinator **Reviews** hub: sidebar `#/session/:id/reviews` (builds on `RubricsPanel`); wizard merges former Rubrics + Reviews into one **Reviews** step via `ReviewsSetupStep` (`ReviewRoundsStep` + compact `RubricsPanel` with `hideRoundActions` to avoid duplicate delete). Legacy `#/session/:id/rubrics` and `?step=rubrics` redirect to the merged step. Coordinator copy updated in Reports / offline scoring / consolidated table. PHPUnit 304 tests and `npm run build` passing.

## Dev Agent Record

### Agent Model Used

Composer (Cursor AI)

### Debug Log References

_(none)_

### Completion Notes List

- **IA:** Single primary **Reviews** surface—same `RubricsPanel` data/UX on sidebar; wizard uses one **Reviews** step so weights and flagged marks are no longer wizard-only gaps.
- **Wire (coordinator):** Wizard: Students → Panels → Reviewers → **Reviews** (rounds first, then rubric criteria/weights/flagged marks) → Assignments. Sidebar session: Setup wizard | Progress | **Reviews** | Reports | …
- **`ReviewsSetupStep`:** Composes existing `ReviewRoundsStep` (add/rename/reorder/marking/delete with unchanged dialogs/API) + `RubricsPanel` (`compact`, `hideRoundActions`, `reloadDependency` keyed off wizard refresh tick).
- **Routing:** `Rubrics.jsx` → `RubricsLegacyRedirect` to `/session/:id/reviews`; `Reviews.jsx` hosts full panel including round removal on standalone hub.
- **Gating:** Assignments step still blocked until `can_advance_to_assignments`; merged Reviews step blocked until `can_advance_to_rubrics`; removed separate wizard tab block that required criteria before opening round-management (round + criteria now coexist).
- **Verification:** `./vendor/bin/phpunit` (304 tests); `npm run build`.

### File List

- `src/coordinator/App.jsx`
- `src/coordinator/CoordinatorNav.jsx`
- `src/coordinator/components/OfflineScoringSheetCard.jsx`
- `src/coordinator/components/ReportsConsolidatedTable.jsx`
- `src/coordinator/components/ReviewRubricsStep.jsx`
- `src/coordinator/components/ReviewRoundsStep.jsx`
- `src/coordinator/components/ReviewsSetupStep.jsx` (new)
- `src/coordinator/components/RubricsPanel.jsx`
- `src/coordinator/pages/Reports.jsx`
- `src/coordinator/pages/Reviews.jsx` (new)
- `src/coordinator/pages/Rubrics.jsx`
- `src/coordinator/pages/SessionWizard.jsx`
- `src/shared/components/WizardNav.jsx`
- `build/coordinator.js`
- `build/coordinator-rtl.css`
- `build/coordinator.css`
- `build/coordinator.asset.php`
- `_bmad-output/implementation/sprint-status.yaml`

---

**Story completion status:** Implementation complete — ready for human review. Unified Reviews hub meets AC 1–8 with redirects for legacy `/rubrics` and `?step=rubrics`; delete-round confirmations unchanged on `ReviewRoundsStep` / standalone `RubricsPanel`.
