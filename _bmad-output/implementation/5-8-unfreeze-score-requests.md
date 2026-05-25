# Story 5.8: Reviewer unfreeze requests and coordinator dashboard approval

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want to request that a coordinator unfreeze my frozen scores for a review assignment,
So that I can correct mistakes after finalizing.

As a **coordinator**,
I want pending unfreeze requests visible on the dashboard with a one-click approve action,
So that I can reopen marking quickly without hunting through sessions.

## Acceptance Criteria

1. **Reviewer — request unfreeze (frozen assignment only)**
   - **Given** the marking grid for an assignment where `review_frozen === true` (all marks for this reviewer + review + panel are `submitted`)
   - **When** the reviewer clicks **Request unfreeze**
   - **Then** a `ConfirmDialog` explains that a coordinator must approve before editing resumes
   - **When** confirmed
   - **Then** `POST /reviewer/assignments/{session_id}/{review_id}/unfreeze-request` with body `{ panel_id }` creates a pending request at grain **session × review × panel × reviewer_user_id**
   - **And** UI shows state **Unfreeze requested** (`StatusChip` variant `unlocked` or dedicated copy) and disables **Request unfreeze** while pending
   - **And** **Update score** and **Freeze scores** remain disabled until granted (still frozen)
   - **When** no pending/granted cycle is active and not frozen
   - **Then** **Request unfreeze** is hidden
   - **When** a pending request already exists for this assignment
   - **Then** POST is idempotent (returns existing request, `200`) or `409` with code `unfreeze_request_pending` — pick one and document in API sketch
   - **When** review is not frozen
   - **Then** POST returns `400` code `not_frozen`

2. **Coordinator — dashboard queue**
   - **Given** the coordinator dashboard (`src/coordinator/pages/Dashboard.jsx`)
   - **When** one or more pending unfreeze requests exist for sessions the user can manage (`pr_manage_sessions`)
   - **Then** a **Pending unfreeze requests** section appears above or below the session cards list (not buried inside a session)
   - **And** each row shows: project/session title, review label, panel name, reviewer display name, requested_at (relative or short datetime)
   - **And** primary action **Approve unfreeze** per row
   - **When** there are no pending requests
   - **Then** the section is omitted (no empty card noise)
   - **And** data loads via `GET /unfreeze-requests?status=pending` (or nested under `/sessions` if you prefer — **default:** top-level list endpoint for dashboard)

3. **Coordinator — grant unfreeze**
   - **Given** a pending request row
   - **When** the coordinator clicks **Approve unfreeze**
   - **Then** `ConfirmDialog` summarizes impact: marks for that reviewer on that review/panel revert to **draft**; combined scores/progress drop until reviewer re-freezes; reviewer can edit again
   - **When** confirmed
   - **Then** `POST /unfreeze-requests/{id}/grant` (coordinator cap `pr_manage_sessions`) runs server-side unfreeze
   - **And** all `pr_marks` rows for that `session_id`, `review_id`, `reviewer_user_id`, and students on that `panel_id` with `status = submitted` are updated to `status = draft` (scores and `flagged` unchanged)
   - **And** request status becomes `granted` with `resolved_at` and `resolved_by_user_id`
   - **And** `AuditService::log()` records action `unfreeze_granted` (entity `unfreeze_request`, entity_id = request id; `new_value` JSON with session/review/panel/reviewer)
   - **And** dashboard list refreshes without the row
   - **When** session is `closed` or review `marking_inactive` / rubric not confirmed
   - **Then** grant still allowed if product needs correction after mis-close — **default:** allow grant when session `active` only; return `403` `session_closed` when closed (match freeze guards)

4. **Reviewer — after grant (resume editing)**
   - **Given** coordinator granted the request
   - **When** the reviewer reloads or refetches the marking grid
   - **Then** `review_frozen === false`, **Update score** enabled, **Freeze scores** enabled
   - **And** student `mark_status` reflects draft/not_started (not `frozen`)
   - **And** optional success `Notice`: “Your coordinator approved unfreeze. You can edit scores and freeze again when ready.”
   - **And** `POST` marks no longer returns `marks_frozen` for that assignment
   - **And** `ScoreService` / progress exclude reverted marks until reviewer freezes again (existing `submitted` filter)

5. **Security and scoping**
   - **And** only the assigned reviewer can create a request for their assignment
   - **And** coordinators without `pr_manage_sessions` cannot list or grant
   - **And** grant validates request is `pending` and assignment still exists
   - **And** reviewers cannot call grant endpoint (`403`)

6. **Regression / non-goals**
   - **And** freeze flow from story 5.6 unchanged except new request button when frozen
   - **And** admin mark override (9.2) remains separate; no merge with override UI in this story
   - **Out of scope:** reviewer self-unfreeze without coordinator; email notifications (9.4); dismiss/reject reason workflow; bulk approve all; session progress page queue (dashboard only)

## Tasks / Subtasks

