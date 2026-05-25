# Story 10.1: Rename user-facing “session” to “project”

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator or reviewer**,
I want **every user-visible label that says “session” to say “project” instead**,
So that the product language matches how our faculty think about a review event (a project review cycle), not an abstract “session.”

## Acceptance Criteria

1. **User-facing copy — coordinator SPA**
   - **Given** the coordinator app (`src/coordinator/**`, `src/shared/**` strings shown in coordinator flows)
   - **When** any visible heading, button, nav label, empty state, notice, confirm dialog, CSV import blurb, wizard copy, or progress summary is rendered
   - **Then** it does **not** contain the word “session” (case-insensitive) except where it refers to a **different** concept (e.g. browser “session” — none expected today)
   - **And** the replacement term is **project** with normal English capitalization:
     - Sentence/body: “this project”, “the project is closed”, “project defaults”
     - Titles/nav/buttons: “Close project”, “Project setup”, “No projects yet” (match existing Dashboard tone)
   - **And** the following known stragglers are updated (non-exhaustive checklist — grep to find others):
     - `CoordinatorNav.jsx`: “Close session” → “Close project”
     - `CloseSession.jsx`: all consequence copy and page title
     - `SessionWizard.jsx`: “Loading session…”, “Session not found”, “session default panel”, load error messages
     - `SessionPlaceholder.jsx`: “Session setup”, “Session”
     - `WizardNav.jsx`: `aria-label` “Session setup steps” → “Project setup steps”
     - `ReviewProgressSummary.jsx`: rollup prefix “Session:” → “Project:” (or “Overall project:” if clearer)
     - `ReviewAssignmentsStep.jsx`: “session defaults” strings
     - `ReviewRoundsStep.jsx` / `ReviewRubricBlock.jsx`: “when the session is active”
     - `PanelReviewersStep.jsx`, `CsvImportMapper.jsx`, `RubricsPanel.jsx`, `Reports.jsx`, `AuditLog.jsx`, `Registry.jsx` descriptions
     - `UnfreezeRequests.jsx` if it mentions session

2. **User-facing copy — reviewer SPA**
   - **Given** reviewer routes (`src/reviewer/**`)
   - **When** assignments list, blocked states, and empty states render
   - **Then** “session” is replaced with “project” (e.g. `MarkAssignments.jsx` blocked copy, empty-state “Select a session…”)
   - **And** `markErrors.js` messages for `session_closed`, `session_not_active`, and `fixByLabel` (“session coordinator” → “project coordinator” or “coordinator” per tone)

3. **User-facing copy — PHP REST and services**
   - **Given** API errors and admin strings returned to the UI or emails (`includes/**/*.php` using `__()` / `esc_html__()`)
   - **When** the message is shown to coordinators, reviewers, or admins
   - **Then** user-visible English strings use “project” not “session”
   - **And** update at minimum:
     - `MarkService.php` — closed / not active / not found messages
     - `class-rest-sessions.php` — validation and enrolment errors
     - `class-rest-reviewer-assignments.php`, `class-rest-reviewers.php`, `class-rest-reviews.php`, `class-rest-review-assignments.php`, `class-rest-session-close.php`, `class-rest-reports.php`
     - `ReviewerProvisionService.php`
     - `ReportsViewService.php`
     - `class-admin-settings.php` — “Email when a project is closed”
     - `SessionClosedEmail.php`, `ReviewerInviteEmail.php`, `RubricOpenEmail.php` (subjects and body copy)
   - **And** **error codes stay unchanged** (`session_closed`, `session_not_active`, `pr_session_not_found`, etc.) — only human-readable `message` fields change

4. **Do not rename implementation identifiers (regression guard)**
   - **Given** this is a **terminology/copy** story only
   - **When** the dev agent completes work
   - **Then** the following are **unchanged** unless a separate refactor story is opened:
     - Database tables/columns (`pr_sessions`, `session_id`, …)
     - REST paths (`/wp-json/project-reviews/v1/sessions/...`)
     - Hash routes (`#/session/:id/...`)
     - React component/file names (`SessionCard`, `SessionWizard`, `CloseSession`, …)
     - PHP class names (`SessionRepository`, `SessionCloseService`, …)
     - JS variables (`sessionId`, `session`, state keys)
     - Export fact column `project_id` (already correct)
   - **And** `SessionCard` component may remain named internally; no requirement to rename to `ProjectCard` in this story

5. **Consistency with partial migration**
   - **Given** Dashboard and parts of SessionWizard already say “project”
   - **When** the sweep is complete
   - **Then** no screen mixes “session” and “project” for the same concept on one flow (e.g. dashboard “Create project” but wizard “Loading session…”)
   - **And** sidebar section label remains **Project** (already in `CoordinatorNav.jsx`)

