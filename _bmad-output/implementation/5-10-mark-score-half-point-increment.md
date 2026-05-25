# Story 5.10: Mark score half-point increment (0.5 step)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer** (and any user who may edit criterion scores),
I want score inputs to increment and validate in **0.5** steps instead of **0.01**,
So that marking matches institutional half-point rubrics and spinner/keyboard entry is faster and less error-prone.

## Acceptance Criteria

1. **Reviewer rubric score entry (`RubricForm`)**
   - **Given** a reviewer opens **Update score** on the marking grid (modal embeds `RubricForm`)
   - **When** they use the numeric control for a criterion score (spinner arrows, step up/down, or constrained entry)
   - **Then** the input uses `step="0.5"` (not `0.01`)
   - **And** `inputMode="decimal"` remains (UX-DR21)
   - **And** min/max (`0` … `max_marks`) behaviour is unchanged

2. **Shared constant — single source of truth**
   - **Given** any current or future UI that edits **student criterion scores** (not rubric builder weights, not panel reviewer weights)
   - **When** implemented or updated in this story
   - **Then** `src/shared/markValidation.js` exports e.g. `MARK_SCORE_STEP = 0.5`
   - **And** `RubricForm.jsx` imports and binds `step={ MARK_SCORE_STEP }` (or string `"0.5"`)
   - **And** future mark-entry surfaces (e.g. admin **mark override** UI from story 9.2 when added) must reuse the same constant — do not duplicate magic numbers

3. **Client validation on blur and save**
   - **Given** a non-empty score value
   - **When** the field blurs or the user saves draft
   - **Then** `validateCriterionScore` rejects values that are not multiples of `0.5` (within floating-point tolerance, e.g. `Math.abs(num * 2 - Math.round(num * 2)) < 1e-6`)
   - **And** the error message is explicit: e.g. “Enter a score in steps of 0.5 (e.g. 3, 3.5, 4).”
   - **And** empty fields remain allowed for partial draft saves (unchanged)
   - **And** `0`, `max_marks`, and values like `2.5` pass when within range

4. **Server guard (API integrity)**
   - **Given** `MarkService` persists marks via REST
   - **When** a score is saved or overridden
   - **Then** `validate_score` (or a dedicated helper) rejects scores that are not multiples of `0.5` with `400` / code `invalid_score` and a clear message
   - **And** `override_mark` uses the same rule
   - **And** existing stored marks that are not 0.5-aligned are **not** migrated — only **new writes** are validated (read/display unchanged)

5. **Out of scope (do not change)**
   - **Panel reviewer weight** inputs (`PanelReviewersStep.jsx`, `step="0.1"`) — weights, not marks
   - **Rubric builder** `max_marks` / criterion **weight** in `RubricTable.jsx` / `WeightConfiguration.jsx` — configuration decimals, not student scores
   - Coordinator **progress** / **reports** tables — read-only display
   - Changing `maximumFractionDigits` display formatting in `MarkingGrid.formatScore` unless product asks (display may still show one decimal place for half points)

6. **Tests and build**
   - **And** add PHPUnit cases in `MarkServiceTest` for valid `2.5`, invalid `2.3`, boundary `0` and `max_marks` when max is half-point aligned
   - **And** optional: small unit tests for `validateCriterionScore` half-step rule (Jest only if project already has JS tests; otherwise manual QA note is enough)
   - **And** run `composer test` and `npm run build`

## Tasks / Subtasks

- [x] **Constant:** Add `MARK_SCORE_STEP` to `src/shared/markValidation.js`
- [x] **UI:** Update `src/reviewer/components/RubricForm.jsx` — `step`, wire validation
- [x] **Validation:** Extend `validateCriterionScore` for 0.5 multiples
- [x] **Server:** Extend `MarkService::validate_score` (or helper) + reuse in `override_mark` path
- [x] **Tests:** `MarkServiceTest` half-step cases
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

> Wherever the mark update option is allowed, the increment option is currently two decimal [0.01], but it should be incremented by **0.5**.

### What exists today

| Location | `step` today | Purpose |
|----------|--------------|---------|
| `src/reviewer/components/RubricForm.jsx` | `0.01` | **Student criterion scores** — **change this** |
| `src/coordinator/components/PanelReviewersStep.jsx` | `0.1` | Reviewer **weights** — leave alone |
| `src/coordinator/components/RubricTable.jsx` | (no step; `inputMode="decimal"`) | Rubric **max_marks / weight** — leave alone |

Mark entry surfaces in the codebase today:

- **Reviewer:** `MarkingGrid` → **Update score** → `ScoreEntryModal` → `RubricForm` (embedded `readOnly` when frozen)
- **Coordinator/admin override UI:** REST exists (`POST /marks/{id}/override`) per story 9.2; if no React override form exists yet, still add server validation and export the shared constant for when that UI lands

