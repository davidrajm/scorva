---
stepsCompleted: [1, 2, 3, 4]
workflowStatus: complete
completedAt: '2026-05-16'
inputDocuments:
  - /Users/davidrm/Documents/mysites/sastt/app/public/wp-content/plugins/project-reviews/_bmad-output/planning/ux-design-specification.md
  - /Users/davidrm/Documents/mysites/sastt/app/public/wp-content/plugins/project-reviews/_bmad-output/planning/ux-design-directions.html
  - /Users/davidrm/Documents/mysites/sastt/app/public/wp-content/themes/david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md
  - /Users/davidrm/Documents/mysites/sastt/app/public/wp-content/themes/david-sas/docs/superpowers/plans/2026-05-16-project-reviews-plugin.md
---

# Project Reviews - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for Project Reviews, decomposing the requirements from the PRD, UX Design if it exists, and Architecture requirements into implementable stories.

## Requirements Inventory

### Functional Requirements

FR1: Coordinators can maintain a long-lived student registry keyed by unique registration number with core fields and coordinator-defined custom field schema.
FR2: Coordinators can import students via CSV with column mapping (remembered per import type), duplicate reg_no handling (update or skip), partial success, row-level errors, and downloadable error CSV.
FR3: Coordinators can create, edit, and list review sessions with lifecycle status draft, active, or closed.
FR4: Coordinators can enrol registry students into a session and assign each enrolled student to a panel.
FR5: Coordinators can create and manage panels per session and ensure all enrolled students are assigned before advancing setup.
FR6: Coordinators can assign reviewers to panels with email, name, and weight; optional per-review reviewer roster overrides.
FR7: The system provisions reviewer WordPress accounts by matching email or creating users with generated passwords and sends plugin-branded invite emails with login URL and credentials.
FR8: Coordinators can resend reviewer credentials on demand.
FR9: Coordinators can manually link reviewers to existing WordPress users or faculty directory rows when email is missing (when bridge available).
FR10: Coordinators can define review rounds per session with rubric criteria (label, max_marks, weight defaulting to 1).
FR11: Coordinators can confirm, unlock, and re-confirm rubrics; on re-confirm choose keep-and-flag existing marks or clear incompatible marks.
FR12: Flagged marks are visible in UI and exports after rubric changes.
FR13: Coordinators can configure review weights and reviewer weights for combined score calculation.
FR14: Reviewers can view only their assignments for active sessions where the rubric is confirmed.
FR15: Reviewers can enter draft or submitted criterion marks per assigned student with validation against max_marks.
FR16: The system blocks marking when rubric is not confirmed, session is closed, or student/review is not assigned to the reviewer.
FR17: The system computes three-level weighted scores server-side (criterion→reviewer, reviewers→review, reviews→combined) and exposes read-only totals to UI and exports.
FR18: Combined scores are never accepted from the client.
FR19: Coordinators can view marking progress with completion percentage by panel and reviewer.
FR20: Coordinators can export seven report types (student master, marks detail, review summary, combined scores, panel progress, audit log, **rubric scores flat**) as formatted Excel (.xlsx) and plain CSV.
FR20a: **Rubric scores flat** export exposes one row per project (session) × review × student (`reg_no`) × reviewer × rubric criterion with columns `project_id`, `review_id`, `reg_no`, `reviewer_id`, `rubric_id`, `score` (SQL view `pr_rubric_scores` over canonical `pr_marks`).
FR21: Excel exports apply merge plans and styling (headers, freeze panes, panel/review/reviewer grouping) matching coordinator mental model.
FR22: Coordinators can close a session, blocking new marks and disabling provisioned reviewer accounts per policy B (never disable coordinator-capable users unless explicit opt-in checkbox).
FR23: Users with override capability can change marks with a mandatory audit reason (minimum length); changes appear in audit log and exports.
FR24: Authorized users can view an append-only audit log of overrides, rubric events, session close, and account disable actions.
FR25: Administrators can configure plugin settings (email from/reply-to, base login URL, capability defaults, optional faculty bridge toggle).
FR26: Coordinators use a dedicated workspace at `/reviews/` with dashboard, session wizard (students→panels→reviewers→rubrics), registry, progress, reports, and close session flows.
FR27: Reviewers use a dedicated workspace at `/reviews/mark/` with assignments→student list→rubric form funnel.
FR28: All domain operations are available via authenticated REST API namespace `project-reviews/v1` with capability and assignment checks.
FR29: Coordinators can import panel reviewer rosters and re-enrol students via CSV with column mapping and error handling.
FR30: Optional notification emails can be sent when rubric is confirmed or session is closed (configurable in settings).

### NonFunctional Requirements

NFR1: Plugin targets PHP 8+ and WordPress 6.x.
NFR2: Product is a standalone plugin with custom database tables; it does not depend on the `david-sas` timetable theme.
NFR3: Plugin routes must not enqueue `david-sas` or theme CSS/JS; only plugin-owned assets on `/reviews/` and `/reviews/mark/`.
NFR4: All REST endpoints require authentication; mutations use `wp_rest` nonce middleware.
NFR5: Capability checks on every route; reviewer assignment scoping on mark and score visibility.
NFR6: No public student data endpoints.
NFR7: Combined scores computed only server-side; recalculated on read after mark or weight changes.
NFR8: Database schema migrated via `dbDelta` on version option bump.
NFR9: Excel generation uses PhpSpreadsheet via Composer; CSV uses the same export pipeline without styling.
NFR10: React SPAs built with `@wordpress/scripts`; coordinator navigation uses HashRouter to avoid rewrite conflicts.
NFR11: Desktop-first web application (1024px+ primary); tablet and mobile supported but not primary.
NFR12: Online-only MVP: all mutations via authenticated REST (no offline mode).
NFR13: UI meets WCAG 2.1 Level AA for contrast, keyboard navigation, focus indicators, and non-color-only status.
NFR14: Respect `prefers-reduced-motion` for progress animations.
NFR15: Session close disables only tracked provisioned reviewers; distinguishes provisioned vs linked existing users.
NFR16: PHPUnit coverage for scoring, exports (valid xlsx, merge counts), and critical domain services.
NFR17: Plugin-branded HTML email templates distinct from timetable tooling.
NFR18: Faculty directory bridge is optional filter-based integration when `david-sas` is active (Phase 2); no shared UI dependency.

### Additional Requirements