- [x] **Schema:** Add `pr_unfreeze_requests` in `Install::table_unfreeze_requests()` + `ensure_unfreeze_requests_table()` patch; columns: `id`, `session_id`, `review_id`, `panel_id`, `reviewer_user_id`, `status` (`pending`|`granted`), `requested_at`, `resolved_at`, `resolved_by_user_id`; **UNIQUE** `(session_id, review_id, panel_id, reviewer_user_id, status)` only for pending — use app logic: one pending per assignment (unique key on pending via partial index not in MySQL — enforce in repository)
- [x] **Repository:** `UnfreezeRequestRepository` — `create_pending`, `find_pending_for_assignment`, `list_pending_for_coordinator`, `grant`, `has_pending`
- [x] **MarkService:** `unfreeze_review_marks($session_id, $review_id, $panel_id, $reviewer_user_id)` — guards + delegate `MarkRepository::revert_to_draft_for_students()`; used only from grant path
- [x] **MarkRepository:** `revert_to_draft_for_students()` mirror of `submit_for_students`
- [x] **REST reviewer:** `POST .../unfreeze-request` in `class-rest-reviewer-assignments.php`
- [x] **REST coordinator:** `GET /unfreeze-requests`, `POST /unfreeze-requests/{id}/grant` in new `class-rest-unfreeze-requests.php` + bootstrap register
- [x] **Extend list_students:** include `unfreeze_request_status: null | "pending" | "granted"` (optional: only `pending` needed for UI)
- [x] **Frontend reviewer:** `MarkingGrid.jsx` — Request unfreeze button, pending chip, copy in `markErrors.js` if needed
- [x] **Frontend coordinator:** `Dashboard.jsx` — pending queue section + grant confirm; optional `UnfreezeRequestRow.jsx` component
- [x] **Tests:** `UnfreezeRequestRepositoryTest` or service tests; `MarkServiceTest` revert draft; `RestReviewerAssignmentsTest` request paths; new `RestUnfreezeRequestsTest`; extend freeze test to ensure revert drops submitted count
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

After story 5.6 freeze:

1. Reviewer gets a **Request unfreeze** button when scores are frozen.
2. Coordinator sees pending requests on the **dashboard** and approves.
3. Reviewer can **edit marks again** after approval (re-freeze when done).

Clarification: “reviewer should be able to unfreeze” means **resume editing after coordinator approval**, not bypass coordinator.

### Architecture alignment

| Area | Current (5.6) | Target |
|------|----------------|--------|
| Frozen state | All mark rows `submitted` for panel students | Unchanged until grant |
| Reviewer actions when frozen | Read-only grid | + Request unfreeze |
| Coordinator visibility | None | Dashboard pending queue |
| Unfreeze mechanism | N/A | Bulk `submitted` → `draft` for assignment grain |
| Reports / scores | Count `submitted` | Reverted marks excluded until re-freeze |

**Do not** add a new mark `status` value — reuse `draft` / `submitted` like freeze.

### Critical files (touch list)

**Frontend**

- `src/reviewer/components/MarkingGrid.jsx` — request button, pending state
- `src/coordinator/pages/Dashboard.jsx` — pending queue
- `src/shared/markErrors.js` — `not_frozen`, `unfreeze_request_pending` (if used)
- `src/shared/components/ConfirmDialog.jsx` — reuse for request + grant

**Backend**

- `includes/Install.php` — new table + migration patch
- `includes/repositories/UnfreezeRequestRepository.php` — **new**
- `includes/repositories/MarkRepository.php` — `revert_to_draft_for_students`
- `includes/services/MarkService.php` — `request_unfreeze`, `grant_unfreeze` / `unfreeze_review_marks`
- `includes/rest/class-rest-reviewer-assignments.php` — request route
- `includes/rest/class-rest-unfreeze-requests.php` — **new**
- `includes/rest/class-rest-bootstrap.php` — register routes
- `includes/services/AuditService.php` — log grant (request log optional)

**Tests**

- `tests/MarkServiceTest.php`
- `tests/RestReviewerAssignmentsTest.php`
- `tests/RestUnfreezeRequestsTest.php` — **new**
- `tests/InstallSchemaTest.php` or patch test for table

### API contract sketch

**POST** `/pr/v1/reviewer/assignments/{session_id}/{review_id}/unfreeze-request`

```json
{ "panel_id": 3 }
```

Response `201`:

```json
{
  "id": 12,
  "status": "pending",
  "requested_at": "2026-05-17T10:00:00Z"
}
```

**GET** `/pr/v1/unfreeze-requests?status=pending`

```json
{
  "requests": [{
    "id": 12,
    "session_id": 1,
    "session_title": "Capstone 2026",
    "review_id": 2,
    "review_label": "Review 1",
    "panel_id": 3,
    "panel_name": "Panel A",
    "reviewer_user_id": 45,
    "reviewer_name": "Dr. Lee",
    "requested_at": "2026-05-17T10:00:00Z"
  }]
}
```

**POST** `/pr/v1/unfreeze-requests/{id}/grant`

Response `200`:

