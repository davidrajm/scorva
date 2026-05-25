# Story 5.15: Panel unfreeze requests and panel-head approval routing

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want to request that my **panel coordinator** (not the project coordinator) unfreeze my frozen personal scores,
So that corrections follow the panel’s chain of authority before project-wide escalation.

As a **panel coordinator** (panel head),
I want pending **reviewer score unfreeze** requests for my panel on a dedicated inbox with one-click approve,
So that I can reopen a reviewer’s marks without involving the project coordinator.

As a **panel coordinator**,
I want to request that the **project coordinator** unfreeze **panel scores only** (lift panel freeze),
So that reviewers can edit again after a mistaken panel freeze without reverting individual submitted marks.

As a **project coordinator**,
I want **panel unfreeze** requests on the dashboard (separate from reviewer score requests),
So that I approve only panel-level lock removal and never mix it with per-reviewer mark revert.

## Acceptance Criteria

### 1. Two request types (do not conflate)

| Type | Grain | Requester | Approver | Grant effect |
|------|-------|-----------|----------|--------------|
| **Reviewer marks** (`reviewer_marks`) | session × review × panel × reviewer_user_id | Assigned reviewer (frozen personal scores) | Panel coordinator for that panel | Existing 5.8: `submitted` → `draft` for that reviewer only |
| **Panel scores** (`panel_freeze`) | session × review × panel | Panel coordinator | Project coordinator (`pr_manage_sessions`) | Delete row in `pr_review_panel_freezes` only — **no** mark status changes |

- **And** reviewer-mark requests use existing `pr_unfreeze_requests` table (extend if needed for routing metadata only — do **not** duplicate mark-revert logic).
- **And** panel-freeze requests use new `pr_panel_unfreeze_requests` table (mirror pending/granted/reason/audit pattern from 5.8).

### 2. Reviewer — request unfreeze (routing change from 5.8)

- **Given** personal scores frozen (`review_frozen === true`) and panel **not** frozen (`panel_scores_frozen === false`)
- **When** reviewer submits **Request unfreeze** with reason (existing flow)
- **Then** pending row created as today; copy says **panel coordinator** must approve (not project coordinator)
- **And** `POST` idempotency unchanged (return existing pending row)
- **When** `panel_scores_frozen === true`
- **Then** **Request unfreeze** hidden/disabled; copy: unfreeze personal scores is unavailable while the panel is frozen — panel coordinator must request panel unfreeze from the project coordinator first
- **When** coordinator marks locked or session closed
- **Then** same guards as 5.8

### 3. Panel coordinator — reviewer unfreeze inbox

- **Given** user is panel coordinator for panel P on review R (`ReviewAssignmentRepository::is_panel_head_for_user`)
- **When** pending `reviewer_marks` requests exist for panels they head
- **Then** reviewer SPA shows **Pending reviewer unfreeze requests** section (default placement: top of **Mark assignments** home `/`, or sub-section on panel report — **default: assignments home** so one inbox covers all panels they head)
- **And** each row: project title, review label, panel name, reviewer name, reason, requested_at
- **And** **Approve unfreeze** opens `ConfirmDialog` with same consequences as 5.8 coordinator dialog (revert to draft, progress drops until re-freeze)
- **When** approved
- **Then** `POST /reviewer/unfreeze-requests/{id}/grant` runs mark revert + request `granted` + audit `unfreeze_granted` (entity `unfreeze_request`)
- **And** only panel coordinator for that assignment’s panel may grant; other reviewers `403`
- **And** project coordinators **cannot** grant reviewer-mark requests via dashboard anymore (remove from coordinator `UnfreezeRequests` component)

### 4. Panel coordinator — request panel unfreeze

- **Given** panel report page and `panel_frozen === true`
- **When** panel coordinator clicks **Request panel unfreeze**
- **Then** `ConfirmDialog` explains project coordinator must approve; panel freeze remains until granted
- **When** confirmed with reason (required, max 500 chars, same validation as reviewer reason)
- **Then** `POST /reviewer/panel-reports/{session_id}/{review_id}/{panel_id}/unfreeze-request` creates pending `pr_panel_unfreeze_requests` row
- **And** UI shows **Panel unfreeze requested** chip; button disabled while pending
- **When** panel not frozen
- **Then** POST `400` `panel_not_frozen`
- **When** not panel coordinator
- **Then** `403` `not_panel_coordinator`