- **Starter / scaffold (Epic 1 Story 1):** Plugin bootstrap per implementation plan Task 1 — `project-reviews.php`, `class-plugin.php`, activation hooks, PHPUnit bootstrap, constants `PR_PLUGIN_VERSION`, `PR_PLUGIN_SLUG`.
- **File structure:** PSR-4 autoload, `includes/services/`, `includes/repositories/`, `includes/rest/`, `src/coordinator/`, `src/reviewer/`, `src/shared/`, `build/` compiled assets, `tests/`.
- **Table prefix:** `$wpdb->prefix . 'pr_'` for all custom tables per design spec §5.1.
- **Routes:** Rewrite rules + minimal template loader for `/reviews/` and `/reviews/mark/` without theme header/footer.
- **REST bootstrap:** Central registry registering all route classes under `project-reviews/v1`.
- **ScoreService, MarkService, ExportService, ReviewerProvisionService, SessionCloseService, AuditService, RubricLifecycleService** as domain services per implementation plan.
- **Optional Phase 2:** Per-review reviewer override UI completion, `david-sas` faculty bridge handlers, force password change on first login, optional rubric-open and session-closed emails.
- **Optional Phase 3:** PDF reports, async export, magic-link reviewers, blind marking.
- **Build pipeline:** Tailwind via PostCSS in webpack; scope utilities to `#pr-root` if needed.
- **WP Admin settings page:** Native WordPress admin markup (not Tailwind) for email and capability documentation.
- **Committed or CI-built** `build/` assets for coordinator and reviewer entry points.
- **E2E validation:** Manual checklist in `tests/e2e/MVP_CHECKLIST.md`.

### UX Design Requirements

UX-DR1: Implement design tokens in `assets/css/app-shell.css` (`:root` variables for primary, surfaces, text, border, success, warning, danger, info, radius, shadow, font stack).
UX-DR2: Configure Tailwind in plugin webpack pipeline with theme extension mapping to CSS variables; scope to `#pr-root` to prevent bleed.
UX-DR3: Adopt **Direction 1 — Structured Academic** from `ux-design-directions.html` for both coordinator and reviewer apps.
UX-DR4: App shell layout — fixed top bar (56px), coordinator left sidebar (240px), max content width 1280px centered, wordmark “Project Reviews” in header.
UX-DR5: Reviewer app shell — top bar only, no sidebar; centered content column max ~640px on rubric form.
UX-DR6: Implement shared component `AppShell` with coordinator vs reviewer states, `nav` landmark, skip link to main content.
UX-DR7: Implement `SessionCard` on dashboard with title, status chip, progress bar, click-through to session (variants draft/active/closed).
UX-DR8: Implement `StatusChip` with fixed variants: draft, active, closed, confirmed, unlocked, flagged — token-mapped color pairs; never color-only (text + icon).
UX-DR9: Implement `WizardNav` horizontal steps (Students → Panels → Reviewers → Rubrics) with current, complete, blocked states and tooltip reasons; cannot skip blocked steps.
UX-DR10: Implement `RubricTable` for criteria editing with editable/read-only states, Save, Confirm, Unlock actions, flagged indicator on affected marks.
UX-DR11: Implement `ReportCard` for each of six report types with side-by-side Download Excel and Download CSV; loading and error states.
UX-DR12: Implement `ProgressTable` with panel × reviewer rows, completion counts, percent, and progress bar; sticky table header.
UX-DR13: Implement `ConfirmDialog` for consequential actions with consequence bullet list, focus trap, `role="dialog"`, `aria-modal="true"`; variants for close session (coordinator-disable checkbox) and re-confirm rubric (keep_flag vs clear).
UX-DR14: Implement `CsvImportMapper` with column dropdowns, preview first 3 rows, persist mapping to localStorage per import type, import summary display.
UX-DR15: Implement reviewer `RubricForm` with criterion inputs, max validation, Save draft and Submit, draft vs submitted visual states.
UX-DR16: Implement `ScoreBreakdown` read-only display of reviewer totals, review scores, and combined — no editable aggregate fields.
UX-DR17: Implement `EmptyState` with headline, one sentence, primary CTA for no sessions, assignments, or audit rows.
UX-DR18: Implement shared `PageHeader`, `Button`, `ProgressBar`, `DataTable` per design system foundation; barrel export from `src/shared/components/`.
UX-DR19: Button hierarchy — one primary action per screen; destructive actions only inside ConfirmDialog; never two primary buttons adjacent.
UX-DR20: Feedback patterns — map API codes `rubric_not_confirmed`, `session_closed`, `not_assigned` to fixed user-facing strings; use `@wordpress/components` Notice and Spinner selectively.
UX-DR21: Form patterns — labels above inputs, `aria-required`, inline validation on blur/submit, rubric numeric `inputmode="decimal"`, override reason textarea min 10 characters.
UX-DR22: Coordinator HashRouter routes: `#/`, `#/session/:id/wizard`, `#/session/:id/progress`, `#/registry`; breadcrumb in page title.
UX-DR23: Reviewer navigation — back link from rubric form → student list → assignments; no sidebar.
UX-DR24: Typography scale per UX spec (page title 32px/600 through caption 12px/500); tabular-nums on marks, scores, progress %.
UX-DR25: Spacing scale 4px base (4, 8, 12, 16, 24, 32, 48); card padding 24px, radius 8px, subtle shadow.
UX-DR26: Responsive breakpoints — desktop-first; sidebar collapses to hamburger at max-md (768px); session cards 1–3 columns by breakpoint.
UX-DR27: Accessibility — WCAG 2.1 AA contrast, focus rings on interactive elements, `aria-describedby` for errors, `aria-live="polite"` for save/submit, semantic table headers with `scope`, sidebar toggle `aria-expanded`.
UX-DR28: Blocked-state UX — full-page or inline banner explaining who can fix (coordinator vs admin) when rubric not confirmed or session closed.
UX-DR29: Registry search debounced 300ms; session list filter by status chips.
UX-DR30: Flagged marks — warning chip + tooltip “Rubric changed after marking”.
UX-DR31: Download buttons disabled during fetch; labels include format (“Download Excel”, “Download CSV”).
UX-DR32: Coordinator session wizard gates — block advance on unassigned students and unconfirmed rubrics with visible blockers.
UX-DR33: Emotional/copy tone — direct, neutral, academic; no gamification; consequence summaries on destructive flows.
UX-DR34: WP Admin settings use native WP admin patterns separate from SPA Tailwind styling.

### FR Coverage Map

