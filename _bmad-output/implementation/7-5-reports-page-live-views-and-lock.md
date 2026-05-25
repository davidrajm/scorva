# Story 7.5: Reports hub — nav polish, live mark tables, overall scores, coordinator lock

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want the reports area to show live student marks and overall scores for a chosen review, with clearer project navigation and a coordinator lock that stops all further mark changes,
So that I can verify committee data on screen, export when ready, and finalize marking without reviewers changing scores afterward.

## Acceptance Criteria

### 1. Sidebar — active state, icons, project grouping

1. **Given** the coordinator app with session context (`#/session/:id/...`)  
   **When** any session-scoped nav item is active  
   **Then** the active link uses primary-tint background (`--pr-chip-active-bg` / `text-primary`) with sufficient contrast vs hover (`--pr-color-surface-raised`) per UX-DR nav patterns.

2. **Given** `CoordinatorNav`  
   **When** nav renders  
   **Then** each item (Dashboard, Student Registry, and each project item) shows a leading **inline SVG icon** (no new npm icon dependency) with `aria-hidden="true"` and visible label text.

3. **Given** a session is selected in the URL  
   **When** project links render  
   **Then** a visually distinct **project group** appears: section label, optional session title (from `GET /sessions/{id}`), subtle container (`border`, `rounded-md`, `bg-surface-raised` or left accent bar) separating project links from global links — coordinators can see these items belong to the current project, not the global app.

### 2. Reports page structure

4. **Given** route `#/session/:id/reports` and `pr_view_reports`  
   **When** the page loads  
   **Then** layout has:
   - **Review selector** (`<select>`) populated from confirmed reviews for the session (`GET /sessions/{id}/reviews` or existing list endpoint).
   - **Tabs or segmented control**: **Rubric marks** | **Overall scores** | **Downloads** (existing `ReportCard` grid moves under Downloads; do not remove export capability from 7.3).

5. **Given** no confirmed reviews  
   **When** reports loads  
   **Then** `EmptyState` explains rubrics must be confirmed first; downloads tab may still list exports if session has data.

### 3. Rubric marks tab (per criterion)

6. **Given** a selected `review_id`  
   **When** the Rubric marks tab is active  
   **Then** a sticky-header table shows:
   - Rows: enrolled students (reg no, name), sorted by reg no.
   - Columns: one per rubric criterion for that review (label in header, `max_marks` in subtitle or tooltip).
   - Cells: each assigned reviewer’s **submitted** score for that criterion (`status = submitted`); draft marks show as “draft” muted text or em dash per UX neutral tone.
   - `FlaggedMarkChip` when criterion mark is flagged.
   - Horizontal scroll on narrow viewports; `tabular-nums` on scores.

7. **And** data is loaded from a dedicated read-only REST endpoint (see API below), not by re-parsing CSV exports client-side.

### 4. Overall scores tab (student × reviewer)

8. **Given** the same selected `review_id`  
   **When** the Overall scores tab is active  
   **Then** a table shows:
   - Rows: enrolled students (reg no, name).
   - Columns: one per reviewer assigned on that review (display name from panel reviewer list; fallback `Reviewer {id}`).
   - Cell values: **reviewer total** from `ScoreService::calculate_reviewer_total()` (Level 1 weighted % — same as `ScoreBreakdown`), using **submitted** marks only.
   - Trailing column **Review score**: `ScoreService::calculate_review_score()` aggregate for that student/review.
   - Footer note: totals are computed on the server; not editable (reuse `ScoreBreakdown` copy tone).

9. **And** `GET` endpoint returns the full matrix in one payload for the selected review (avoid N+1 per student in the browser).

### 5. Coordinator lock (finalize marking)

10. **Given** the coordinator has `pr_manage_sessions` on the reports page  
    **When** they click **Lock marks for this review** (primary destructive/confirm pattern via `ConfirmDialog`)  
    **Then** server sets `coordinator_marks_locked = 1` on `pr_reviews` for that review (new column via `Install::maybe_upgrade` patch)  
    **And** audit logs `review_marks_locked` with `review_id`, `session_id`, actor.

11. **Given** a review with `coordinator_marks_locked`  
    **When** any client attempts mark save, freeze, unfreeze grant, or mark override for that `review_id`  
    **Then** API returns `403` with code `coordinator_marks_locked` and message that the coordinator locked marking for this review  
    **And** reviewer UI shows frozen/disabled state if they open that assignment (reuse `marks_frozen` UX patterns where practical).

12. **Given** a locked review  
    **When** the coordinator views reports  
    **Then** a `StatusChip` (e.g. `confirmed` or custom “Locked”) appears near the review selector  
    **And** **Lock marks** is hidden or disabled; optional **Unlock marks** only if product allows (default: **no unlock in this story** — lock is intentional; session close remains Epic 8).

13. **Out of scope for this story:** session-wide close (8.x), email notifications, changing export file formats, reviewer-initiated unfreeze after coordinator lock (deny new unfreeze requests when locked).

