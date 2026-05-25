# Story 5.6: Reviewer marking grid, deep links, save-only drafts, and per-review freeze

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want a shareable marking page with students and rubric scores in a grid, score entry in a modal, save-as-draft only, and a per-review freeze action,
So that I can work efficiently on mobile or desktop, bookmark my assignment, and finalize scores when ready so coordinators see them in reports.

## Acceptance Criteria

1. **Deep links (HashRouter)**
   - **Given** the reviewer SPA at `/reviews/mark/` (or dev proxy `http://localhost:3000/reviews/mark/`)
   - **When** the app loads
   - **Then** routes use `HashRouter` (same pattern as coordinator `src/coordinator/App.jsx`) with at least:
     - `#/` — assignment list
     - `#/mark/:sessionId/:reviewId/:panelId` — marking grid for one assignment
   - **And** each markable assignment card is a real link (`<a href="#/mark/...">`) so URLs can be copied, refreshed, and shared
   - **And** invalid or unauthorized IDs show a Notice and a link back to `#/`
   - **And** browser back/forward moves between assignments and the list without losing context

2. **Marking grid with rubric columns (mobile-first)**
   - **Given** the reviewer opens `#/mark/:sessionId/:reviewId/:panelId`
   - **When** data loads
   - **Then** a responsive CSS **grid** table shows:
     - Sticky/first columns: student name, reg no, overall status chip
     - One column per rubric criterion (label in header, abbreviated on narrow viewports with `title` tooltip)
     - Last column: **Update score** action (button; not navigation to a new page)
   - **And** each criterion cell shows the current score (or em dash if empty), `tabular-nums`, and `FlaggedMarkChip` when that criterion is flagged
   - **And** layout is mobile-first: horizontal scroll on small screens; header row uses `scope="col"`; student column remains readable at `sm` breakpoint and up
   - **And** blocked assignments (`marking_inactive`, `rubric_not_confirmed`, `session_closed`) are not reachable via deep link (API 403 → Notice + back link)

3. **Score entry modal (replaces full-page funnel step)**
   - **Given** the marking grid
   - **When** the reviewer clicks **Update score** on a student row
   - **Then** a modal opens (reuse `ConfirmDialog` portal pattern / focus trap from `src/shared/components/ConfirmDialog.jsx`) containing the rubric form for that student only
   - **And** the modal title includes student name and reg no
   - **And** closing the modal (Cancel, Escape, backdrop) returns to the grid without a full page reload
   - **And** optional deep link `?student={studentId}` on the hash route opens that student’s modal once on load (for support links)

4. **Save only — draft per student (no Submit)**
   - **Given** the modal rubric form
   - **When** the reviewer saves
   - **Then** only a single primary action **Save** is shown (remove **Submit** and remove form `onSubmit` → submitted flow)
   - **And** `POST` marks always persist with `status: 'draft'` (partial scores allowed; same validation as today’s draft path in `MarkService` / `validateMarksForSave`)
   - **And** `aria-live="polite"` announces “Draft saved successfully.”
   - **And** after save, the grid refreshes criterion cells and status chip for that student
   - **And** student row status values: `not_started` | `draft` | `frozen` (see AC5)

5. **Per-review Freeze scores**
   - **Given** the marking grid for one assignment
   - **When** the reviewer clicks **Freeze scores** (review-level action in page header/toolbar)
   - **Then** a `ConfirmDialog` explains: scores will be finalized for all students on this review and included in coordinator reports; editing will be locked until a coordinator intervenes (if ever supported later)
   - **When** confirmed
   - **Then** server validates that **every assigned student** on this panel/review has a valid numeric score for **every** rubric criterion
   - **And** all that reviewer’s mark rows for this `session_id` + `review_id` are updated to `status = 'submitted'` (reuse `MarkRepository::STATUS_SUBMITTED`; no new DB status value)
   - **And** UI shows review-level state **Frozen** (e.g. `StatusChip` variant `confirmed`) and disables **Update score** and **Freeze scores**
   - **And** subsequent `POST` marks for that review return `403` with code `marks_frozen` and mapped copy in `markErrors.js`
   - **And** frozen rows appear in coordinator **reports and score aggregation** that already filter on `submitted` (`ScoreService`, `ReportQueryService::count_submitted_marks`, progress %)
   - **When** validation fails (missing scores)
   - **Then** API returns `400` with code `incomplete_marks` and a list/count of students still incomplete; UI shows a Notice naming how many students need scores