FR1: Epic 2 - Student registry CRUD and custom fields
FR2: Epic 2 - CSV student import
FR3: Epic 3 - Session CRUD and lifecycle
FR4: Epic 3 - Session enrolment and panel assignment
FR5: Epic 3 - Panel management and assignment validation
FR6: Epic 3 - Panel reviewer roster and overrides
FR7: Epic 3 - Reviewer provisioning and invite email
FR8: Epic 3 - Resend credentials
FR9: Epic 3 - Manual reviewer linking (faculty/WP user)
FR10: Epic 4 - Rubric criteria definition
FR11: Epic 4 - Confirm, unlock, re-confirm rubric
FR12: Epic 4 - Flagged marks visibility
FR13: Epic 4 - Weight configuration
FR14: Epic 5 - Reviewer assignment list
FR15: Epic 5 - Draft and submitted marking
FR16: Epic 5 - Marking guards and blocked states
FR17: Epic 6 - Server-side three-level scoring
FR18: Epic 6 - No client combined scores
FR19: Epic 6 - Progress dashboard
FR20: Epic 7 - Six report types Excel + CSV
FR21: Epic 7 - Excel merge and styling
FR22: Epic 8 - Session close and account disable policy
FR23: Epic 9 - Mark override with audit reason
FR24: Epic 9 - Audit log viewing
FR25: Epic 9 - Plugin settings and capability defaults
FR26: Epic 1 + Epic 3-8 - Coordinator SPA and flows
FR27: Epic 1 + Epic 5 - Reviewer SPA and funnel
FR28: Epic 1 + all - REST API foundation
FR29: Epic 3 - CSV panel reviewer and re-enrol imports
FR30: Epic 9 - Optional notification emails

## Epic List

### Epic 1: Access a standalone Project Reviews workspace
Coordinators, reviewers, and administrators can sign in and reach dedicated plugin routes with distinct branding, capabilities, and API access—without timetable theme chrome.
**FRs covered:** FR26, FR27, FR28 (foundation)
**NFRs addressed:** NFR1–NFR6, NFR10, NFR17
**UX-DRs addressed:** UX-DR1–UX-DR6, UX-DR18, UX-DR34

### Epic 2: Maintain the student registry
Coordinators can build and import a long-lived student registry with custom fields, ready for session enrolment.
**FRs covered:** FR1, FR2
**UX-DRs addressed:** UX-DR14, UX-DR17, UX-DR29

### Epic 3: Configure review sessions end-to-end
Coordinators can create sessions, enrol students, organize panels, assign and provision reviewers, and complete the session wizard through rubric setup prerequisites.
**FRs covered:** FR3, FR4, FR5, FR6, FR7, FR8, FR9, FR29
**UX-DRs addressed:** UX-DR7, UX-DR9, UX-DR14, UX-DR32

### Epic 4: Govern rubrics and open marking
Coordinators can define review rounds, configure weights, and confirm/unlock/re-confirm rubrics so marking opens safely with visible flagged state.
**FRs covered:** FR10, FR11, FR12, FR13
**UX-DRs addressed:** UX-DR8, UX-DR10, UX-DR13, UX-DR30

### Epic 5: Reviewers mark assigned students
Reviewers can complete the assignments→list→rubric funnel with draft/submit marks, validation, and clear blocked-state messaging.
**FRs covered:** FR14, FR15, FR16
**UX-DRs addressed:** UX-DR5, UX-DR15, UX-DR21, UX-DR23, UX-DR28

### Epic 6: Monitor progress and computed scores
Coordinators can see accurate completion by panel/reviewer and read-only score breakdowns aligned with server rules.
**FRs covered:** FR17, FR18, FR19
**UX-DRs addressed:** UX-DR12, UX-DR16, UX-DR24

### Epic 7: Export committee-ready reports
Coordinators can download all six report types as formatted Excel and plain CSV with grouping that matches progress and marks views.
**FRs covered:** FR20, FR21
**UX-DRs addressed:** UX-DR11, UX-DR31

### Epic 8: Close sessions safely
Coordinators can end marking and disable provisioned reviewer accounts without accidental coordinator lockout.
**FRs covered:** FR22
**UX-DRs addressed:** UX-DR13 (close variant), UX-DR33

### Epic 9: Administer governance and system settings
Administrators and authorized users can override marks with audit trail, review audit history, and configure plugin email and capabilities.
**FRs covered:** FR23, FR24, FR25, FR30
**UX-DRs addressed:** UX-DR20, UX-DR21, UX-DR34

---

## Epic 1: Access a standalone Project Reviews workspace

Coordinators, reviewers, and administrators can sign in and reach dedicated plugin routes with distinct branding, capabilities, and API access—without timetable theme chrome.

### Story 1.1: Plugin scaffold and activation hooks

As a **developer**,
I want the plugin bootstrap, autoloading, and activation hooks in place,
So that all subsequent features build on a testable WordPress plugin foundation.

**Acceptance Criteria:**

**Given** the plugin directory is present under `wp-content/plugins/project-reviews/`
**When** the plugin is activated in WordPress
**Then** constants `PR_PLUGIN_VERSION`, `PR_PLUGIN_SLUG`, `PR_PLUGIN_DIR`, and `PR_PLUGIN_FILE` are defined
**And** `Plugin::instance()` registers on `init`
**And** PHPUnit test `PluginBootstrapTest` passes without a full WordPress install (stub load)
**And** `composer.json` defines PSR-4 autoload for `ProjectReviews\` namespace

*Covers: Additional scaffold requirement; FR28 foundation; NFR1*

### Story 1.2: Capabilities and default role mapping

As an **administrator**,
I want Project Reviews capabilities registered with sensible defaults on activation,
So that coordinators and reviewers receive correct permissions without manual code edits.

**Acceptance Criteria:**

**Given** the plugin is activated
**When** capabilities are inspected via `get_role()` or capability tests
**Then** all `pr_*` capabilities from design spec §7 exist (`pr_manage_sessions`, `pr_upload_students`, `pr_manage_panels`, `pr_assign_reviewers`, `pr_configure_weights`, `pr_confirm_rubrics`, `pr_enter_marks`, `pr_override_marks`, `pr_view_reports`, `pr_close_session`, `pr_manage_settings`)
**And** administrator role receives all capabilities
**And** default coordinator bundle includes all except `pr_override_marks` (configurable later in settings)
**And** reviewer role receives only `pr_enter_marks`
**And** `CapabilitiesTest` passes

*Covers: NFR5; FR28*

### Story 1.3: REST auth helpers and API bootstrap

As a **client application**,
I want authenticated REST endpoints with shared permission helpers,
So that every future route enforces login, nonce, and capability checks consistently.

**Acceptance Criteria:**

**Given** a logged-out user
**When** they call any `project-reviews/v1` route
**Then** the response is 401 or 403 as appropriate
**Given** a logged-in user without the required capability
**When** they call a protected route
**Then** the response is 403 with a clear error code
**Given** a logged-in user with `wp_rest` nonce
**When** they call `GET /project-reviews/v1/health` (or bootstrap health route)
**Then** the response is 200 with plugin version
**And** `RestAuthTest` covers nonce and capability rejection paths

*Covers: FR28; NFR4, NFR5, NFR6*

### Story 1.4: Front-end routes and minimal PHP app shell

As a **coordinator or reviewer**,
I want dedicated URLs that load a minimal plugin shell without theme header/footer,
So that the experience feels like a standalone product.

**Acceptance Criteria:**

**Given** rewrite rules are flushed after activation
**When** an authenticated user visits `/reviews/`
**Then** a minimal template renders `#pr-root` without `david-sas` theme header/footer
**When** an authenticated user visits `/reviews/mark/`
**Then** the reviewer shell loads similarly
**And** unauthenticated users are redirected to WordPress login
**And** no theme CSS/JS handles are enqueued on these routes (NFR3)
**And** `templates/app-shell.php` is used for both routes

