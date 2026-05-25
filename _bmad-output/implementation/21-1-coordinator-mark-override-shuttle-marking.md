# Story 21.1: Coordinator mark override with shuttle marking

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **project coordinator**,
I want to override individual reviewer criterion marks for a student with a mandatory reason,
So that scoring errors can be corrected fairly while everyone can see that the coordinator changed the mark (**shuttle marking**).

As a **coordinator or reviewer viewing marks**,
I want coordinator-overridden cells clearly distinguished from rubric-change flags and normal scores,
So that shuttle marking is obvious in the UI and in exports without confusing “flagged” rubric warnings.

## Acceptance Criteria

1. **Capability and authorization**
   - **Given** the default `project_reviews_coordinator` role
   - **When** capabilities are applied on plugin upgrade
   - **Then** coordinators receive `pr_override_marks` (same capability as administrators for this action)
   - **And** `CapabilitiesTest::test_coordinator_role_excludes_override_marks` is updated to assert coordinators **have** the cap (or replaced with positive assertion)
   - **And** `POST /project-reviews/v1/marks/{id}/override` remains the canonical override endpoint (Story 9.2); permission still requires `pr_override_marks`
   - **When** a user lacks `pr_override_marks`
   - **Then** override returns `403`

2. **Persist coordinator override separately from rubric “flagged”**
   - **Given** `pr_marks` today has `flagged` for rubric re-confirm (Story 4.5) and Story 9.2 incorrectly sets `flagged = 1` on override
   - **When** this story ships
   - **Then** schema migration adds `coordinator_overridden tinyint(1) NOT NULL DEFAULT 0` on `pr_marks` (via `Install::maybe_migrate_*` pattern used elsewhere)
   - **And** optional `overridden_from_score decimal(10,4) DEFAULT NULL` — set on **first** coordinator override only (reviewer’s score before change); leave unchanged on subsequent overrides to same mark
   - **And** `MarkService::override_mark()` sets `coordinator_overridden = 1`, does **not** set `flagged` for override path
   - **And** `format_mark()` and `pr_rubric_scores` view include `coordinator_overridden` (and view column if view is regenerated)
   - **And** rubric `flag_marks_for_review` behaviour is unchanged

3. **Override rules (reuse 9.2 service)**
   - **Given** coordinator with override permission on an active project
   - **When** they submit `{ "score": <number>, "reason": "<text>" }` with reason trimmed length ≥ 10
   - **Then** mark updates to `status = submitted`, score validated (half-point increment, max_marks) as today
   - **And** `AuditService::log('mark_override', 'mark', mark_id, old_score, json with score+reason, actor_user_id)` (unchanged)
   - **When** reason missing or &lt; 10 characters
   - **Then** `400` `reason_required` / `reason_too_short` (existing validation)
   - **When** review has `coordinator_marks_locked` or session is `closed`
   - **Then** override blocked with existing error codes (`coordinator_marks_locked`, `session_closed`)
   - **When** score invalid
   - **Then** `invalid_score` as today

4. **Shuttle marking — API contract**
   - **Given** a mark with `coordinator_overridden = 1`
   - **When** marks are returned via marks REST, marks-grid, reviewer assignments payload, or reports matrix builders
   - **Then** each affected mark/cell includes:
     - `coordinator_overridden: true`
     - `overridden_from_score: number | null` (prior reviewer score when known)
   - **And** `flagged` remains exclusively for rubric-change semantics

5. **Shuttle marking — coordinator UI**
   - **Given** coordinator on **Reports → Marks** (live matrix) or **Progress** with per-student mark context
   - **When** viewing a cell that has a numeric score (draft or submitted) and review is not coordinator-frozen
   - **Then** an **Override mark** action is available (icon button or row menu — match existing `Button` / table action density)
   - **And** opening it shows `ConfirmDialog` (or modal) with:
     - Read-only context: student, review, criterion, reviewer slot, current score
     - Editable score input (`inputmode="decimal"`, step 0.5 per Story 5.10)
     - Required reason `textarea`, min 10 chars, `aria-required` (UX-DR21)
   - **On** success: refresh matrix/progress; success `Notice`
   - **On** error: map REST codes to `Notice` (reuse `api.js` error helpers)
   - **When** `coordinator_marks_locked` for the review
   - **Then** override controls hidden/disabled with copy pointing to **Unlock review** on Reports (Story 5.18)