6. **Regression / non-goals**
   - **And** assignment list still shows blocked assignments with existing `BLOCKED_COPY` messages (`5-5-blocked-state-messaging.md`)
   - **And** coordinator apps and exports are unchanged except they receive more `submitted` rows after reviewers freeze
   - **Out of scope:** coordinator “unfreeze” UI, blind marking, async export changes

## Tasks / Subtasks

- [x] **Routing:** Add `HashRouter` + routes in `src/reviewer/App.jsx`; replace `useState` funnel with route-driven views; keep `configureApi()` on mount
- [x] **Marking grid:** New `src/reviewer/components/MarkingGrid.jsx` (or extend `StudentList`); Tailwind grid/table; fetch rubric + students with scores
- [x] **Modal form:** Adapt `src/reviewer/components/RubricForm.jsx` for modal embed (props: `onClose`, hide back link, Save-only); wire from grid
- [x] **REST — richer student list:** Extend `GET /reviewer/assignments/{session}/{review}/{panel}/students` to include `criteria` (id, label, max_marks) and per-student `scores: { [criterion_id]: number|null }`, `flagged: { [criterion_id]: bool }`, `frozen: bool` (true when all marks for that reviewer+review are submitted)
- [x] **REST — freeze:** `POST /reviewer/assignments/{session}/{review}/freeze` (panel scoped in body or path if needed for assignment check); delegate to `MarkService::freeze_review_marks()`
- [x] **MarkService:** Implement `freeze_review_marks($session_id, $review_id, $reviewer_user_id)` with assignment guards + completeness validation + bulk status update; guard saves when already frozen
- [x] **Errors:** Add `marks_frozen`, `incomplete_marks` to `src/shared/markErrors.js`
- [x] **Tests:** `MarkServiceTest` freeze happy path, incomplete, frozen guard; `RestReviewerAssignments` or dedicated REST test; optional component test stubs
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

Target URL: `http://localhost:3000/reviews/mark/` (browser-sync → WordPress). Requested changes:

1. Proper deep links after `/reviews/mark/`
2. Per-review grid: students + rubric columns, mobile-first CSS grid
3. Student → **Update score** opens modal with form (not separate page)
4. Remove Submit; **Save** only → always `draft`
5. Per-review **Freeze** → scores appear in reports (via `submitted` status)

### Architecture alignment

| Area | Current | Target |
|------|---------|--------|
| Reviewer navigation | React `useState` in `App.jsx` (assignments → list → form) | `HashRouter` deep links |
| Rubric entry | Full-page `RubricForm` max-w 640px | Modal on grid; optional retain centered form styles inside modal |
| Mark status | `draft` / `submitted` per criterion row; UI infers student `mark_status` | Save → always `draft`; Freeze → bulk `submitted` |
| Reports / scores | `ScoreService` ignores non-`submitted` | Freeze promotes to `submitted` so progress + combined scores update |

**Do not** add Excel `freeze_row` confusion — user “freeze” means **finalize marks**, not spreadsheet panes.

### Critical files (touch list)

**Frontend**

- `src/reviewer/App.jsx` — router shell
- `src/reviewer/pages/MarkAssignments.jsx` — assignment links; remove or repurpose `StudentList` funnel
- `src/reviewer/components/RubricForm.jsx` — Save-only, modal-friendly
- `src/reviewer/components/MarkingGrid.jsx` — **new** grid + freeze toolbar
- `src/shared/markErrors.js` — new codes
- `src/shared/markValidation.js` — remove/submit paths still OK for server; client only calls `draft`