### 5. Project coordinator — panel unfreeze queue only

- **Given** coordinator dashboard
- **When** pending `panel_freeze` requests exist
- **Then** section **Pending panel unfreeze requests** (replace or rename current **Unfreeze requests** — **must not** list reviewer-mark requests)
- **And** each row: project, review, panel, panel coordinator name, reason, requested_at
- **When** **Approve panel unfreeze** confirmed
- **Then** `POST /panel-unfreeze-requests/{id}/grant` removes freeze via `PanelFreezeRepository::unfreeze()` (new method: delete matching `review_id` + `panel_id` row)
- **And** request `granted` + audit `panel_unfreeze_granted` (entity `panel_unfreeze_request`)
- **And** **no** mark rows change status
- **When** no pending panel requests
- **Then** section omitted

### 6. After panel unfreeze grant

- **Given** coordinator granted panel unfreeze
- **When** panel coordinator reloads panel report
- **Then** `panel_frozen === false`; **Freeze panel scores** available again if business rules allow
- **When** reviewers on that panel reload marking grid
- **Then** `panel_scores_frozen === false`; they may save/freeze personal scores again (subject to personal freeze state)
- **And** existing personal `submitted` marks remain submitted until reviewer freezes/unfreezes personally

### 7. After panel head grants reviewer unfreeze

- Unchanged from 5.8 AC #4: personal draft, save works, success notice copy references **panel coordinator** not project coordinator.

### 8. Security and scoping

- Reviewer: create own `reviewer_marks` request only.
- Panel head: list/grant `reviewer_marks` only for panels where `is_panel_head_for_user(review_id, panel_id, current_user)`.
- Panel head: create `panel_freeze` request only for own panel report.
- Project coordinator: list/grant `panel_freeze` only with `pr_manage_sessions`.
- Panel head cannot grant panel unfreeze; reviewer cannot grant any grant endpoint.

### 9. Regression / non-goals

- Personal freeze/unfreeze mechanics (5.6) unchanged except approver role and coordinator UI removal.
- Panel freeze prerequisites (all reviewers submitted, etc.) unchanged on freeze path.
- Admin mark override (9.2) separate.
- **Out of scope:** email notifications; reject/deny with reason; bulk approve; coordinator granting reviewer marks (escalation); auto-unfreeze panel when last reviewer unfreezes.

## Tasks / Subtasks