### 6. Regression

14. **And** existing download cards and `RestReportsTest` continue to pass.  
15. **And** `npm run build` + PHPUnit suite pass.

## Tasks / Subtasks

- [x] **Nav:** Update `CoordinatorNav.jsx` — icons, active styles, project group + session title fetch
- [x] **Schema:** `pr_reviews.coordinator_marks_locked` tinyint + `Install` patch + `ReviewRepository` accessors
- [x] **MarkService:** Guard `validate_save_context`, `freeze_review_marks`, `grant_unfreeze`, `override_mark` when review locked
- [x] **REST:** `GET /sessions/{id}/reviews/{review_id}/marks-grid`, `GET .../scores-matrix`, `POST .../lock-marks` (coordinator cap)
- [x] **Reports UI:** Refactor `Reports.jsx` — review select, tabs, `ReportsMarksTable.jsx`, `ReportsScoresTable.jsx`, lock banner + ConfirmDialog
- [x] **Errors:** `coordinator_marks_locked` in `markErrors.js`
- [x] **Tests:** `MarkServiceTest`, REST tests, optional component smoke
- [x] Run `./vendor/bin/phpunit` and `npm run build`

## Dev Notes

### User request (source)

Enhance reports page:

1. Active menu item background  
2. Icon per menu item  
3. Clear separation — project menu group  
4. Live list: students × rubric criterion marks for chosen review  
5. Overall scores: student × reviewer (+ review score column)  
6. Coordinator freeze — no mark updates after lock  

### Architecture alignment

| Area | Current | Target |
|------|---------|--------|
| `CoordinatorNav` | Text links; `bg-surface-raised` active; “Project” label only | Icons + chip active bg + bordered project group + session title |
| `Reports.jsx` | Download cards only | Tabs: live tables + downloads |
| Review lock | Reviewer freeze (`submitted` per reviewer); session `closed` | New `coordinator_marks_locked` on `pr_reviews` — blocks **all** mark mutations for review |
| Scores UI | `ScoreBreakdown` per student on Progress page | Session-wide matrix on Reports for one review |

**Do not** conflate coordinator lock with:

- Reviewer **Freeze scores** (`MarkService::freeze_review_marks`) — promotes to `submitted` per reviewer.  
- **Session close** (`SessionCloseService`) — disables accounts + session status `closed`.  
- **`marking_active`** — opens/closes reviewer access; keep independent of lock.

### API contracts (suggested)

**`GET /project-reviews/v1/sessions/{session_id}/reviews/{review_id}/marks-grid`**

```json
{
  "review_id": 1,
  "criteria": [{ "id": 10, "label": "Design", "max_marks": 10, "sort_order": 0 }],
  "students": [
    {
      "student_id": 1,
      "reg_no": "21CS001",
      "name": "Ada Lovelace",
      "marks": {
        "10": [
          { "reviewer_user_id": 5, "reviewer_name": "Dr Smith", "score": 8, "status": "submitted", "flagged": false }
        ]
      }
    }
  ],
  "coordinator_marks_locked": false
}
```

Implement in `ReportQueryService` or new `ReportsViewService` — query `pr_marks` + criteria + enrolled students + panel reviewers (same joins as `marks_detail()` but structured for UI).

**`GET .../scores-matrix`**

```json
{
  "review_id": 1,
  "reviewers": [{ "user_id": 5, "name": "Dr Smith" }],
  "students": [
    {
      "student_id": 1,
      "reg_no": "21CS001",
      "name": "Ada Lovelace",
      "reviewer_totals": { "5": 82.5 },
      "review_score": 80.0
    }
  ],
  "coordinator_marks_locked": false
}
```

Delegate reviewer totals / review score to `ScoreService` (already `submitted_only = true`).

**`POST .../lock-marks`** → `{ "coordinator_marks_locked": true }`  
Permission: `pr_manage_sessions`. Idempotent if already locked.

### Files to touch

| File | Change |
|------|--------|
| `src/coordinator/CoordinatorNav.jsx` | Icons, grouping, session title |
| `src/coordinator/pages/Reports.jsx` | Tabs, review select, lock CTA |
| `src/coordinator/components/ReportsMarksTable.jsx` | **new** |
| `src/coordinator/components/ReportsScoresTable.jsx` | **new** |
| `src/shared/components/NavIcon.jsx` or icons inline | **new** optional |
| `includes/Install.php` | `coordinator_marks_locked` column patch |
| `includes/repositories/ReviewRepository.php` | read/update lock flag |
| `includes/services/MarkService.php` | lock guard + audit |
| `includes/rest/class-rest-reports.php` or `class-rest-session-reports-view.php` | new routes |
| `includes/rest/class-rest-bootstrap.php` | register |
| `src/shared/markErrors.js` | new code |
| `tests/MarkServiceTest.php`, `tests/RestReportsTest.php` | lock + endpoints |

### UX references