6. **Verification**
   - **Given** implementation is done
   - **When** searching user-facing surfaces
   - **Then** `rg -i 'session' src/` returns only code identifiers (paths, variable names, API URLs, import types like `session-enrol`), not user-visible string literals
   - **And** `rg 'session' includes/ --glob '*.php'` finds no `__()` strings with “session” except comments/docblocks
   - **And** `composer test` and `npm run build` pass
   - **And** spot-check: Dashboard, wizard, progress rollup, close project, reviewer blocked state, one REST error in browser network tab

## Tasks / Subtasks

- [x] **Inventory:** `rg -i '"[^"]*session[^"]*"' src/` and `rg "__\([^)]*session" includes/` — track every user string (AC: 1–3)
- [x] **Coordinator UI:** Update all string literals from inventory (AC: 1, 5)
- [x] **Reviewer UI + markErrors:** Update blocked/empty/error copy (AC: 2)
- [x] **PHP messages + emails + admin:** Update `__()` strings and email templates (AC: 3)
- [x] **Verify:** ripgrep guards + manual spot-check + PHPUnit + build (AC: 6)

## Dev Notes

### Why this story exists

Planning docs and early UX mocks used **session** for a review event. Faculty and coordinators think in **projects** (B.Tech project review, M.Tech project defence). Epic 3 story 3.8 and export schema already use “project” in places (`project_id`, “project roster”). Dashboard and wizard were **partially** migrated; this story finishes the product-wide copy sweep without a risky code rename.

### Terminology rules (follow exactly)

| Context | Use | Avoid |
|--------|-----|--------|
| User-visible noun | project / projects | session / sessions |
| “Review session” compound | project review or project | review session |
| Coordinator role in errors | coordinator or project coordinator | session coordinator |
| Lifecycle | draft / active / closed **project** | closed session |
| Default template | project defaults | session defaults |
| Code, routes, DB | keep `session` identifiers | renaming types in this story |

**Capitalization:** Match existing Dashboard — sentence case in descriptions (“this project is closed”), title case in nav/buttons where other items use title case (“Close project”).

### File inventory (start here — grep for more)

**Coordinator / shared React**

| File | Examples to fix |
|------|-----------------|
| `src/coordinator/CoordinatorNav.jsx` | `Close session` |
| `src/coordinator/pages/CloseSession.jsx` | titles, success/error, consequence bullets |
| `src/coordinator/pages/SessionWizard.jsx` | Loading/not-found, session default panel |
| `src/coordinator/pages/SessionPlaceholder.jsx` | Session setup |
| `src/coordinator/pages/Registry.jsx` | review sessions |
| `src/coordinator/pages/AuditLog.jsx` | for this session |
| `src/coordinator/pages/Reports.jsx` | session configured |
| `src/coordinator/components/ReviewProgressSummary.jsx` | `Session:` rollup prefix |
| `src/coordinator/components/ReviewAssignmentsStep.jsx` | session defaults |
| `src/coordinator/components/ReviewRoundsStep.jsx` | when the session is |
| `src/coordinator/components/ReviewRubricBlock.jsx` | session is active |
| `src/coordinator/components/PanelReviewersStep.jsx` | this session |
| `src/coordinator/components/CsvImportMapper.jsx` | enrol students in this session |
| `src/coordinator/components/RubricsPanel.jsx` | this session, session setup |
| `src/shared/markErrors.js` | session closed / not active / coordinator |
| `src/shared/components/WizardNav.jsx` | aria-label Session setup |
| `src/reviewer/pages/MarkAssignments.jsx` | session closed, Select a session |
| `src/coordinator/components/UnfreezeRequests.jsx` | check for session |

**PHP (user-visible only)**

| File | Notes |
|------|--------|
| `includes/services/MarkService.php` | Multiple `__()` session messages |
| `includes/rest/class-rest-sessions.php` | Title required, enrolment errors |
| `includes/rest/class-rest-reviewer-assignments.php` | closed / not assigned |
| `includes/rest/class-rest-reviewers.php` | panel in this session |
| `includes/rest/class-rest-reviews.php` | Review not found in this session |
| `includes/rest/class-rest-session-close.php` | Unable to close session |
| `includes/rest/class-rest-reports.php` | Combined session scores description |
| `includes/services/ReviewerProvisionService.php` | not found in this session |
| `includes/services/ReportsViewService.php` | Session not found |
| `includes/admin/class-admin-settings.php` | Email when a session is closed |
| `includes/emails/SessionClosedEmail.php` | Subject and body |
| `includes/emails/ReviewerInviteEmail.php` | a review session fallback |

### Already correct (do not regress)

- `src/coordinator/pages/Dashboard.jsx` — “Create project”, “Loading projects…”, “No review projects yet”
- `CoordinatorNav.jsx` — section heading “Project”
- `SessionWizard.jsx` — “Draft project”, “Project roster”, “Could not open project for marking”, closed notice
- Export/report copy using `project_id` column name