*Covers: FR26, FR27; NFR2, NFR3*

### Story 1.5: Design tokens and shared UI primitives

As a **user**,
I want consistent Project Reviews branding and reusable UI primitives,
So that coordinator and reviewer apps share a calm, academic visual language.

**Acceptance Criteria:**

**Given** `assets/css/app-shell.css` is enqueued on plugin routes
**When** the page loads
**Then** CSS variables match UX spec (primary `#1e4d6b`, surfaces, text, status colors, radius, shadow, font stack)
**And** Tailwind is configured in the webpack pipeline with theme extension mapping to CSS variables scoped under `#pr-root`
**And** shared components exist: `Button`, `PageHeader`, `StatusChip`, `EmptyState`, `Card` (or `SessionCard` stub)
**And** Direction 1 layout tokens apply (top bar 56px, sidebar 240px coordinator-only)
**And** skip link “Skip to main content” is present in AppShell markup

*Covers: UX-DR1, UX-DR2, UX-DR3, UX-DR4, UX-DR8, UX-DR17, UX-DR18, UX-DR24, UX-DR25, UX-DR27*

### Story 1.6: React SPAs, AppShell, and shared API client

As a **coordinator or reviewer**,
I want React apps mounted on plugin routes with HashRouter and a shared API client,
So that I can navigate the product and call REST endpoints securely.

**Acceptance Criteria:**

**Given** build assets for `coordinator` and `reviewer` are enqueued on the correct routes
**When** the SPA loads
**Then** `AppShell` renders coordinator variant (sidebar) on `/reviews/` and reviewer variant (no sidebar) on `/reviews/mark/`
**And** coordinator app uses HashRouter with routes `#/` and placeholder session routes
**And** `src/shared/api.js` attaches `wp_rest` nonce to `apiFetch` mutations
**And** coordinator dashboard shows EmptyState when no sessions exist
**And** no imports reference `david-sas` theme paths

*Covers: FR26, FR27, FR28; NFR10; UX-DR6, UX-DR22*

---

## Epic 2: Maintain the student registry

Coordinators can build and import a long-lived student registry with custom fields, ready for session enrolment.

### Story 2.1: Student registry database tables

As a **developer**,
I want student registry tables created on plugin install/upgrade,
So that coordinators can persist students and custom field definitions.

**Acceptance Criteria:**

**Given** plugin activation or `pr_db_version` bump
**When** migrations run via `dbDelta`
**Then** tables `pr_students`, `pr_field_definitions`, and `pr_student_meta` exist with `reg_no` unique constraint
**And** migration is idempotent on re-activation
**And** `StudentRepositoryTest` can insert and fetch a student fixture

*Covers: FR1; NFR8*

### Story 2.2: Student registry REST CRUD and field schema

As a **coordinator**,
I want REST endpoints to manage students and custom field definitions,
So that the registry can be maintained programmatically and by the UI.

**Acceptance Criteria:**

**Given** a user with `pr_upload_students`
**When** they `POST`, `GET`, `PUT`, `DELETE` students via `/project-reviews/v1/students`
**Then** operations succeed with validation on required `reg_no` and `name`
**When** they manage field definitions via the schema endpoint
**Then** custom fields can be added and values stored per student
**And** users without capability receive 403
**And** duplicate `reg_no` returns a clear error

*Covers: FR1; FR28*

### Story 2.3: Registry UI with search and empty state

As a **coordinator**,
I want a registry screen to browse, search, and edit students,
So that I can maintain records before enrolling them in sessions.

**Acceptance Criteria:**

**Given** the coordinator navigates to `#/registry`
**When** students exist
**Then** a searchable table lists students with core and custom columns
**And** search is debounced 300ms (UX-DR29)
**When** no students exist
**Then** EmptyState displays with CTA to import or add first student
**And** create/edit form uses labels above inputs with `aria-required` on required fields

*Covers: FR1, FR26; UX-DR17, UX-DR21, UX-DR29*

### Story 2.4: CSV student import with column mapper

As a **coordinator**,
I want to import students from CSV with remembered column mapping and error reporting,
So that bulk registry setup is fast and recoverable.

**Acceptance Criteria:**

**Given** a CSV with at least `reg_no` and `name` columns
**When** the coordinator maps columns via `CsvImportMapper`, previews first 3 rows, and submits
**Then** valid rows are imported; mapping is saved to `localStorage` per import type
**When** duplicate `reg_no` rows exist
**Then** the user chooses update or skip before import proceeds
**When** some rows fail validation
**Then** the response includes row-level errors and a downloadable error CSV
**And** a success Notice summarizes imported vs failed counts

*Covers: FR2; UX-DR14, UX-DR20*

---

## Epic 3: Configure review sessions end-to-end

Coordinators can create sessions, enrol students, organize panels, assign and provision reviewers, and complete the session wizard through rubric setup prerequisites.

### Story 3.1: Session, panel, and enrolment database tables

As a **developer**,
I want session-related tables for enrolment, panels, and reviewer links,
So that session configuration can be persisted.

**Acceptance Criteria:**

