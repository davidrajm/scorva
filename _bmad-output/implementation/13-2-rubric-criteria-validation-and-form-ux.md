# Story 13.2: Rubric criteria validation and form UX

Status: review

<!-- Ultimate context engine analysis completed — zero max_marks save gap, RubricTable layout, total marks, notice styling -->

## Story

As a **review coordinator**,
I want **rubric criteria saves rejected when any criterion has zero max marks**, **clear spacing and button styling on the criteria table**, **a visible total marks sum per review round**, and **the pre-scoring helper as plain description text**,
so that **invalid rubrics never persist**, the **Rubric criteria & weights** section is easy to scan, and I **know each round’s marking scale at a glance**.

## Background — current behaviour (do not guess)

### Where this UI appears

| Surface | Path / component | Rubric card |
|--------|-------------------|-------------|
| Standalone Reviews hub | `#/session/:id/reviews` → [`Reviews.jsx`](../../src/coordinator/pages/Reviews.jsx) → [`RubricsPanel`](../../src/coordinator/components/RubricsPanel.jsx) | One [`ReviewRubricBlock`](../../src/coordinator/components/ReviewRubricBlock.jsx) per review inside bordered card (`p-4`) |
| Wizard — Rubric criteria & weights | [`ReviewsSetupStep.jsx`](../../src/coordinator/components/ReviewsSetupStep.jsx) section heading **Rubric criteria & weights** | Same `RubricsPanel` with `compact` + `hideRoundActions` |

Both paths render **`ReviewRubricBlock` → `RubricTable`** for each review where rounds exist.

### Validation gap (root cause)

| Layer | Behaviour today | Problem |
|-------|-----------------|--------|
| **Confirm** | [`ReviewRubricBlock.validateCriteriaPayload`](../../src/coordinator/components/ReviewRubricBlock.jsx) requires ≥1 row with `label` trim + `max_marks > 0` | OK for Confirm only |
| **Save payload** | [`RubricTable.payloadCriteria`](../../src/coordinator/components/RubricTable.jsx) maps all rows, then **`.filter(row => row.label !== '')`** — **does not exclude `max_marks <= 0`** | Labeled rows with **0 max marks are persisted** if at least one other row is valid (Save passes `validateCriteriaPayload`) |
| **REST save** | [`Rest_Reviews::save_criteria`](../../includes/rest/class-rest-reviews.php) calls `replace_criteria()` with **no server-side max_marks check** | API accepts zero max marks |
| **Confirm (server)** | [`RubricLifecycleService::assert_valid_criteria`](../../includes/services/RubricLifecycleService.php) rejects empty label or `max_marks <= 0` | Only on **confirm**, not on **save** |
| **Repository** | [`ReviewRepository::replace_criteria`](../../includes/repositories/ReviewRepository.php) skips empty labels but **writes `max_marks` as provided** (including 0) | Data layer allows invalid criteria |

**User report:** “marks are saved even with zero marks” = **criterion `max_marks` of 0** (not reviewer score marks). Fix Save + server guard; align payload builder with confirm validation.

### UX gaps

1. **Table inputs** — criterion / max marks `<input>` use `w-full` / `w-24` inside `<td className="py-2 pr-3">` with no horizontal inset; in the bordered review card they read as **flush against the card/table edge**.
2. **Add criterion** — [`RubricTable`](../../src/coordinator/components/RubricTable.jsx) uses `<Button variant="ghost" size="sm">` with **no `icon`**; reads as a text link, unlike **Add review round** (`variant="secondary"` / `primary` elsewhere).
3. **Total marks** — not shown; coordinators must mentally sum `max_marks` per round.
4. **Pre-scoring notice** — when `confirmed && !has_marks && criteria_editable`, copy uses `<Notice variant="info">` (border, `px-4 py-3`, tinted background per [`Notice.jsx`](../../src/shared/components/Notice.jsx)). Product wants **description-only** styling (`text-sm text-text-muted`, no Notice chrome).

## Acceptance Criteria

1. **Save rejects zero max marks (client)** — **Given** criteria are editable **When** the coordinator clicks **Save** or **Confirm** **Then** the client blocks the request if **any row with a non-empty label** has `max_marks` missing, not numeric, or **≤ 0** **And** shows a clear error (e.g. “Each criterion needs max marks greater than zero.”) **And** **no row with `max_marks <= 0`** is included in the PUT payload (empty-label draft rows may still be omitted).