### Architecture compliance

- **UX-DR20:** `markErrors.js` maps API codes to copy — update messages only; codes unchanged.
- **NFR / i18n:** PHP strings use `__('...', 'project-reviews')` — change English text inside quotes only.
- **No routing change:** Hash `#/session/:id` and REST `/sessions` remain for bookmark and API stability.

### Testing requirements

- PHPUnit: no tests currently assert exact “Session not found” strings; if any are added during work, update expectations to “Project not found”.
- No new REST routes or schema migrations.
- Run `composer test` and `npm run build` before marking done.
- Manual: create/open project, close project confirm dialog, reviewer sees “project is closed” when applicable.

### Anti-patterns (do not)

- Do **not** rename `pr_sessions` table or `session_id` columns.
- Do **not** change REST URL paths or React route paths (`/session/:id`).
- Do **not** rename error codes (`session_closed`) — front-end maps by code.
- Do **not** rename `SessionCard` / `SessionWizard` files in this story (scope creep).
- Do **not** update `_bmad-output/planning/*.md` or epics unless explicitly asked — implementation story only.

### Previous story intelligence

From **7-5** and **6-6**: Coordinator nav uses a “Project” group with session title from API; progress UI has mark-grain rollup line still labeled “Session:” — fix in `ReviewProgressSummary.jsx`. Reports and close flows still say “session” in several places.

From **3-8**: “Project roster” mental model is established; wizard Students step heading already “Project roster”.

### Project structure notes

- After copy changes, rebuild: `npm run build` (coordinator + reviewer bundles).
- Optional follow-up (out of scope): rename `CloseSession.jsx` → `CloseProject.jsx`, routes `/project/:id` — requires migration story and redirects.

## Dev Agent Record

### Agent Model Used

Auto (dev-story)

### Debug Log References

### Completion Notes List

- Swept coordinator SPA, reviewer SPA, `markErrors.js`, PHP `__()` messages, admin settings label, and notification emails: user-visible “session” → “project”.
- Left identifiers unchanged: REST `/sessions`, hash `#/session/:id`, error codes (`session_closed`, `pr_session_not_found`), DB/types, component/file names (`SessionWizard`, `SessionCard`).
- `./vendor/bin/phpunit`: 178 tests OK. `npm run build`: webpack OK.

### File List

- src/coordinator/CoordinatorNav.jsx
- src/coordinator/pages/CloseSession.jsx
- src/coordinator/pages/SessionWizard.jsx
- src/coordinator/pages/SessionPlaceholder.jsx
- src/coordinator/pages/Registry.jsx
- src/coordinator/pages/AuditLog.jsx
- src/coordinator/pages/Reports.jsx
- src/coordinator/components/ReviewProgressSummary.jsx
- src/coordinator/components/ReviewAssignmentsStep.jsx
- src/coordinator/components/ReviewRoundsStep.jsx
- src/coordinator/components/ReviewRubricBlock.jsx
- src/coordinator/components/PanelsStep.jsx
- src/coordinator/components/PanelReviewersStep.jsx
- src/coordinator/components/CsvImportMapper.jsx
- src/coordinator/components/RubricsPanel.jsx
- src/coordinator/components/ReportsScoresTable.jsx
- src/coordinator/components/ReportsMarksTable.jsx
- src/shared/components/WizardNav.jsx
- src/shared/markErrors.js
- src/reviewer/pages/MarkAssignments.jsx
- includes/services/MarkService.php
- includes/rest/class-rest-sessions.php
- includes/rest/class-rest-reviewer-assignments.php
- includes/rest/class-rest-reviewers.php
- includes/rest/class-rest-reviews.php
- includes/rest/class-rest-session-close.php
- includes/rest/class-rest-reports.php
- includes/rest/class-rest-review-assignments.php
- includes/services/ReviewerProvisionService.php
- includes/services/ReportsViewService.php
- includes/admin/class-admin-settings.php
- includes/emails/SessionClosedEmail.php
- includes/emails/ReviewerInviteEmail.php
- includes/repositories/PanelRepository.php
- build/coordinator.js
- build/coordinator-rtl.css
- build/reviewer.js
- build/reviewer-rtl.css

### Change Log

- 2026-05-17: User-facing session → project terminology across SPAs, REST messages, emails, and admin settings (story 10-1).

## References

- [Source: _bmad-output/planning/epics.md — FR3, FR26, Epic 3 “project (session)” notes]
- [Source: _bmad-output/planning/ux-design-specification.md — session wizard terminology (planning still says session; product copy overrides)]
- [Source: src/coordinator/pages/Dashboard.jsx — partial project terminology]
- [Source: src/coordinator/CoordinatorNav.jsx — “Project” section label]
- [Source: src/shared/markErrors.js — UX-DR20 error mapping]
