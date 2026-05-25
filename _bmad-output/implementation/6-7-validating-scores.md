# Story 6.7: Validating scores (golden fixtures & parity)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator** or **developer**,
I want score totals validated with documented golden scenarios and automated tests,
So that reviewer totals, review scores, and combined scores are trustworthy and match UI, REST, and exports.

## Acceptance Criteria

1. **Given** a single confirmed review with two criteria (`max_marks` used only for mark entry validation, **not** for aggregation) and three panel reviewers (reviewer `weight` = 1 each) **When** submitted marks are:

   | Reviewer | Criterion 1 | Criterion 2 |
   |----------|-------------|-------------|
   | 1        | 5           | 5           |
   | 2        | 5           | 9           |
   | 3        | 4           | 5           |

   **Then** `ScoreService` returns:

   | Level | Field | Expected |
   |-------|-------|----------|
   | 1 | Reviewer 1 total | **10.00** |
   | 1 | Reviewer 2 total | **14.00** |
   | 1 | Reviewer 3 total | **9.00** |
   | 2 | Review score (student) | **11.00** |
   | 3 | Combined score (only this review) | **11.00** |

   **And** each **reviewer total** is the **raw sum** of submitted criterion scores for that reviewer (e.g. 5+5 = **10**, not a percentage).

   **And** the **review score** is the **weighted average of reviewer totals** for that student; reviewer weights default to **1** when unset (`(10+14+9)/3 = 11.00`).

   **And** **rubric criterion weights are not used** in any score calculation and are **removed from coordinator rubric configuration UI** (no weight column on criteria; existing `weight` column in DB to be droped and should be ignored by `ScoreService`).

2. **Given** the same fixture **When** `GET /project-reviews/v1/sessions/{id}/students/{student_id}/scores` (or breakdown endpoint used by `ScoreBreakdown`) **Then** JSON numbers match the table in AC1 to two decimal places.

3. **Given** reports `scores-matrix` for that session/review **When** the coordinator loads Overall scores / weighted review column **Then** the student’s review score equals **11.00** (same `ScoreService::calculate_review_score()` — do not duplicate formula in React).

4. **Given** `ScoreServiceTest` **When** `composer test` runs **Then** golden and existing level-1/2 tests use the **raw-sum + reviewer-weight** model and pass.

5. **Given** optional manual validation **When** a maintainer runs SQL or seed script **Then** `tests/sql/validate_scores_golden.sql` documents expected reviewer sums and review score for one student.

## Tasks / Subtasks

- [x] **Change `ScoreService::calculate_reviewer_total`** — sum submitted criterion `score` values only; do not normalize by `max_marks`; ignore `criterion['weight']` (AC: 1)
- [x] **Keep `calculate_review_score`** — weighted average of reviewer totals by panel/override reviewer weights (default 1) (AC: 1)
- [x] **Update `ScoreService` docblock** and design-spec cross-reference in code comments to describe new Level 1 formula (AC: 1)
- [x] **Remove criterion weight from rubric UI** — `RubricTable.jsx`: drop weight column; API still stores weight default 1, ignored in scoring (AC: 1)
- [x] **Update existing `ScoreServiceTest` expectations** (AC: 4)
- [x] Add `test_golden_three_reviewers_two_criteria_equal_weights` (AC: 1, 4)
- [x] Reports use server `review_score` via `ScoreService` / `ReportsViewService` (AC: 3)
- [x] Add `tests/sql/validate_scores_golden.sql` (AC: 5)
- [x] `./vendor/bin/phpunit` (275 tests) + `npm run build`

## Dev Notes

### Scoring model (replace current % normalization)

Canonical implementation: `includes/services/ScoreService.php`.

**Level 1 — reviewer total** (one reviewer, one student, one review):

```
reviewer_total = round( Σ(submitted criterion scores), 2 )
```

- Include only marks with `status = submitted` (existing `submitted_only` default).
- Skip null scores.
- **`max_marks` is not used** in aggregation (only for entry validation in marking UI).
- **No criterion / rubric weights.**

**Level 2 — review score** (one student, one review — the weighted “final” for that review round):

```
review_score = round( Σ(reviewer_total × reviewer_weight) / Σ(reviewer_weight), 2 )
```

Reviewer weights: per-review override (`pr_reviewer_weights`) else `pr_panel_reviewers.weight`; default **1**.

**Level 3 — combined score** (across confirmed reviews in a project):

```
combined = round( Σ(review_score × review_weight) / Σ(review_weight), 2 )
```

Review weights from `pr_review_weights` / `WeightConfiguration` (unchanged). Only **confirmed** reviews count.

### Worked example (AC1)

**Level 1 — raw sums**

| Reviewer | Marks | Total |
|----------|-------|-------|
| 1 | 5 + 5 | **10.00** |
| 2 | 5 + 9 | **14.00** |
| 3 | 4 + 5 | **9.00** |

**Level 2 — weighted average of reviewer totals** (weights 1)

`(10 + 14 + 9) / 3 = **11.00**`

**Level 3** (single confirmed review)

`11.00`

### Example with non-default reviewer weights

Reviewer totals 10, 14, 9; weights 2, 1, 1:

`(10×2 + 14×1 + 9×1) / (2+1+1) = 43/4 = **10.75**`

### Scope boundaries

| In scope | Out of scope (unless product asks later) |
|----------|------------------------------------------|
| Remove rubric criterion weight from UI & scoring | Removing **review** or **reviewer** weight configuration |
| Raw-sum reviewer totals | Changing `max_marks` validation on mark entry |
| Golden tests + REST/reports parity | DB migration to drop `pr_rubric_criteria.weight` column |

### Breaking change vs Story 6.1

Story 6.1 implemented **percentage-based** Level 1. This story **supersedes** that behavior. Update all `ScoreServiceTest` and any docs that describe “reviewer total as %”.

### Prerequisites

- Stories 6.1–6.4: `ScoreService`, scores REST, `ScoreBreakdown`.
- Marks in `pr_marks`, submitted status for aggregation.

### Files / patterns

| Area | Path |
|------|------|
| Score logic | `includes/services/ScoreService.php` |
| Unit tests | `tests/ScoreServiceTest.php` |
| Rubric UI | `src/coordinator/components/RubricTable.jsx`, wizard `ReviewRubricsStep.jsx` if applicable |
| Weights UI (keep) | `src/coordinator/components/WeightConfiguration.jsx` — review + reviewer only |
| REST | `includes/rest/class-rest-scores.php` |
| UI breakdown | `src/coordinator/components/ScoreBreakdown.jsx` — verify labels still accurate (raw totals, not %) |
| Reports | `ReportsOverallScoresTable.jsx`, `reportsScoresMatrixUtils.js` |
| SQL parity | `tests/sql/validate_scores_golden.sql` |

### UI copy suggestion

`ScoreBreakdown` helper text: reviewer lines show **sum of marks**; review score is **weighted average of reviewer totals** (not “percentage”).

### Testing requirements

- PHPUnit: golden AC1 + updated legacy fixtures.
- `round(..., 2)` on all aggregates.
- Draft marks excluded from sums.

### References

- [Source: `includes/services/ScoreService.php` — update Level 1–3]
- [Source: `tests/ScoreServiceTest.php`]
- [Source: _bmad-output/implementation/6-1-score-service.md — superseded Level 1 behavior]
- [Source: _bmad-output/implementation/4-4-weight-configuration.md — reviewer/review weights only]
- [Source: _bmad-output/implementation/7-6-reports-marks-matrix-layout-sort-export.md — use server review score]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