- [x] **Schema:** `pr_panel_unfreeze_requests` in `Install::table_panel_unfreeze_requests()` + `ensure_panel_unfreeze_requests_table()` patch; columns: `id`, `session_id`, `review_id`, `panel_id`, `requested_by_user_id`, `reason`, `status` (`pending`|`granted`), `requested_at`, `resolved_at`, `resolved_by_user_id`; one pending per `(session_id, review_id, panel_id)` enforced in repository
- [x] **PanelFreezeRepository:** `unfreeze(int $review_id, int $panel_id): bool` — delete freeze row
- [x] **PanelUnfreezeRequestRepository** — `create_pending`, `find_pending_for_panel`, `list_pending_for_coordinator`, `grant`, `has_pending`
- [x] **UnfreezeRequestRepository:** `list_pending_for_panel_head(int $user_id, int $limit)` — join assignments where user is panel head for `review_id`+`panel_id`
- [x] **MarkService:** `grant_unfreeze` — require panel head (not `pr_manage_sessions`); add `assert_panel_head_for_request()` using `ReviewAssignmentRepository::is_panel_head_for_user`
- [x] **PanelReportService:** `request_panel_unfreeze`, `grant_panel_unfreeze` (coordinator actor) — grant calls `PanelFreezeRepository::unfreeze` only
- [x] **REST reviewer:** `GET /reviewer/unfreeze-requests?status=pending`, `POST /reviewer/unfreeze-requests/{id}/grant` (panel head cap via assignment check, not coordinator cap)
- [x] **REST reviewer:** `POST .../panel-reports/.../unfreeze-request` on `class-rest-panel-reports.php`
- [x] **REST coordinator:** new `class-rest-panel-unfreeze-requests.php` — `GET /panel-unfreeze-requests`, `POST /panel-unfreeze-requests/{id}/grant`; register in bootstrap
- [x] **REST coordinator:** remove or restrict `GET/POST /unfreeze-requests/*` for reviewer marks (deprecate coordinator grant path — return `403` with code `use_panel_head_grant` if called, or delete routes and update tests)
- [x] **Frontend reviewer:** `PanelHeadUnfreezeRequests.jsx` on `MarkAssignments.jsx`; update `MarkingGrid.jsx` copy (panel coordinator); block request when `panel_scores_frozen`
- [x] **Frontend reviewer:** `PanelReportPage.jsx` — request panel unfreeze when frozen + pending state
- [x] **Frontend coordinator:** rename/split `UnfreezeRequests.jsx` → `PanelUnfreezeRequests.jsx` using `/panel-unfreeze-requests`; remove reviewer rows
- [x] **Tests:** extend `RestUnfreezeRequestsTest` / new `RestPanelUnfreezeRequestsTest`; panel head grant in `RestReviewerAssignmentsTest` or dedicated test; `PanelFreezeRepository` unfreeze; regression: coordinator cannot grant reviewer marks; panel grant does not revert marks

## Dev Notes

### User request (source)

1. Implement **panel unfreeze** like 5.8 but affects **panel freeze only** (not individual reviewer marks).
2. **Reviewer unfreeze requests** → **panel head** approves (for their reviewers).
3. **Panel unfreeze** → **project coordinator** only.

### Architecture alignment

| Area | Current (5.8 + 11.1) | Target |
|------|----------------------|--------|
| Reviewer unfreeze approver | Project coordinator dashboard | Panel coordinator reviewer SPA inbox |
| Coordinator dashboard unfreeze | All `pr_unfreeze_requests` | `pr_panel_unfreeze_requests` only |
| Panel frozen | Blocks all mark saves | Panel head may request lift; coordinator grants delete freeze row |
| Mark revert on panel unfreeze | N/A | **Never** — only on reviewer-mark grant |

**Reuse:** `UnfreezeRequestRepository`, `MarkService::unfreeze_review_marks`, `MarkingGrid` request UI, `ConfirmDialog`, `AuditService`, `UnfreezeRequests.jsx` layout patterns.

### Critical files (touch list)

**Frontend**

- `src/reviewer/pages/MarkAssignments.jsx` — panel head inbox
- `src/reviewer/components/PanelHeadUnfreezeRequests.jsx` — **new** (adapt from `UnfreezeRequests.jsx`)
- `src/reviewer/components/MarkingGrid.jsx` — copy + panel-frozen guard on request
- `src/reviewer/pages/PanelReportPage.jsx` — panel unfreeze request
- `src/coordinator/components/PanelUnfreezeRequests.jsx` — **new** or refactor `UnfreezeRequests.jsx`
- `src/coordinator/pages/Dashboard.jsx` — wire panel-only component
- `src/shared/markErrors.js` — `panel_not_frozen`, `panel_unfreeze_pending`, `not_panel_coordinator`, `use_panel_head_grant`

**Backend**

- `includes/Install.php` — `pr_panel_unfreeze_requests` + patch
- `includes/repositories/PanelUnfreezeRequestRepository.php` — **new**
- `includes/repositories/PanelFreezeRepository.php` — `unfreeze()`
- `includes/repositories/UnfreezeRequestRepository.php` — `list_pending_for_panel_head()`
- `includes/services/MarkService.php` — panel-head grant guard
- `includes/services/PanelReportService.php` — panel unfreeze request/grant
- `includes/rest/class-rest-unfreeze-requests.php` — remove coordinator reviewer grant OR 403
- `includes/rest/class-rest-panel-unfreeze-requests.php` — **new**
- `includes/rest/class-rest-panel-reports.php` — panel unfreeze request route
- `includes/rest/class-rest-reviewer-assignments.php` — optional: panel-head list/grant routes if not separate class
- `includes/rest/class-rest-bootstrap.php` — register routes