6. **Shuttle marking — visual indicator (frontend)**
   - **Given** a mark/cell with `coordinator_overridden`
   - **When** rendered in coordinator Reports marks matrix, coordinator progress mark summaries, and reviewer marking grid / rubric form read-only score display
   - **Then** show a **ShuttleMarkChip** (new shared component) distinct from `FlaggedMarkChip`:
     - Label e.g. **Coordinator** or shuttle icon + “Coordinator override” tooltip
     - Variant: use `StatusChip` with new variant `coordinator_override` (add token colors in `app-shell.css` if needed — e.g. accent distinct from `flagged` warning)
   - **And** when `overridden_from_score` is present, show compact pattern: `8 → 7` or strikethrough prior + new score (tabular-nums); screen reader text includes both values
   - **And** `FlaggedMarkChip` is **not** shown for coordinator overrides

7. **Shuttle marking — exports and audit**
   - **Given** marks detail / rubric scores flat export (ReportQueryService)
   - **When** a row is coordinator-overridden
   - **Then** export includes a column or value such as `Coordinator override: Yes` (and optionally `Previous score` from `overridden_from_score`) — do not overload `flagged` column
   - **And** audit log UI (`AuditLog.jsx`) already lists `mark_override`; ensure action label is human-readable (“Mark override”)

8. **Out of scope**
   - Bulk override (multi-cell) — one mark per dialog for MVP
   - Reverting an override to reviewer’s original in one click (coordinator can override again with reason)
   - Panel-head-only override without coordinator cap
   - Changing WP Admin capability documentation UI beyond role default (Story 9.3) — note in admin settings copy if trivial

9. **Tests and build**
   - **And** `MarkServiceTest`: override sets `coordinator_overridden`, does not set `flagged`; `overridden_from_score` captured once
   - **And** `Rest_Marks` or integration test: coordinator role can override; reviewer `403`
   - **And** `ReportsViewService` / matrix cell includes shuttle fields when mark overridden
   - **And** `composer test` and `npm run build`

## Tasks / Subtasks

- [x] **Schema:** `coordinator_overridden`, `overridden_from_score` on `pr_marks`; migration; update `pr_rubric_scores` view DDL
- [x] **Repository:** `MarkRepository::apply_coordinator_override`; queries return columns via `SELECT *`
- [x] **Service:** Fix `MarkService::override_mark` — set shuttle columns, stop using `flagged`; extend `format_mark`
- [x] **Capabilities:** Include `PR_CAP_OVERRIDE_MARKS` in `Capabilities::coordinator_caps()`; update tests and admin settings blurb in `class-admin-settings.php`
- [x] **REST payloads:** Marks grid, `Rest_Reviewer_Assignments`, `ReportsViewService` / `ReportQueryService` expose shuttle fields on cells
- [x] **Shared UI:** `ShuttleMarkChip.jsx`, `StatusChip` variant, CSS token
- [x] **Coordinator UI:** Override dialog + wire on `ReportsMarksTable`; API helper `postMarkOverride(markId, { score, reason })`
- [x] **Reviewer UI:** Read-only shuttle indicator on `MarkingGrid` / `RubricForm` when viewing existing overridden marks (no override action for reviewers)
- [x] **Exports:** Marks detail / flat scores columns for coordinator override
- [x] **Tests + build**

## Dev Notes

### User request (source)

> Implement a students review mark override for the coordinator role. He should be able to override the marks, but modified by the co-ordinator marking should be there, in the backend and in the front-end (a shuttle marking).

**Shuttle marking** = persistent, visible indicator that a score was changed by the coordinator (not a silent edit). Name is intentional product language; implement as `coordinator_overridden` in data and **ShuttleMarkChip** in UI.