**Backend**

- `includes/rest/class-rest-reviewer-assignments.php` — extend `list_students`, add freeze route
- `includes/services/MarkService.php` — `freeze_review_marks`, `assert_not_frozen` in `save_marks`
- `includes/repositories/MarkRepository.php` — bulk update status by review+reviewer; helper `is_review_frozen_for_reviewer()`
- `includes/rest/class-rest-bootstrap.php` — register routes if split

**Tests**

- `tests/MarkServiceTest.php`
- `tests/RestReviewersTest.php` or new `tests/RestReviewerAssignmentsTest.php`
- Update `tests/sql/01_seed_demo_session.sql` only if freeze integration needs seed marks

### Routing spec (recommended)

```text
/reviews/mark/#/                                    → MarkAssignments
/reviews/mark/#/mark/:sessionId/:reviewId/:panelId → MarkingGrid
/reviews/mark/#/mark/:sessionId/:reviewId/:panelId?student=:studentId → grid + open modal
```

WordPress rewrite stays `^reviews/mark/?$` → reviewer shell only (`includes/routes.php`). Do **not** add path segments under `/reviews/mark/foo` without new rewrite rules; hash routing avoids that.

Assignment card link example:

```jsx
<a href={`#/mark/${a.session_id}/${a.review_id}/${a.panel_id}`}>
```

### Grid UX (implementation hints)

- Use CSS Grid: `grid-template-columns: minmax(8rem,1.2fr) minmax(5rem,.8fr) repeat(N, minmax(3.5rem,1fr)) minmax(6rem,auto)` on `md+`; on mobile, `overflow-x-auto` wrapper with `min-w-[640px]` inner grid or fewer visible columns + expand row pattern — prefer horizontal scroll per UX spec “desktop-first” coordinator vs “focused” reviewer (slightly more spacious modal, denser grid).
- Criterion headers: `text-xs`, truncate with `title={label}`.
- Status chip mapping: `not_started` → draft variant; `draft` → unlocked; `frozen` → confirmed label “Frozen”.

### API contract sketch

**GET** `.../students` response extension:

```json
{
  "criteria": [{ "id": 1, "label": "Design", "max_marks": 10 }],
  "review_frozen": false,
  "students": [{
    "id": 5,
    "reg_no": "S001",
    "name": "Ada",
    "mark_status": "draft",
    "scores": { "1": 8.5, "2": null },
    "flagged": { "1": false, "2": true }
  }]
}
```

`mark_status` at student level: `frozen` when all criteria rows for this reviewer are `submitted`; else existing draft/not_started logic.

**POST** `.../freeze` → `{ "frozen": true, "students_updated": 12 }` or `WP_Error incomplete_marks`.

Reuse existing marks POST: `POST /sessions/{id}/reviews/{review_id}/students/{student_id}/marks` with `{ status: "draft", criteria: [...] }` only from UI.

### Freeze vs submit (product rule)

| Action | Scope | DB status | Editable after |
|--------|-------|-----------|----------------|
| Save | One student | `draft` | Yes (until frozen) |
| Freeze | All students on review for this reviewer | `submitted` | No (`marks_frozen`) |

This replaces per-student **Submit** from story 5.4. Update any copy in UX spec references from “draft or submit” to “draft then freeze” for reviewer flows only.

### Reports impact (no ExportService change required)

- `ReportQueryService::marks_detail` — already lists all rows with Status column; frozen rows show `submitted`
- `rubric_scores` view — includes all statuses; coordinators filter mentally or future story can filter
- `ScoreService` / `review_summary` / progress — use `submitted` counts → **freeze is the gate for computed scores**

### Previous story intelligence

From `5-4-rubric-form.md`:

- `validateMarksForSave` + `validateCriterionScore` in `src/shared/markValidation.js`
- `mapMarkApiError` / `fixByLabel` in `src/shared/markErrors.js`
- Marks loaded via `GET /sessions/.../marks` and rubric via `GET /reviewer/assignments/.../rubric`

From `5-5-blocked-state-messaging.md`:

- Keep `BLOCKED_COPY` and coordinator/admin fix hints on assignment list

From `3-11-per-review-assignments-marking-active.md`:

- Assignments require `marking_active`, per-review roster, `MarkService::validate_assignment` uses `review_id`

### Anti-patterns (do not)

- Do **not** build a second marks table or `frozen` status column on `pr_marks` — use `submitted`
- Do **not** use BrowserRouter with path `/mark/...` without WordPress rewrite changes
- Do **not** leave Submit in modal “for later” — remove from reviewer UI entirely
- Do **not** skip server-side freeze validation (client-only freeze is insecure)

### Testing requirements

1. Reviewer A freezes review 1 → all marks `submitted`; Reviewer A gets `marks_frozen` on further POST
2. Incomplete criterion on one student → freeze returns `incomplete_marks`, marks stay draft
3. `ScoreService` includes frozen marks in totals (existing submitted tests pattern)
4. Hash URL `#/mark/s/r/p` loads grid; refresh preserves view
5. `npm run build` produces `build/reviewer.js` without errors