**Tests**

- `tests/RestUnfreezeRequestsTest.php` — update coordinator expectations
- `tests/RestPanelUnfreezeRequestsTest.php` — **new**
- `tests/PanelHeadTest.php` or new panel-head unfreeze tests
- `tests/MarkServiceTest.php` — panel head grant authorization

### API contract sketch

**POST** `/pr/v1/reviewer/panel-reports/{session_id}/{review_id}/{panel_id}/unfreeze-request`

```json
{ "reason": "Wrong panel frozen after PDF review." }
```

Response `201`:

```json
{
  "id": 4,
  "status": "pending",
  "requested_at": "2026-05-17T12:00:00Z"
}
```

**GET** `/pr/v1/panel-unfreeze-requests?status=pending` (coordinator)

```json
{
  "requests": [{
    "id": 4,
    "session_id": 1,
    "session_title": "Capstone 2026",
    "review_id": 2,
    "review_label": "Review 1",
    "panel_id": 3,
    "panel_name": "Panel A",
    "requested_by_user_id": 45,
    "panel_coordinator_name": "Dr. Lee",
    "reason": "...",
    "requested_at": "2026-05-17T12:00:00Z"
  }]
}
```

**POST** `/pr/v1/panel-unfreeze-requests/{id}/grant`

Response `200`:

```json
{ "granted": true, "panel_unfrozen": true }
```

**GET** `/pr/v1/reviewer/unfreeze-requests?status=pending` (panel head)

Same shape as current coordinator list (reviewer name, reason, etc.) but filtered to headed panels.

**POST** `/pr/v1/reviewer/unfreeze-requests/{id}/grant` (panel head)

Same response as today: `{ "granted": true, "marks_reverted": 48 }`

**Deprecate for reviewer marks:** `POST /pr/v1/unfreeze-requests/{id}/grant` — return `403` `use_panel_head_grant` (update `RestUnfreezeRequestsTest`).

### Grant symmetry

| Action | Actor | Scope | DB effect |
|--------|-------|-------|-----------|
| Freeze personal | Reviewer | panel + review + self | `draft` → `submitted` |
| Request unfreeze personal | Reviewer | same | `pr_unfreeze_requests` pending |
| Grant unfreeze personal | Panel head | same assignment | `submitted` → `draft` + request granted |
| Freeze panel | Panel head | review + panel | insert `pr_review_panel_freezes` |
| Request panel unfreeze | Panel head | same | `pr_panel_unfreeze_requests` pending |
| Grant panel unfreeze | Project coordinator | same | delete freeze row only |

### Panel head resolution

Use **`ReviewAssignmentRepository::is_panel_head_for_user($review_id, $panel_id, $user_id)`** (per-review assignment row), not session-level `PanelRepository::find_panel_head` alone — matches panel report authorization in `PanelReportService::assert_panel_coordinator`.

### UX hints

- Panel head inbox: reuse `UnfreezeRequests` list/card styling; heading **Reviewer unfreeze requests**.
- Coordinator section: **Panel unfreeze requests**; confirm dialog stresses marks stay as-is, only panel lock lifts.
- `PanelReportPage` freeze dialog bullet “Only a project coordinator can override” → update to “Request panel unfreeze from the project coordinator” after this story.
- `MarkingGrid` pending chip: “Unfreeze requested — awaiting panel coordinator”.

### Panel frozen vs personal frozen interaction

```text
panel NOT frozen + personal frozen → reviewer may request personal unfreeze → panel head grants
panel frozen → personal unfreeze request blocked; panel head requests panel unfreeze → coordinator grants → panel NOT frozen → then personal unfreeze rules apply again
```

### Previous story intelligence