### What already exists (do not reinvent)

| Area | Location | State |
|------|----------|--------|
| Override service + audit | `MarkService::override_mark`, `validate_override_reason` | Shipped (9.2) |
| REST | `POST .../marks/{id}/override` in `class-rest-marks.php` | Shipped; cap-gated |
| Audit | `AuditService`, `pr_mark_audit` | Shipped (9.1) |
| Reason validation | min 10 chars | Shipped |
| Coordinator **UI** | — | **Missing** |
| Coordinator **cap** | `coordinator_caps()` excludes override | **By design — change for this story** |
| Distinct override flag | Uses `flagged=1` on override | **Bug/conflict with 4.5 — fix** |

```631:680:includes/services/MarkService.php
    public function override_mark(int $mark_id, float $score, string $reason, int $actor_user_id): array
    {
        // ...
        $this->marks->upsert(
            // ...
            MarkRepository::STATUS_SUBMITTED,
            true  // ← currently sets flagged; replace with coordinator_overridden
        );
```

### Capability change

Update `Capabilities::coordinator_caps()` to **include** `PR_CAP_OVERRIDE_MARKS` (remove the filter exclusion). Administrators unchanged. Document in `class-admin-settings.php` that coordinators may override marks with audit reason.

`user_has_coordinator_workspace_access` already treats `pr_override_marks` as coordinator workspace — no change needed.

### REST (unchanged route)

```http
POST /wp-json/project-reviews/v1/marks/{mark_id}/override
Content-Type: application/json

{ "score": 7.5, "reason": "Panel consensus recorded wrong criterion score." }
```

Response: `{ "mark": { "id", "score", "coordinator_overridden": true, "overridden_from_score": 8, "flagged": false, ... } }`

Frontend: add to `src/shared/api.js`:

```javascript
export function postMarkOverride( markId, body ) {
  return post( `/marks/${ markId }/override`, body );
}
```

### UI placement (recommended MVP)

| Surface | Action |
|---------|--------|
| `ReportsMarksTable.jsx` | Primary: coordinators already scan all marks; add per-cell override on click/long-press or row action column |
| `SessionProgress.jsx` | Optional phase 2: only if Reports override is awkward for sparse marks |

Reuse patterns from Story **5.17** (`CorrectAttendanceDialog`) and UX **Override Mark with Audit** flow (`ux-design-specification.md` § Admin: Override Mark with Audit).

`MarkCell` today:

```36:51:src/coordinator/components/ReportsMarksTable.jsx
function MarkCell( { cell } ) {
	// ...
	{ cell.flagged ? <FlaggedMarkChip /> : null }
}
```

Extend to:

```javascript
{ cell.coordinator_overridden ? <ShuttleMarkChip fromScore={ cell.overridden_from_score } score={ cell.score } /> : null }
{ cell.flagged ? <FlaggedMarkChip /> : null }
```

### Reports / matrix backend

`ReportsViewService` builds cells with `flagged` today — add `coordinator_overridden` and `overridden_from_score` from `pr_marks` row when assembling mark cells (same path as draft/score).

`ReportQueryService` marks detail lines: add fields parallel to `flagged` Yes/No column.

### Reviewer read-only visibility

Reviewers should **see** shuttle marking on their grid when a coordinator changed a score they entered (transparency). Do **not** grant reviewers override UI. `MarkingGrid` / assignment `flagged` object in `class-rest-reviewer-assignments.php` should expose per-criterion `coordinator_overridden` for chip rendering.

### Lock and freeze interaction

- `coordinator_marks_locked` → block override (existing `override_mark` check) — align UI disable with Reports freeze copy (Story 5.18).
- Panel freeze / reviewer submitted → override still allowed unless review coordinator-frozen (coordinator governance supersedes reviewer freeze for corrections).

### Previous story intelligence

