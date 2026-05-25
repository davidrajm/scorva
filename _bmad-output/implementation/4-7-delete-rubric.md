# Story 4.7: Delete rubric criteria rows and review rounds

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want to remove individual rubric criteria and delete draft review rounds from the rubric UI,
So that I can fix mistakes during setup without being stuck with extra criteria or unwanted review rounds.

## Acceptance Criteria

1. **Given** a review round where `criteria_editable` is true (`draft`, `unlocked`, or `confirmed` with no marks) **When** the coordinator views `RubricTable` **Then** each criteria row shows a **Remove** control (icon button or text) **And** clicking it removes that row from the local table **And** **Save** persists via `PUT /sessions/{id}/reviews/{review_id}/criteria` with the remaining rows only.

2. **Given** only one criterion row remains **When** the coordinator views the table **Then** the **Remove** control for that row is disabled **And** helper copy explains at least one criterion is required (same rule as confirm validation).

3. **Given** criteria rows loaded from the API include `id` **When** the coordinator saves after removing a row **Then** the payload includes `id` for each remaining row **And** `ReviewRepository::replace_criteria()` deletes criteria omitted from the payload **And** existing criterion rows are updated in place (not duplicated).

4. **Given** a review round with `status === 'draft'` and `has_marks === false` **When** the coordinator uses the standalone **Rubrics** page (`RubricsPanel`) or wizard **Rubrics** step **Then** they can **Remove review round** with a `ConfirmDialog` **And** `DELETE /sessions/{id}/reviews/{review_id}` succeeds **And** the list refreshes.

5. **Given** a review round that is confirmed, unlocked with marks, or has any marks **When** the coordinator views delete affordances **Then** **Remove review round** is hidden or disabled **And** tooltip/helper text matches server rules: “Only draft review rounds without marks can be removed.” (reuse `ReviewRoundsStep` behaviour).

6. **Given** PHPUnit and front-end build **When** implementation is complete **Then** tests cover: save criteria with `id` preserves rows; omitting a criterion `id` from payload deletes that row; `DELETE` draft review still passes **And** optional React test or manual checklist documented **And** `composer test` + `npm run build` pass.

## Tasks / Subtasks

- [x] Preserve criterion `id` in `RubricTable` local state and `payloadCriteria` (AC: 1, 3)
- [x] Add `removeRow(index)` with min-one-row guard and accessible Remove button per row (AC: 1, 2)
- [x] Add review-round delete to `RubricsPanel` (mirror `ReviewRoundsStep.handleDelete` + `ConfirmDialog`) (AC: 4, 5)
- [x] Extend `RestReviewsTest` for criteria delete-by-omit with stable ids; regression on `test_delete_draft_review_without_marks` (AC: 6)
- [x] Run `composer test` and `npm run build`

## Dev Notes

### Product intent (user report)

Coordinators report **“delete rubric is not available”** while configuring rubrics. Investigation shows:

| Capability | Backend | UI today |
|------------|---------|----------|
| Delete **criterion row** | `replace_criteria()` deletes rows not in payload | **No Remove control** in `RubricTable.jsx` (gap vs Story 4.6 AC3: “remove criteria rows freely”) |
| Delete **review round** | `DELETE .../reviews/{id}` — draft, no marks only | **Only** on wizard `ReviewRoundsStep.jsx`; **missing** on `RubricsPanel` / `#/session/:id/rubrics` |

This story closes both UI gaps. It does **not** change lifecycle rules (confirmed rounds with marks still require Unlock; review rounds with marks cannot be deleted).

### Critical: preserve criterion `id` on save

`RubricTable` currently maps criteria to local state **without** `id`:

```javascript
review.criteria.map((row) => ({
  label: row.label ?? '',
  max_marks: String(row.max_marks ?? ''),
  weight: String(row.weight ?? 1),
}));
```

`payloadCriteria` also omits `id`. On save, `replace_criteria()` treats every row as **new inserts** and **deletes** all previous criteria IDs. That orphans `pr_marks.criterion_id` references and breaks scoring.

**Required fix (before or with row delete):**

```javascript
// Local state shape
{ id?: number, label, max_marks, weight }

// Payload
{ id, label, max_marks, weight, sort_order }
```

Only omit `id` for genuinely new rows (user clicked **Add criterion**).

### Criterion row removal (no new REST route)