**Given** migration runs after registry tables exist
**When** `dbDelta` completes
**Then** tables exist: `pr_sessions`, `pr_session_students`, `pr_panels`, `pr_panel_reviewers`, `pr_session_reviewers` (and override table if in schema)
**And** session `status` supports `draft`, `active`, `closed`
**And** `SessionRepositoryTest` covers create session and enrol student

*Covers: FR3, FR4, FR5; NFR8*

### Story 3.2: Sessions REST and dashboard with session cards

As a **coordinator**,
I want to create, list, and open review sessions from a card-based dashboard,
So that I can manage multiple events in one place.

**Acceptance Criteria:**

**Given** a user with `pr_manage_sessions`
**When** they create a session via REST
**Then** it is stored with status `draft` by default
**When** they open `/reviews/` dashboard
**Then** each session renders as `SessionCard` with title, StatusChip, and progress placeholder
**And** clicking a card navigates into session context
**And** status filter chips filter the dashboard list (UX-DR29)

*Covers: FR3, FR26; UX-DR7, UX-DR8, UX-DR22*

### Story 3.3: Wizard step Students — enrolment and CSV re-enrol

As a **coordinator**,
I want wizard step 1 to enrol registry students and import re-enrol CSV,
So that the session has the correct student roster with panel assignments.

**Acceptance Criteria:**

**Given** a draft session and `WizardNav` on step Students
**When** the coordinator searches registry and adds students to the session
**Then** students appear in the enrolment list
**When** they upload a re-enrol CSV (`reg_no`, `panel`)
**Then** `CsvImportMapper` handles mapping and updates session enrolment
**And** wizard cannot advance to Panels until at least one student is enrolled (visible blocker)

*Covers: FR4, FR29; UX-DR9, UX-DR14, UX-DR32*

### Story 3.4: Wizard step Panels — assign all enrolled students

As a **coordinator**,
I want to create panels and assign every enrolled student,
So that reviewers can be scoped to panel groups.

**Acceptance Criteria:**

**Given** enrolled students in the session
**When** the coordinator creates panels and assigns students
**Then** each student has exactly one `panel_id`
**When** any student is unassigned
**Then** WizardNav blocks advance to Reviewers with tooltip listing unassigned count
**And** unassigned students are highlighted in the step UI

*Covers: FR5; UX-DR9, UX-DR32*

### Story 3.5: Wizard step Reviewers — roster, CSV import, and provisioning

As a **coordinator**,
I want to assign reviewers by panel, import rosters, and provision WordPress accounts,
So that reviewers can log in and receive credentials.

**Acceptance Criteria:**

**Given** panels exist with assigned students
**When** the coordinator adds reviewers with email, name, and weight per panel
**Then** roster rows persist via REST
**When** they import panel reviewer CSV (`panel`, `reviewer_name`, `email`, optional `weight`)
**Then** import uses `CsvImportMapper` with row-level error handling
**When** provisioning runs for a new email
**Then** existing WP user is matched or new user is created with generated password
**And** plugin-branded invite email is sent with login URL and credentials (FR7, NFR17)
**And** a success toast confirms credentials sent

*Covers: FR6, FR7, FR29; UX-DR14, UX-DR20*

### Story 3.6: Resend credentials and manual reviewer linking

As a **coordinator**,
I want to resend invites and link reviewers to existing users when email is missing,
So that roster gaps can be resolved without re-importing.

**Acceptance Criteria:**

**Given** a provisioned reviewer on a session roster
**When** the coordinator clicks Resend credentials
**Then** a new invite email is sent and action is logged
**Given** a reviewer row without email
**When** the coordinator picks an existing WP user (or faculty row when bridge active)
**Then** the roster links `user_id` without creating a duplicate account
**And** `pr_session_reviewers` distinguishes provisioned vs linked users

*Covers: FR8, FR9; NFR18 deferred if bridge not active — manual WP user pick still works*

### Story 3.7: Wizard step Reviews — create review rounds before roster setup

As a **coordinator**,
I want to define Review 1, Review 2, … immediately after creating a session,
So that the wizard follows project → review rounds → students/panels before reviewers and rubric criteria.

**Acceptance Criteria:**

**Given** a draft session in the session wizard
**When** the coordinator opens the **Reviews** step (first step)
**Then** they can add, rename, and remove review rounds via existing review REST
**And** at least one review round must exist before advancing to Students
**When** they complete Students and Panels
**Then** enrolment and panel assignment remain session-scoped and apply to all review rounds in the session
**And** wizard order is Reviews → Students → Panels → Reviewers → Rubrics

*Covers: FR10 (review round definition), FR26; UX-DR9, UX-DR32 — corrects gap where review creation only appeared on Rubrics step*

### Story 3.8: Project default student roster

As a **coordinator**,
I want each project (session) to have an attached student list used by default for every review round,
So that the marking cohort is defined once per project.

**Acceptance Criteria:**

**Given** a coordinator creates a project
**When** they optionally attach registry students at creation or on the wizard Students step
**Then** enrolments persist in `pr_session_students` and apply to all review rounds in that session
**When** they view the dashboard
**Then** each session card shows enrolled student count
**And** marking and progress only include enrolled students (existing guards)

*Covers: FR4, FR26; UX-DR9 — extends session enrolment with project-roster mental model at create/list*

---

## Epic 4: Govern rubrics and open marking

Coordinators can define review rounds, configure weights, and confirm/unlock/re-confirm rubrics so marking opens safely with visible flagged state.

### Story 4.1: Reviews, rubrics, and lifecycle REST

As a **coordinator**,
I want REST endpoints for review rounds, criteria, and rubric lifecycle actions,
So that rubric state is enforced server-side before marking opens.

**Acceptance Criteria:**

**Given** tables `pr_reviews`, `pr_rubric_criteria`, `pr_review_weights`, `pr_reviewer_weights` exist after migration
**When** coordinator creates Review 1 with criteria (label, max_marks, weight default 1)
**Then** criteria persist in draft review status
**When** coordinator calls confirm
**Then** review status becomes `confirmed` and marking is allowed for that review
**When** coordinator unlocks and re-confirms
**Then** `RubricLifecycleService` supports `keep_flag` and `clear` paths per design spec §5.3
**And** `RubricLifecycleServiceTest` covers confirm/unlock/re-confirm

*Covers: FR10, FR11; FR28*

### Story 4.2: Rubric builder UI with RubricTable

As a **coordinator**,
I want to edit rubric criteria in the session wizard Rubrics step,
So that review rounds are defined before confirmation.

**Acceptance Criteria:**