- **9.2** implemented backend only; story file still says “administrator” — this story extends FR23 to coordinators with UI.
- **4.5** owns `flagged` + `FlaggedMarkChip` — never use `flagged` for coordinator override after this story.
- **5.17** attendance correction uses same reason length and `pr_manage_sessions`; override uses `pr_override_marks` (coordinator will have both).
- **7.5 / 5.18** coordinator marks lock — respect on override.

### Project structure

| Layer | Files |
|-------|--------|
| Schema | `includes/Install.php` (migration + table DDL + view) |
| Repo | `includes/repositories/MarkRepository.php` |
| Service | `includes/services/MarkService.php` |
| REST | `includes/rest/class-rest-marks.php`, `class-rest-reviewer-assignments.php` |
| Reports | `includes/services/ReportsViewService.php`, `ReportQueryService.php` |
| Caps | `includes/capabilities.php`, `includes/admin/class-admin-settings.php` |
| UI shared | `src/shared/components/ShuttleMarkChip.jsx`, `StatusChip.jsx`, `assets/css/app-shell.css` |
| UI coordinator | `src/coordinator/components/ReportsMarksTable.jsx`, new `MarkOverrideDialog.jsx` |
| UI reviewer | `src/reviewer/components/MarkingGrid.jsx`, `RubricForm.jsx` (indicator only) |
| Tests | `tests/MarkServiceTest.php`, `tests/CapabilitiesTest.php`, `tests/RestMarksTest.php` (add if missing) |

### Testing commands

```bash
composer test
npm run build
```

### References

- [Source: _bmad-output/planning/epics.md — Epic 9 Story 9.2, FR23]
- [Source: _bmad-output/implementation/9-2-mark-override.md]
- [Source: _bmad-output/implementation/4-5-flagged-marks-visibility.md]
- [Source: _bmad-output/implementation/5-17-attendance-consensus-correction.md — dialog + reason pattern]
- [Source: _bmad-output/planning/ux-design-specification.md — Override Mark with Audit, UX-DR21]
- [Source: themes/david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md — § Admin override, capabilities §7]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Added `coordinator_overridden` and `overridden_from_score` on `pr_marks` with migration and `pr_rubric_scores` view refresh.
- `MarkService::override_mark` uses `apply_coordinator_override` (no longer sets `flagged`); `format_mark` and marks-grid payloads expose shuttle fields including mark `id` for UI.
- Coordinators now receive `pr_override_marks` by default; Reports → Marks shows **Override** per scored cell (hidden when review frozen) with audited dialog; **ShuttleMarkChip** distinct from rubric **Flagged**.
- Reviewer assignments/grid/rubric form show read-only shuttle indicators; exports and audit log label updated.
- `composer test` (364 tests) and `npm run build` passed.

### File List

- includes/Install.php
- includes/capabilities.php
- includes/repositories/MarkRepository.php
- includes/services/MarkService.php
- includes/services/ReportsViewService.php
- includes/services/ReportQueryService.php
- includes/rest/class-rest-reviewer-assignments.php
- includes/admin/class-admin-settings.php
- assets/css/app-shell.css
- tailwind.config.js
- src/shared/api.js
- src/shared/components/ShuttleMarkChip.jsx
- src/shared/components/StatusChip.jsx
- src/shared/components/index.js
- src/coordinator/components/MarkOverrideDialog.jsx
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/components/reportsMarksMatrixUtils.js
- src/coordinator/pages/Reports.jsx
- src/coordinator/pages/AuditLog.jsx
- src/reviewer/components/markingGridUtils.js
- src/reviewer/components/MarkingGrid.jsx
- src/reviewer/components/MarkingGridStudentCard.jsx
- src/reviewer/components/RubricForm.jsx
- tests/CapabilitiesTest.php
- tests/MarkServiceTest.php
- tests/RestMarksTest.php
- tests/RestReportsTest.php
- tests/ReportQueryServiceTest.php
- tests/FakeWpdb.php
- build/* (webpack output)

### Change Log

- 2026-05-25: Story 21.1 — coordinator mark override with shuttle marking (schema, caps, API, UI, exports, tests).