2. **Save rejects zero max marks (server)** — **Given** `PUT /sessions/{id}/reviews/{review_id}/criteria` **When** the body includes any criterion with non-empty label and `max_marks <= 0`, or no valid criterion remains **Then** REST returns **400** with a stable error code/message aligned with confirm validation **And** existing criteria are **not** partially replaced with invalid rows.

3. **Confirm regression** — **Given** valid criteria (all labeled rows have `max_marks > 0`) **When** Confirm runs **Then** behaviour unchanged (`RubricLifecycleService`, emails, `criteria_editable` / `has_marks` locks).

4. **Criteria form layout** — **Given** an editable `RubricTable` **When** rendered inside the review rubric card (standalone and wizard compact) **Then** text and number inputs have **consistent horizontal spacing** from the table/card edges (e.g. cell padding or input margin matching coordinator form patterns) **And** the table does not look clipped by `TableScrollWrapper`.

5. **Add criterion control** — **Given** editable criteria **Then** **Add criterion** uses **`variant="secondary"`** (or `primary` if design tokens demand a single primary per card—prefer **secondary** because Confirm remains primary in the action row) **And** includes a **plus** icon via `Button` `icon` prop **And** label remains “Add criterion”.

6. **Total marks per review** — **Given** a review rubric card is shown **When** criteria exist (from API or current editable rows) **Then** the UI displays **Total marks: {sum}** (sum of `max_marks` for all criteria with label + `max_marks > 0`; while editing, sum **live rows** the same way) **And** the value updates after Save reload **And** when no valid criteria, show **Total marks: —** or **0** with muted helper copy.

7. **Pre-scoring helper as description** — **Given** `showPreMarkNotice` (confirmed, no `has_marks`, still `criteria_editable`) **When** the helper is shown **Then** text remains: “Marking is open; criteria remain editable until a score is saved.” **And** it is rendered as **plain description** (`<p className="text-sm text-text-muted">` or equivalent)—**not** `<Notice variant="info">`.

8. **Surfaces & regression** — **Given** wizard **Rubric criteria & weights** and sidebar **Reviews** **When** changes ship **Then** both show the same `RubricTable` behaviour **And** `composer test` adds/extends cases for invalid criteria save on REST **And** `npm run build` passes.

## Tasks / Subtasks

- [x] **Validation — shared rules** (AC: 1, 2, 3)
  - [x] Extract or reuse one validation helper (JS): every non-empty label row must have `max_marks > 0`; at least one such row to save/confirm.
  - [x] Update `payloadCriteria` to filter `max_marks > 0` (and non-empty label) before PUT.
  - [x] In `Rest_Reviews::save_criteria`, validate before `replace_criteria` (reuse `RubricLifecycleService` private logic via new package-private/static helper or duplicate minimal check—prefer **single PHP validator** used by save + confirm).
  - [x] Extend `RestReviewsTest` (and/or repository test): PUT with `{ label: 'X', max_marks: 0 }` → 400; valid save unchanged.

- [x] **RubricTable UX** (AC: 4, 5, 6, 7)
  - [x] Input spacing in table cells (criterion + max marks columns).
  - [x] Add `plus` path to [`NavIcon.jsx`](../../src/shared/components/NavIcon.jsx) `ICONS` if missing; wire `Add criterion` button.
  - [x] Compute and display total marks in card header (non-embedded and embedded titles).
  - [x] Replace pre-mark `Notice` with muted description paragraph.

- [x] **Verification** (AC: 8)
  - [x] Manual: Save with one criterion max 0 → blocked client + server; Save all valid → persists; total updates.
  - [x] Manual: confirmed / no marks notice is plain text.
  - [x] `./vendor/bin/phpunit` + `npm run build`.

## Dev Notes

### Technical requirements

- **Terminology:** User-facing “review round” / “review”; code paths still `review_id`, `sessions`.
- **Do not change** when criteria lock (`criteria_editable`, `has_marks`, unlock flow)—Story 4.6 / 13.1 behaviour stands.
- **Total marks definition:** Simple **sum of criterion `max_marks`** for the round (not weighted; weights are separate `WeightConfiguration`). Format with same decimal display as elsewhere (integers without unnecessary `.0` optional).
- **Icon:** Add Heroicons-style `plus` to `ICONS` in `NavIcon.jsx` (match stroke width 1.75); use `icon="plus"` on Add criterion `Button`.