**Given** wizard step Rubrics with `RubricTable`
**When** review is draft or unlocked
**Then** criteria rows are editable (label, max_marks, weight) with `inputmode="decimal"`
**When** review is confirmed
**Then** table is read-only with Confirmed StatusChip
**And** Save persists criteria via REST
**And** one primary Confirm action is visible per screen (UX-DR19)

*Covers: FR10, FR26; UX-DR10, UX-DR21*

### Story 4.3: Confirm, unlock, and re-confirm with consequence dialog

As a **coordinator**,
I want explicit confirm dialogs for rubric lifecycle actions,
So that I understand consequences before marking is opened, paused, or reset.

**Acceptance Criteria:**

**Given** a draft rubric with valid criteria
**When** the coordinator clicks Confirm
**Then** rubric status shows `confirmed` chip and reviewers can mark (when session active)
**When** the coordinator clicks Unlock
**Then** `ConfirmDialog` explains marking is paused
**When** they re-confirm after edits
**Then** dialog offers **Keep and flag** (default) vs **Clear marks** with bullet consequences
**And** dialog traps focus, uses `role="dialog"` and `aria-modal="true"`

*Covers: FR11; UX-DR13, UX-DR33*

### Story 4.4: Review and reviewer weight configuration

As a **coordinator**,
I want to configure review weights and reviewer weights within a session,
So that combined scores reflect our weighting policy.

**Acceptance Criteria:**

**Given** a user with `pr_configure_weights`
**When** they update review weights and per-reviewer weights via REST/UI
**Then** values persist and default to 1 when unset
**When** weights change after marks exist
**Then** UI shows optional warning Notice (amber)
**And** scores recalculate on next read via ScoreService (no client totals)

*Covers: FR13; FR17 dependency for recalculation*

### Story 4.5: Flagged marks visibility

As a **coordinator or reviewer**,
I want flagged marks clearly indicated after rubric changes,
So that everyone knows which scores may need review.

**Acceptance Criteria:**

**Given** marks were kept and flagged on rubric re-confirm
**When** viewing marks in coordinator or reviewer UI
**Then** flagged rows show warning StatusChip and tooltip “Rubric changed after marking”
**And** flagged state appears in marks-related exports (when export epic complete)

*Covers: FR12; UX-DR30*

---

## Epic 5: Reviewers mark assigned students

Reviewers can complete the assignments→list→rubric funnel with draft/submit marks, validation, and clear blocked-state messaging.

### Story 5.1: Marks persistence and MarkService guards

As a **developer**,
I want marks stored with server-side guards for rubric and session state,
So that invalid marking cannot be persisted.

**Acceptance Criteria:**

**Given** table `pr_marks` exists
**When** `MarkService` receives a mark POST
**Then** it rejects if rubric is not `confirmed`, session is `closed`, or reviewer is not assigned
**And** criterion values are validated against `max_marks`
**And** `MarkServiceTest` covers guard cases and draft vs submitted status

*Covers: FR15, FR16; NFR7*

### Story 5.2: Marks REST with assignment scoping

As a **reviewer**,
I want REST endpoints to read and write marks only for my assignments,
So that I cannot access other panels’ students.

**Acceptance Criteria:**

**Given** a reviewer with `pr_enter_marks` assigned to Panel A, Review 1
**When** they GET marks for an assigned student
**Then** data is returned
**When** they POST marks for an unassigned student or review
**Then** API returns `not_assigned` error code
**And** coordinators with appropriate caps can read broader mark sets

*Covers: FR14, FR15, FR16, FR28; NFR5*

### Story 5.3: Reviewer assignments and student list UI

As a **reviewer**,
I want to see my assignments and pick a student to mark,
So that I know where to work without seeing other panels.

**Acceptance Criteria:**

**Given** the reviewer opens `/reviews/mark/`
**When** active sessions have confirmed rubrics for their assignments
**Then** assignment cards show session name, review round, and panel
**When** they select an assignment
**Then** student list shows only assigned students with draft/submitted indicators
**And** back navigation returns to assignments (UX-DR23)
**When** rubric is not confirmed or session inactive
**Then** assignment is hidden or disabled with reason banner (UX-DR28)

*Covers: FR14, FR27; UX-DR5, UX-DR17, UX-DR23, UX-DR28*

### Story 5.4: RubricForm with draft save and submit

As a **reviewer**,
I want to enter criterion scores and save draft or submit,
So that I can complete marking at my own pace.

**Acceptance Criteria:**

**Given** a selected student and confirmed rubric
**When** the reviewer enters scores in `RubricForm`
**Then** each field shows “Score (0–{max})” and validates on blur/submit
**When** they click Save draft
**Then** marks persist with draft status and `aria-live` announces success
**When** they click Submit
**Then** marks persist with submitted status and row appears distinct in student list
**And** form layout is max-width ~640px centered (UX-DR5)

*Covers: FR15; UX-DR15, UX-DR21, UX-DR27*

### Story 5.5: Blocked-state messaging and API error mapping

As a **reviewer**,
I want clear messages when marking is blocked,
So that I know whether to contact a coordinator or admin.

**Acceptance Criteria:**

**Given** API returns `rubric_not_confirmed`, `session_closed`, or `not_assigned`
**When** the reviewer attempts to save marks
**Then** UI shows fixed user-facing strings (not raw codes) per UX-DR20
**And** banner states who can fix the issue (coordinator vs admin)
**And** `@wordpress/components` Notice is used for errors

*Covers: FR16; UX-DR20, UX-DR28, UX-DR33*

---

## Epic 6: Monitor progress and computed scores

Coordinators can see accurate completion by panel/reviewer and read-only score breakdowns aligned with server rules.

### Story 6.1: Three-level ScoreService

As a **coordinator**,
I want combined scores computed server-side with three weighted levels,
So that totals are trustworthy and consistent across UI and exports.

**Acceptance Criteria:**

**Given** criterion marks and weights for a student
**When** `ScoreService` calculates totals
**Then** Level 1 reviewer total, Level 2 review score, and Level 3 combined score match design spec formulas
**And** weights default to 1 when unset
**And** `ScoreServiceTest` includes fixture scenarios for all three levels
**And** combined scores are never read from request body (FR18)

*Covers: FR17, FR18; NFR7, NFR16*

### Story 6.2: Scores REST (read-only)

As a **coordinator or reviewer**,
I want to fetch computed scores via REST,
So that UI displays breakdowns without client-side math.

**Acceptance Criteria:**