### References

- [Source: _bmad-output/planning/epics.md — Epic 5, FR15, FR27, UX-DR5, UX-DR15, UX-DR23]
- [Source: _bmad-output/planning/ux-design-specification.md — Reviewer marking funnel, HashRouter note]
- [Source: _bmad-output/implementation/5-4-rubric-form.md]
- [Source: _bmad-output/implementation/5-5-blocked-state-messaging.md]
- [Source: src/reviewer/App.jsx, MarkAssignments.jsx, RubricForm.jsx]
- [Source: includes/services/MarkService.php, ReportQueryService.php, ScoreService.php]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

- RestReviewerAssignmentsTest: second criterion max_marks=5 caused invalid_score when test used score 8; fixed by capping scores to max_marks.
- RestTestFixtures::login_with_cap resets user id to 42; tests restore reviewer user id after login.

### Completion Notes List

- Reviewer SPA uses HashRouter: `#/` assignments, `#/mark/:sessionId/:reviewId/:panelId` grid; optional `?student=` opens score modal.
- MarkingGrid: CSS grid with sticky student column, horizontal scroll on small screens, per-criterion scores, Freeze scores + ConfirmDialog.
- RubricForm: Save-only (always draft POST), embedded modal mode, read-only when frozen.
- Backend: extended list_students payload; POST freeze with panel_id; MarkService::freeze_review_marks + marks_frozen guard on save.
- PHPUnit 146 tests OK; `npm run build` OK.

### File List

- src/reviewer/App.jsx
- src/reviewer/pages/MarkAssignments.jsx
- src/reviewer/components/MarkingGrid.jsx
- src/reviewer/components/ScoreEntryModal.jsx
- src/reviewer/components/RubricForm.jsx
- src/shared/markErrors.js
- includes/repositories/MarkRepository.php
- includes/services/MarkService.php
- includes/rest/class-rest-reviewer-assignments.php
- tests/MarkServiceTest.php
- tests/RestReviewerAssignmentsTest.php
- tests/FakeWpdb.php
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css
- build/reviewer.asset.php

## Change Log

- 2026-05-17: Story 5.6 — reviewer marking grid, hash deep links, save-only drafts, per-panel freeze scores.

## Open questions (for PM / user — not blocking default implementation)

1. After freeze, should coordinators see draft rows in **marks detail** export mixed with submitted, or should exports hide drafts? **Default:** no export filter change (status column shows `submitted`).
2. Should freeze be **per panel** (current assignment scope) or **all panels** the reviewer holds on that review? **Default:** only students on the linked `#/mark/.../panelId` assignment (matches assignment card grain).
3. Coordinator unfreeze / rubric unlock interaction with frozen marks — defer to Epic 4 lifecycle story unless user requests now.