- Active nav: UX spec — “Active nav item: primary tint background in sidebar” (`ux-design-specification.md` Navigation Patterns).  
- Tables: sticky header, `tabular-nums`, horizontal scroll (Progress / MarkingGrid patterns).  
- Lock: `ConfirmDialog` consequence copy (UX-DR33 style) — explain reviewers cannot edit and overrides are blocked.  
- Tokens: `--pr-chip-active-bg`, `--pr-color-primary` from `assets/css/app-shell.css`.

### Nav icons (inline SVG, no new package)

| Item | Suggestion |
|------|------------|
| Dashboard | grid/home |
| Student Registry | users/list |
| Setup wizard | settings/sliders |
| Progress | bar-chart |
| Rubrics | checklist |
| Reports | document/table |
| Audit log | clock/list |
| Close session | lock/archive |

Keep SVGs ~20×20, `currentColor`, in a small map next to `SESSION_NAV` / global links.

### Previous story intelligence

From **7.3** (`7-3-report-download-ui.md`):

- `Reports.jsx` maps REST catalog to `ReportCard`; keep under Downloads tab.  
- `Rest_Reports::report_catalog()` — seven types; do not break download URLs.

From **7.2** / **7.4**:

- `ReportQueryService::marks_detail()` and `pr_rubric_scores` — reuse SQL/join logic for marks grid payload.  
- Marking grain: session × review × criterion × reviewer × student.

From **6.3** / **6.4**:

- `ProgressTable` sticky table patterns; `ScoreBreakdown` for read-only score copy.  
- `GET /sessions/{id}/students`, `/progress`, `/students/{sid}/scores` — scores matrix is **review-scoped bulk**, not per-student breakdown.

From **5.6** / **5.8**:

- Reviewer freeze uses `submitted`; coordinator lock is orthogonal — lock must block saves even for `draft` rows.  
- Deny `grant_unfreeze` when `coordinator_marks_locked`.

From **8.1**:

- Session `closed` still blocks via existing guards; coordinator lock can apply while session is `active`.

### Anti-patterns (do not)

- Do **not** remove or relocate download endpoints from 7.3.  
- Do **not** add `@wordpress/icons` or other deps solely for nav icons.  
- Do **not** use client-side Excel parsing for live tables.  
- Do **not** implement coordinator lock only in React — enforce in `MarkService`.  
- Do **not** reuse `marking_active = 0` as the lock flag (conflicts with “open marking” semantics).

### Testing checklist

1. Nav: active route highlights correct item; project group visible on session routes.  
2. Marks tab: switch review → table updates; flagged chip visible.  
3. Scores tab: reviewer columns match assignments; review score column matches `ScoreServiceTest` fixture.  
4. Lock: POST lock → reviewer save returns `coordinator_marks_locked`; override blocked.  
5. Downloads tab: still downloads CSV/XLSX for all seven types.  
6. PHPUnit + build green.

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

- PHPUnit 170 tests OK; `npm run build` OK
- Fixed `ScoreServiceTest::test_progress_percent` for nested `calculate_session_progress()` shape

### Completion Notes List

- Coordinator nav: inline SVG icons, chip-active background, bordered project group with session title from `GET /sessions/{id}`
- Reports page: review selector, Rubric marks / Overall scores / Downloads tabs; live tables via `ReportsViewService`; lock CTA with `ConfirmDialog`
- Backend: `coordinator_marks_locked` column + `MarkService` guards + `POST lock-marks` + marks-grid/scores-matrix REST
- Reviewer UX: assignments blocked + marking grid read-only when coordinator locked

### File List

- includes/Install.php
- includes/repositories/ReviewRepository.php
- includes/services/MarkService.php
- includes/services/ReportsViewService.php
- includes/rest/class-rest-reports.php
- includes/rest/class-rest-reviews.php
- includes/rest/class-rest-reviewer-assignments.php
- src/coordinator/CoordinatorNav.jsx
- src/coordinator/pages/Reports.jsx
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/components/ReportsScoresTable.jsx
- src/shared/components/NavIcon.jsx
- src/shared/markErrors.js
- src/reviewer/pages/MarkAssignments.jsx
- src/reviewer/components/MarkingGrid.jsx
- tests/FakeWpdb.php
- tests/InstallSchemaPatchTest.php
- tests/MarkServiceTest.php
- tests/RestReportsTest.php
- tests/ScoreServiceTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css

### Change Log

- 2026-05-17: Story 7-5 — reports live views, nav polish, coordinator marks lock

## References

- [Source: _bmad-output/planning/epics.md — Epic 7]
- [Source: _bmad-output/planning/ux-design-specification.md — Navigation, Reports responsive]
- [Source: _bmad-output/implementation/7-3-report-download-ui.md]
- [Source: _bmad-output/implementation/6-4-score-breakdown.md]
- [Source: _bmad-output/implementation/5-6-reviewer-marking-grid-freeze.md]
- [Source: includes/services/ScoreService.php — three-level scoring]
- [Source: includes/services/ReportQueryService.php — marks_detail joins]