**Given** marks exist for a student/review
**When** client calls `GET` scores endpoint
**Then** response includes reviewer totals, review scores, and combined score as read-only numbers
**When** client sends combined score in POST body
**Then** server ignores it and does not persist client aggregates

*Covers: FR17, FR18, FR28*

### Story 6.3: Progress REST and ProgressTable UI

As a **coordinator**,
I want a progress page showing completion by panel and reviewer,
So that I can chase incomplete marking before close.

**Acceptance Criteria:**

**Given** an active session with assignments and marks
**When** the coordinator opens `#/session/:id/progress`
**Then** `ProgressTable` lists panel, reviewer, completed/total, percent, and ProgressBar
**And** table header is sticky; numbers use tabular-nums
**And** `completed` / `total` count **submitted rubric scores** at grain **session × review × rubric criterion × reviewer × student** (each expected cell is one criterion mark for one student in one review round from one panel reviewer)
**And** draft-only marks do not increment `completed`
**And** percentages match server calculation for fixture data in tests

*Covers: FR19; UX-DR12, UX-DR24, UX-DR14 (reduced-motion)*

### Story 6.4: ScoreBreakdown read-only component

As a **coordinator**,
I want to view per-student score breakdowns without editing totals,
So that I can explain combined scores to committees.

**Acceptance Criteria:**

**Given** a student with marks across reviews
**When** the coordinator views mark detail / score breakdown
**Then** `ScoreBreakdown` shows reviewer totals, per-review scores, and combined score
**And** no input fields allow editing aggregate totals
**And** copy uses academic neutral tone

*Covers: FR17, FR18; UX-DR16*

### Story 6.5: Progress — student grain, per-review tables, overall radial summary

As a **coordinator**,
I want marking progress counted by **students fully marked** with per-review tables and an overall radial summary per review round,
So that I can see which review rounds and reviewers still have students to finish.

**Acceptance Criteria:**

**Given** panel reviewers and enrolled students on a confirmed review
**When** progress is calculated per panel × reviewer
**Then** `completed` / `total` count **students** (not rubric criterion marks) using the same completion rules as freeze (including absent attendance)
**When** the coordinator opens `#/session/:id/progress`
**Then** an overall section shows a radial/donut per review with student completion %
**And** each confirmed review has its own `ProgressTable` subsection
**And** server tests and panel progress export use the same student grain

*Covers: FR19; UX-DR12, UX-DR24, NFR14 — supersedes rubric-score counting in Story 6.3 for coordinator progress*

### Story 6.6: Progress — review-mark summary %, accordions, expand controls

As a **coordinator**,
I want **overall/review/panel completion % based on review marks** (reviewer × student obligations) and **collapsible review/panel sections** with expand/collapse toolbar,
So that Overall progress % reflects partial marking and the page stays scannable.

**Acceptance Criteria:**

**Given** linked panel reviewers and enrolled students on a confirmed review
**When** review/panel `summary.percent` is calculated
**Then** `marks_total` = Σ panels (students × **all assigned** panel reviewers, linked or not); unlinked obligations count as not started
**And** `summary` exposes `marks_completed`, `marks_in_progress`, `marks_not_started`; `percent` = completed / total
**And** student counts on `summary` remain; panel × reviewer table rows stay student grain
**When** the coordinator opens progress
**Then** Overall section shows session rollup + per-review complete/in progress/not started; radials use mark grain; accordions default closed; toolbar expand/collapse

*Covers: FR19; UX-DR12, UX-DR24, NFR13, NFR14 — extends Story 6.5 UI; see `_bmad-output/implementation/6-6-progress-accordion-mark-grain-summary.md`*

---

## Epic 7: Export committee-ready reports

Coordinators can download all six report types as formatted Excel and plain CSV with grouping that matches progress and marks views.

### Story 7.1: ExportService CSV and XLSX foundation

As a **developer**,
I want a shared export pipeline for CSV and styled Excel,
So that all reports use one tested code path.

**Acceptance Criteria:**

**Given** PhpSpreadsheet is installed via Composer
**When** `ExportService::to_csv($rows)` is called
**Then** valid CSV is returned
**When** `ExportService::to_xlsx($rows, $merge_plan, $styles)` is called
**Then** valid `.xlsx` is returned with bold header, freeze pane, and numeric format for marks
**And** `ExportServiceTest` asserts xlsx validity and expected merge cell counts on fixtures

*Covers: FR20, FR21; NFR9, NFR16*

### Story 7.4: Flat rubric scores schema (`pr_rubric_scores` view)

As a **developer**,
I want a SQL view at the rubric-score grain with stable ID columns,
So that exports and integrations can query project × review × reg_no × reviewer × rubric × score without ad hoc joins.

**Acceptance Criteria:**

**Given** criterion marks in `pr_marks` and students in `pr_students`
**When** schema patches run
**Then** view `{prefix}pr_rubric_scores` exists with `project_id`, `review_id`, `reg_no`, `reviewer_id`, `rubric_id`, `score`
**And** row count per session matches joined `pr_marks` for that session
**And** validation SQL in `tests/sql/validate_rubric_scores.sql` documents parity checks
**And** PHPUnit covers DDL and patch idempotency

*Covers: FR20a*

### Story 7.2: Report query layer for report types (incl. rubric scores flat)

As a **coordinator**,
I want normalized data queries for each report type,
So that exports reflect session truth including per-rubric reviewer scores with IDs.

**Acceptance Criteria:**

**Given** a session with enrolment, marks, scores, and audit rows
**When** each legacy report query runs (student master, marks detail, review summary, combined scores, panel progress, audit log)
**Then** row structures match design spec §11.2 layouts
**And** merge plans are defined for Excel panel/review/reviewer grouping (FR21)
**When** `TYPE_RUBRIC_SCORES` runs
**Then** rows use ID columns from `pr_rubric_scores` (project, review, reg_no, reviewer, rubric, score)

*Covers: FR20, FR20a, FR21*

### Story 7.3: Report download REST and ReportCard gallery

As a **coordinator**,
I want a reports page with report cards and paired download buttons,
So that I can export committee deliverables without a separate format picker.

**Acceptance Criteria:**

**Given** a user with `pr_view_reports` on session reports route
**When** they view the reports page
**Then** seven `ReportCard` components display (six legacy + rubric scores flat) with descriptions
**And** each card has side-by-side **Download Excel** and **Download CSV** buttons (UX-DR31)
**When** a download is in progress
**Then** buttons disable until complete
**When** download completes
**Then** browser receives correct `Content-Type` and filename
**And** Excel output matches merge semantics for student master and marks detail on fixture session