```json
{
  "granted": true,
  "marks_reverted": 48
}
```

**GET** `.../students` extension:

```json
{
  "review_frozen": true,
  "unfreeze_request_status": "pending",
  ...
}
```

### Grant vs freeze symmetry

| Action | Actor | Scope | DB effect |
|--------|-------|-------|-----------|
| Freeze | Reviewer | panel + review + self | `draft` → `submitted` |
| Request unfreeze | Reviewer | same | insert `pending` row |
| Grant unfreeze | Coordinator | same assignment | `submitted` → `draft` + request `granted` |

### Dashboard UX hints

- Use `Card` or bordered list consistent with `SessionCard` spacing (`Dashboard.jsx`).
- **Approve unfreeze** = primary `Button`; no navigation required — inline grant + toast/`Notice` success.
- Cap list at 50 pending; show count in section heading if > 0.
- Link session title to `/session/{id}/progress` optional enhancement — not required for AC.

### Reviewer UX hints

- Place **Request unfreeze** in `PageHeader` `actions` next to **Frozen** chip (replace chip when pending: “Unfreeze requested”).
- ConfirmDialog body: one sentence + who must act (coordinator).

### Score / progress impact

- `ScoreService` and `ReportQueryService::count_submitted_marks` already key off `submitted` — reverting to `draft` automatically removes from aggregates until re-freeze.
- No `ExportService` changes.

### Previous story intelligence

From `5-6-reviewer-marking-grid-freeze.md`:

- Freeze endpoint: `POST .../freeze` with `{ panel_id }`; `MarkService::freeze_review_marks`, `submit_for_students`, `is_student_frozen_for_reviewer`, `marks_frozen` on save.
- UI: `MarkingGrid.jsx` freeze toolbar; `review_frozen` on students payload.
- Explicitly deferred coordinator unfreeze — **this story implements that path.**

From `5-7-student-attendance-marking.md`:

- Freeze completeness skips absent students; unfreeze revert must include **all** students on panel with submitted rows (including absent null-score rows).

From `9-1-audit-service.md` / `9-2-mark-override.md`:

- Use `AuditService::log` for grant; do not conflate with per-mark override.

### Anti-patterns (do not)

- Do **not** let reviewers call grant or bulk-revert marks without a pending request (unless you add explicit “coordinator initiated” later).
- Do **not** delete mark rows on unfreeze — only change `status`.
- Do **not** add `frozen` status column on `pr_marks`.
- Do **not** build coordinator unfreeze only inside session wizard — dashboard AC is required.
- Do **not** send email in this story.

### Testing requirements

1. Reviewer freezes → request unfreeze → pending UI → coordinator grants → reviewer saves draft successfully.
2. Grant reverts all submitted rows for that reviewer/panel/review; `ScoreService` total drops until re-freeze.
3. Second request while pending → idempotent or `409` per chosen contract.
4. Request while not frozen → `not_frozen`.
5. Non-coordinator cannot `GET`/`POST` grant endpoints.
6. `composer test` green; `npm run build` green.

### References

- [Source: _bmad-output/implementation/5-6-reviewer-marking-grid-freeze.md]
- [Source: _bmad-output/implementation/5-7-student-attendance-marking.md]
- [Source: _bmad-output/planning/epics.md — Epic 5, FR15, FR16]
- [Source: _bmad-output/planning/ux-design-specification.md — ConfirmDialog, StatusChip, Dashboard hub]
- [Source: includes/services/MarkService.php, MarkRepository.php]
- [Source: src/reviewer/components/MarkingGrid.jsx, src/coordinator/pages/Dashboard.jsx]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

### Completion Notes List

- Added `pr_unfreeze_requests` table with schema patch; one pending request per assignment enforced in repository.
- Reviewer `POST .../unfreeze-request` is idempotent (returns existing pending row). Grant reverts `submitted` marks to `draft` and logs `unfreeze_granted` audit.
- Coordinator dashboard lists pending requests with inline approve + ConfirmDialog; marking grid shows request/pending states.

### File List

- includes/Install.php
- includes/repositories/UnfreezeRequestRepository.php
- includes/repositories/MarkRepository.php
- includes/services/MarkService.php
- includes/rest/class-rest-reviewer-assignments.php
- includes/rest/class-rest-unfreeze-requests.php
- includes/rest/class-rest-bootstrap.php
- src/reviewer/components/MarkingGrid.jsx
- src/coordinator/pages/Dashboard.jsx
- src/shared/markErrors.js
- tests/FakeWpdb.php
- tests/MarkServiceTest.php
- tests/RestReviewerAssignmentsTest.php
- tests/RestUnfreezeRequestsTest.php
- tests/InstallSchemaTest.php
- tests/InstallSchemaPatchTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css

## Change Log

- 2026-05-17: Story 5.8 — reviewer unfreeze request, coordinator dashboard approval, bulk revert to draft.
- 2026-05-17: Implemented end-to-end unfreeze request/grant flow with tests and frontend (status → review).