**5.8 (`unfreeze-score-requests`):** Full implementation exists — coordinator dashboard grant must be **removed/migrated** for reviewer marks; keep mark revert logic.

**11.1 (`panel-head-reports-pdf-freeze`):** `PanelFreezeRepository`, `freeze_panel`, `panel_scores_frozen` on students payload, `PanelReportPage` — add unfreeze request UI here; do not regress freeze guards.

**5.6 / 5.7:** Freeze completeness and absent students unchanged; panel unfreeze does not mass-revert marks.

### Anti-patterns (do not)

- Do **not** call `unfreeze_review_marks` when granting panel unfreeze.
- Do **not** let project coordinator grant reviewer personal unfreeze on dashboard (product decision for this story).
- Do **not** let panel head grant panel unfreeze (coordinator only).
- Do **not** merge panel and reviewer requests into one table without `request_type` discipline — separate tables preferred for clear grants.
- Do **not** delete `pr_unfreeze_requests` rows on panel unfreeze.

### Testing requirements

1. Reviewer freezes → requests → panel head grants → marks draft; coordinator endpoint for same request returns `403`.
2. Panel head freezes panel → requests panel unfreeze → coordinator grants → freeze row gone; submitted marks still submitted.
3. While panel frozen, reviewer cannot create personal unfreeze request.
4. Non-head reviewer cannot grant panel head endpoint.
5. Panel head cannot grant panel unfreeze coordinator endpoint.
6. `composer test` green; `npm run build` green.

### References

- [Source: _bmad-output/implementation/5-8-unfreeze-score-requests.md]
- [Source: _bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md]
- [Source: includes/repositories/UnfreezeRequestRepository.php, PanelFreezeRepository.php]
- [Source: includes/services/MarkService.php, PanelReportService.php]
- [Source: src/coordinator/components/UnfreezeRequests.jsx]
- [Source: src/reviewer/components/MarkingGrid.jsx, pages/PanelReportPage.jsx]
- [Source: includes/repositories/ReviewAssignmentRepository.php — is_panel_head_for_user]

## Dev Agent Record

### Agent Model Used

Auto (dev-story)

### Debug Log References

### Completion Notes List

- Split unfreeze into two flows: reviewer marks → panel coordinator (`/reviewer/unfreeze-requests`); panel freeze → project coordinator (`/panel-unfreeze-requests`).
- Coordinator legacy `/unfreeze-requests` list returns empty; grant returns `403 use_panel_head_grant`.
- Panel grant deletes `pr_review_panel_freezes` row only; personal `submitted` marks unchanged.
- PHPUnit 211 tests OK; `npm run build` OK.

### File List

- includes/Install.php
- includes/repositories/PanelFreezeRepository.php
- includes/repositories/PanelUnfreezeRequestRepository.php
- includes/repositories/UnfreezeRequestRepository.php
- includes/services/MarkService.php
- includes/services/PanelReportService.php
- includes/rest/class-rest-bootstrap.php
- includes/rest/class-rest-panel-reports.php
- includes/rest/class-rest-panel-unfreeze-requests.php
- includes/rest/class-rest-reviewer-unfreeze-requests.php
- includes/rest/class-rest-unfreeze-requests.php
- src/coordinator/components/PanelUnfreezeRequests.jsx
- src/coordinator/pages/Dashboard.jsx
- src/reviewer/components/PanelHeadUnfreezeRequests.jsx
- src/reviewer/components/MarkingGrid.jsx
- src/reviewer/pages/MarkAssignments.jsx
- src/reviewer/pages/PanelReportPage.jsx
- src/shared/markErrors.js
- tests/MarkServiceTest.php
- tests/PanelHeadTest.php
- tests/RestPanelUnfreezeRequestsTest.php
- tests/RestUnfreezeRequestsTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css

## Change Log

- 2026-05-17: Story 5.15 — panel unfreeze requests (coordinator), reviewer unfreeze routed to panel head approval.
- 2026-05-17: Implemented story 5.15 (dev-story) — schema, REST, UI, tests.