*Covers: FR20, FR21, FR26; UX-DR11, UX-DR31*

---

## Epic 8: Close sessions safely

Coordinators can end marking and disable provisioned reviewer accounts without accidental coordinator lockout.

### Story 8.1: SessionCloseService and close REST

As a **coordinator**,
I want to close a session with policy B account handling,
So that marking stops and only intended reviewer accounts are disabled.

**Acceptance Criteria:**

**Given** an active session
**When** `SessionCloseService::close()` runs
**Then** session status becomes `closed` and new marks are rejected
**And** provisioned reviewers (`provisioned_for_session = true`) are disabled
**And** users with `pr_manage_sessions` are NOT disabled unless explicit `also_disable_coordinators` flag is true
**And** audit rows record `session_closed` and disable actions
**And** `SessionCloseServiceTest` covers policy B edge cases (NFR15)

*Covers: FR22; NFR15*

### Story 8.2: Close session UI with consequence dialog

As a **coordinator**,
I want a close session screen with summary and explicit confirmation,
So that I do not accidentally lock out coordinators or leave marking open.

**Acceptance Criteria:**

**Given** the coordinator opens Close session for an active session
**When** the screen loads
**Then** summary shows session status, open marks count, and provisioned users affected
**And** checkbox “Also disable coordinator-capable users” is unchecked by default with warning when checked
**When** they confirm via `ConfirmDialog`
**Then** session closes and success Notice appears
**And** copy uses consequence bullet list (UX-DR33)

*Covers: FR22, FR26; UX-DR13, UX-DR33*

---

## Epic 9: Administer governance and system settings

Administrators and authorized users can override marks with audit trail, review audit history, and configure plugin email and capabilities.

### Story 9.1: AuditService, audit REST, and audit UI

As an **administrator**,
I want an append-only audit log of governance actions,
So that overrides and session events are traceable.

**Acceptance Criteria:**

**Given** table `pr_mark_audit` (or `pr_audit`) exists
**When** rubric unlock, re-confirm, override, session close, or account disable occurs
**Then** `AuditService` appends a row with actor, action, entity, old/new values, timestamp
**When** authorized user opens audit view
**Then** paginated log displays with EmptyState when empty
**And** audit log is included as sixth export report type

*Covers: FR24; FR20 audit report*

### Story 9.2: Mark override with mandatory reason

As an **administrator**,
I want to override marks with a required reason,
So that changes are fair and auditable.

**Acceptance Criteria:**

**Given** a user with `pr_override_marks`
**When** they enable override mode on a mark row, edit score, and provide reason ≥ 10 characters
**Then** mark updates and audit row is created
**When** reason is missing or too short
**Then** submit is blocked with inline validation
**And** override UI uses textarea with `aria-required`

*Covers: FR23; UX-DR21*

### Story 9.3: WP Admin settings and capability documentation

As an **administrator**,
I want a WordPress admin settings page for email and capabilities,
So that I can configure the plugin without editing code.

**Acceptance Criteria:**

**Given** a user with `pr_manage_settings`
**When** they open the plugin settings under WP Admin
**Then** they can set from name, reply-to, and base login URL
**And** capability default documentation is visible (native WP admin styles, UX-DR34)
**And** settings persist in options table
**And** invite emails use configured from/reply-to (FR25, NFR17)

*Covers: FR25; UX-DR34*

### Story 9.4: Optional rubric-open and session-closed emails

As an **administrator**,
I want optional notification emails on rubric confirm and session close,
So that stakeholders are informed when enabled.

**Acceptance Criteria:**

**Given** settings toggles for rubric-open and session-closed emails
**When** toggles are off
**Then** no notification emails are sent on those events
**When** toggles are on and coordinator confirms rubric or closes session
**Then** configured templates send to appropriate recipients
**And** emails use plugin-branded HTML (not theme templates)

*Covers: FR30; NFR17*

---

## Epic 16: Data backup and portability

Administrators and coordinators can download an archive of plugin database tables and committee Excel reports before uninstall, migration, or disaster recovery.

### Story 16.1: Project backup — database SQL + Excel reports (ZIP download)

As a **site administrator or review coordinator**,
I want a **ZIP backup** containing plugin SQL data and **Excel reports for each project**,
So that I can archive marking data without manual per-report downloads.

**Acceptance Criteria:**

**Given** a user with `pr_manage_settings`
**When** they download a full-site backup
**Then** the ZIP contains `database/pr-plugin-data.sql`, `options/pr-plugin-options.json`, `manifest.json`, `README.txt`, and per-project Excel workbooks (consolidated + per confirmed review: panel roster, rubric marks matrix, overall scores matrix)
**And** response is raw `application/zip` via `Rest_Binary_Response` (not JSON-wrapped)

**Given** a user with `pr_manage_sessions`
**When** they download a single-project backup
**Then** the ZIP contains session-scoped SQL plus that project’s Excel reports only
**And** global registry tables (`pr_students`, etc.) are included in full in SQL

**Given** the backup is generated
**When** extracted
**Then** restore/import is **not** required in this story (export-only; documented in README)

*See `_bmad-output/implementation/16-1-project-backup-database-reports-zip.md`*

---

## Epic 20: Configurable product branding (Scorva)

Site administrators can set the user-visible product name once (default **Scorva: The Review Management System**) so colleges can brand the review app without renaming technical identifiers (`project-reviews` slug, REST namespace, database).

### Story 20.1: Configurable app branding — Scorva default

As a **site administrator**,
I want **one settings field that controls the product name shown across the app, emails, and WP Admin**,
So that our college can brand the review system without forking the plugin.

**Acceptance Criteria:**

**Given** WP Admin → Project Reviews settings
**When** the administrator sets **Application display name**
**Then** the value persists in `pr_plugin_settings['app_display_name']`
**And** defaults to **Scorva: The Review Management System** when unset

**Given** coordinator, reviewer, or landing SPA loads
**When** the shell wordmark, page title, or landing header renders
**Then** copy uses `prAppData.appDisplayName` / `PluginSettings::app_display_name()` — not hardcoded “Project Reviews”

**Given** invite and notification emails
**When** sent
**Then** subjects and HTML headers use the configured display name (short name before `:` for compact subjects where appropriate)

**Given** technical plugin identity
**When** this story ships
**Then** folder slug, REST namespace `project-reviews/v1`, capabilities, and DB schema are **unchanged** (same boundary as story 10.1)

*See `_bmad-output/implementation/20-1-configurable-app-branding-scorva-default.md`*