There is **no** other `type="number"` score field for student marks in `src/` besides `RubricForm`.

### Implementation sketch

**Frontend**

```javascript
// src/shared/markValidation.js
export const MARK_SCORE_STEP = 0.5;

function isHalfPointMultiple( num ) {
  const scaled = num * 2;
  return Math.abs( scaled - Math.round( scaled ) ) < 1e-6;
}
```

`RubricForm.jsx` (~line 324):

```jsx
step={ String( MARK_SCORE_STEP ) }
```

**Backend** (`includes/services/MarkService.php`):

```php
private const SCORE_STEP = 0.5;

private function is_valid_score_step(float $score): bool
{
    $scaled = $score / self::SCORE_STEP;
    return abs($scaled - round($scaled)) < 1e-6;
}
```

Call from `validate_score` before range checks.

### Edge cases

| Case | Expected |
|------|----------|
| `max_marks = 10` | Valid scores: 0, 0.5, …, 10 |
| `max_marks = 10.5` | 10.5 valid if ≤ max |
| `max_marks = 7` (odd integer max) | 7 and 6.5 valid; 7.25 invalid |
| Draft save with empty criterion | Still allowed |
| Frozen / read-only form | No input; no change |
| Direct REST POST with `2.33` | `400 invalid_score` after server guard |
| Legacy DB row `3.25` | Still displays; editing to new value must be 0.5-aligned |

### Architecture compliance

- **Epic 5** marking funnel; no new routes.
- **UX-DR21:** labels above inputs, blur validation — extend, do not replace.
- **MarkService** remains single write authority; client validation mirrors server (same pattern as `markValidation.js` today).

### Previous story intelligence

From `5-4-rubric-form.md`, `5-6-reviewer-marking-grid-freeze.md`, `5-7-student-attendance-marking.md`:

- All reviewer score entry flows through **`RubricForm`** in the modal (Save-only draft; freeze → read-only).
- `validateCriterionScore` / `validateMarksForSave` in `src/shared/markValidation.js` are the client guard layer.
- Do not reintroduce Submit or full-page funnel.

From `9-2-mark-override.md`:

- Override writes go through `MarkService::override_mark` — must share half-step validation.

### Critical files

| File | Action |
|------|--------|
| `src/shared/markValidation.js` | `MARK_SCORE_STEP`, half-step check in `validateCriterionScore` |
| `src/reviewer/components/RubricForm.jsx` | `step={ MARK_SCORE_STEP }` |
| `includes/services/MarkService.php` | Server step validation in `validate_score` |
| `tests/MarkServiceTest.php` | New cases |
| `build/reviewer.js` | Regenerated via `npm run build` |

### Manual QA checklist

1. Open marking grid → **Update score** → use spinner on a score field: each click changes by **0.5**.
2. Type `4.25` → blur → inline error; Save blocked.
3. Type `4.5` → Save → persists; grid shows updated score.
4. Panel reviewer weight field still steps by **0.1** (wizard Reviewers step).

### References

- [Source: src/reviewer/components/RubricForm.jsx — `step="0.01"`]
- [Source: src/shared/markValidation.js]
- [Source: includes/services/MarkService.php — `validate_score`]
- [Source: _bmad-output/implementation/5-4-rubric-form.md]
- [Source: _bmad-output/implementation/5-6-reviewer-marking-grid-freeze.md]
- [Source: _bmad-output/implementation/9-2-mark-override.md]
- [Source: _bmad-output/planning/epics.md — Epic 5, UX-DR21]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

- PHPUnit via `./vendor/bin/phpunit` (no `composer test` script in composer.json).

### Completion Notes List

- Added `MARK_SCORE_STEP = 0.5` and `isHalfPointMultiple` check in `validateCriterionScore` with explicit error message.
- `RubricForm` uses `step={ String( MARK_SCORE_STEP ) }`; blur/save validation unchanged path via shared helper.
- `MarkService::validate_score` calls new `is_valid_score_step()` before range checks; `override_mark` inherits via existing `validate_score` call.
- Four new PHPUnit cases: accept 2.5, reject 2.3, boundaries 0/10, override reject 2.3.
- `npm run build` succeeded; 182 PHPUnit tests pass.

### File List

- `src/shared/markValidation.js`
- `src/reviewer/components/RubricForm.jsx`
- `includes/services/MarkService.php`
- `tests/MarkServiceTest.php`
- `build/reviewer.js`
- `build/reviewer.asset.php`

## Change Log

- 2026-05-17: Half-point (0.5) mark score step on client and server; RubricForm spinner step; MarkServiceTest coverage.