### Architecture compliance

- Coordinator SPA: React + `@wordpress/element`, shared `Button` / `Notice` / `Card`.
- REST remains authoritative; server validation is required even when client validates (defense in depth).
- Minimal diff: prefer adjusting `RubricTable.jsx`, `ReviewRubricBlock.jsx`, `class-rest-reviews.php`, `RubricLifecycleService.php` (or small `CriteriaValidator` in `includes/services/` only if duplication is ugly).

### File structure (expected touch sets)

- [`src/coordinator/components/RubricTable.jsx`](../../src/coordinator/components/RubricTable.jsx) — primary UI
- [`src/coordinator/components/ReviewRubricBlock.jsx`](../../src/coordinator/components/ReviewRubricBlock.jsx) — validation messaging
- [`src/shared/components/NavIcon.jsx`](../../src/shared/components/NavIcon.jsx) — `plus` icon
- [`includes/rest/class-rest-reviews.php`](../../includes/rest/class-rest-reviews.php) — `save_criteria`
- [`includes/services/RubricLifecycleService.php`](../../includes/services/RubricLifecycleService.php) — shared assert (optional extract)
- [`tests/RestReviewsTest.php`](../../tests/RestReviewsTest.php)
- `build/coordinator.*` after `npm run build`

### Testing requirements

- PHPUnit: `PUT .../criteria` with zero `max_marks` → 400; valid multi-row save still 200.
- No new E2E required; manual matrix on both Reviews route and wizard Rubric criteria & weights section.

### Previous story intelligence (13.1)

- Unified **Reviews** hub and wizard **`ReviewsSetupStep`** use the same `RubricsPanel` / `ReviewRubricBlock`; fix once in `RubricTable` covers both.
- Review cards use `rounded-lg border … p-4` wrapper—spacing fix should account for **compact** `embedded` mode (wizard nested layout).

### Risks / edge cases

- **Partial rows:** Coordinator leaves blank label + empty max marks → still omit from payload; do not treat as “zero max marks” error.
- **All rows zero except blanks:** Should fail “at least one criterion” like confirm today.
- **Read-only table:** Total marks still shown from `review.criteria` when not editable.
- **Confirm with pending criteria:** `ReviewRubricBlock` already persists `pendingCriteria` before confirm—ensure that path uses the same stricter validation.

## References

- [Source: `_bmad-output/implementation/4-6-per-review-rubric-until-scoring.md` — `criteria_editable`, pre-mark notice intent]
- [Source: `_bmad-output/implementation/4-2-rubric-builder-ui.md` — `RubricTable` baseline]
- [Source: `_bmad-output/implementation/13-1-coordinator-reviews-rubrics-navigation-consolidation.md` — Reviews hub + `ReviewsSetupStep` section **Rubric criteria & weights**]
- [Source: `_bmad-output/planning/epics.md` — FR10 criterion `max_marks`; UX-DR21 form patterns]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

_(none)_

### Completion Notes List

- Added `src/shared/rubricCriteria.js` with `validateCriteriaRows`, `buildCriteriaPayload`, and `sumCriteriaMaxMarks` so Save/Confirm validate raw rows before filtering (fixes zero max_marks slipping through when another row was valid).
- `RubricLifecycleService::assert_valid_criteria_rows()` shared by confirm and `Rest_Reviews::save_criteria` (400 `pr_invalid_criteria`, no partial replace).
- `RubricTable`: `px-3` inputs, cell `px-1`, total marks in header, secondary **Add criterion** with `plus` icon, pre-mark copy as muted `<p>`.
- PHPUnit: `test_save_criteria_rejects_zero_max_marks`, `test_save_criteria_rejects_labeled_zero_without_partial_replace`. Full suite 306 tests OK; `npm run build` OK.

### File List

- src/shared/rubricCriteria.js (new)
- src/coordinator/components/RubricTable.jsx
- src/coordinator/components/ReviewRubricBlock.jsx
- src/shared/components/NavIcon.jsx
- includes/services/RubricLifecycleService.php
- includes/rest/class-rest-reviews.php
- tests/RestReviewsTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php

## Change Log

- 2026-05-20: Rubric criteria zero max_marks blocked client/server; RubricTable UX (spacing, total marks, Add criterion button, plain pre-mark helper).

---

**Story completion status:** review — validation, layout, total marks, and notice styling on per-review rubric cards under **Rubric criteria & weights**.