Deletion is **save-time**: remove row locally → **Save** → PUT fewer criteria with ids for survivors. Server already deletes criteria not listed in `kept_ids` (`ReviewRepository::replace_criteria` lines 208–212).

Validation (client, match `ReviewRubricBlock.validateCriteriaPayload`):

- At least one row with non-empty label and `max_marks > 0` before **Save** or **Confirm**.

### Review round removal (reuse existing REST)

Copy pattern from `ReviewRoundsStep.jsx`:

```javascript
const canDelete = review.status === 'draft' && !review.has_marks;
await del(`/sessions/${sessionId}/reviews/${review.id}`);
```

Add `ConfirmDialog` on `RubricsPanel` (destructive confirm). Optional: expose `review_deletable` from `format_review()` — **not required** if UI derives same rule as server.

### Files to touch

| File | Change |
|------|--------|
| `src/coordinator/components/RubricTable.jsx` | `id` in state, Remove row, min-row guard |
| `src/coordinator/components/RubricsPanel.jsx` | Per-review header actions: Remove review round + confirm |
| `tests/RestReviewsTest.php` | Criteria delete via PUT with ids; duplicate-row regression |

### Do not

- Add `DELETE` per criterion endpoint (YAGNI — PUT replace is canonical).
- Allow deleting the last review round if it would leave session with zero reviews and break wizard gates (server may allow; UI should match `ReviewRoundsStep` — at least one round required to continue).
- Allow review-round delete when `has_marks` or status ≠ `draft` (server returns `409`).
- Delete criteria when `criteria_editable === false` without Unlock flow.

### UX

- Remove criterion: ghost/secondary button per row, `aria-label="Remove criterion {label or index}"`.
- Disabled remove on last row: `title` tooltip “At least one criterion is required.”
- Review round remove: destructive `ConfirmDialog` — “Remove {label}? This review round and its criteria will be deleted.”

### Testing

**PHPUnit (`RestReviewsTest`):**

1. Create review with two criteria (capture ids from response).
2. `PUT criteria` with only first id → second criterion gone from `list_criteria`.
3. `PUT criteria` with both ids unchanged → ids stable (no duplicate rows).

**Manual:**

- Wizard Reviews step: remove criterion row → Save → reload → row gone.
- Standalone Rubrics: remove draft review round → confirm → round gone.
- Confirmed review: no review-round delete button; criterion remove only if `criteria_editable`.

### References

- [Source: _bmad-output/implementation/4-6-per-review-rubric-until-scoring.md — AC3 remove criteria rows]
- [Source: _bmad-output/implementation/3-7-wizard-review-rounds.md — delete review round pattern]
- [Source: includes/repositories/ReviewRepository.php — `replace_criteria`]
- [Source: includes/rest/class-rest-reviews.php — `delete_review`, `save_criteria`]
- [Source: src/coordinator/components/RubricTable.jsx]
- [Source: src/coordinator/components/ReviewRoundsStep.jsx — `handleDelete`, `canDelete`]
- [Source: src/coordinator/components/RubricsPanel.jsx]

## Dev Agent Record

### Agent Model Used

(create-story workflow)

### Debug Log References

- `./vendor/bin/phpunit` — 118 tests, 413 assertions, OK
- `npm run build` — webpack compiled successfully

### Completion Notes List

- `RubricTable` preserves criterion `id` in local state and save/confirm payloads; new rows omit `id`.
- Per-row **Remove** control with min-one-row guard (`title` tooltip when disabled).
- `RubricsPanel` adds **Remove review round** for draft rounds without marks, with destructive `ConfirmDialog`.
- Added `test_save_criteria_omitting_id_deletes_criterion_row` and `test_save_criteria_with_ids_preserves_rows_without_duplicates` in `RestReviewsTest`.

### File List

- `src/coordinator/components/RubricTable.jsx`
- `src/coordinator/components/RubricsPanel.jsx`
- `tests/RestReviewsTest.php`
- `build/coordinator.js`
- `build/coordinator.css`
- `build/coordinator-rtl.css`
- `build/coordinator.asset.php`

### Change Log

- 2026-05-17: Story created from user report — delete rubric unavailable; criterion row remove + review round delete on Rubrics UI; fix criterion `id` on save.
- 2026-05-17: Implemented criterion row remove, criterion `id` on save, RubricsPanel review-round delete with confirm dialog, PHPUnit coverage.
