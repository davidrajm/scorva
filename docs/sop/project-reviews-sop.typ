#import "lib/theme.typ": *

#set document(
  title: "Scorva — Standard Operating Procedure",
  author: "Scorva: The Review Management System",
)

#set page(
  paper: "a4",
  margin: (top: 2.2cm, bottom: 2.2cm, left: 2.4cm, right: 2.4cm),
  header: context {
    if counter(page).get().first() > 1 [
      #set text(size: 8.5pt, fill: pr-muted)
      #grid(
        columns: (1fr, 1fr),
        align(left)[Scorva SOP],
        align(right)[#context counter(page).display("1 / 1", both: true)],
      )
      #line(length: 100%, stroke: 0.5pt + pr-border)
    ]
  },
  footer: none,
)

#set text(font: font-body, size: 10.5pt, lang: "en")
#set heading(numbering: "1.1")
#set par(justify: true, leading: 0.65em)
#show heading.where(level: 1): it => {
  v(1.2em)
  text(font: font-display, size: 18pt, weight: "bold", fill: pr-primary)[#it]
  v(0.6em)
}
#show heading.where(level: 2): it => {
  v(0.9em)
  text(size: 13pt, weight: "bold", fill: pr-primary.darken(8%))[#it]
  v(0.35em)
}
#show heading.where(level: 3): it => {
  v(0.6em)
  text(size: 11.5pt, weight: "semibold", fill: pr-coordinator)[#it]
  v(0.25em)
}
#show link: it => text(fill: pr-accent, underline: it)

#title-page(
  "Scorva",
  "Standard Operating Procedure for Coordinators & Reviewers",
  "1.2",
  "June 2026",
)

// ── Front matter ──────────────────────────────────────────────────────────────

= Document purpose

This Standard Operating Procedure (SOP) describes how to use the *Scorva: The Review Management System* WordPress plugin from first login through project setup, marking, reporting, and project closure. It is written for two primary audiences:

#table(
  columns: (auto, 1fr, auto),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  fill: (_, row) => if calc.odd(row) { pr-surface } else { white },
  table.header(
    [*Role*], [*Responsibility*], [*Workspace*],
  ),
  [Coordinators], [
    Maintain the student registry, configure projects (panels, reviewers, rubrics), monitor progress, export committee reports, and close projects safely.
  ], [#role-badge("coordinator")],
  [Reviewers], [
    Enter criterion marks and attendance for assigned students only, freeze personal scores when complete, and (for panel heads) manage panel reports and unfreeze requests.
  ], [#role-badge("reviewer")],
)

#tip[
  *Quick navigation:* Part I covers coordinators; Part II covers reviewers. Shared concepts (login, terminology, troubleshooting) appear first. Figures marked with live screenshots use captures from `npm run sop:screenshots`; remaining figures show placeholders until captured manually.
]

#outline(title: "Table of contents", indent: auto, depth: 3)
#pagebreak()

= Shared concepts

== What Scorva replaces

Scorva replaces spreadsheet-and-email marking workflows with a governed pipeline:

#grid(
  columns: (1fr, 1fr, 1fr),
  gutter: 12pt,
)[
  #block(stroke: 0.75pt + pr-border, radius: 6pt, inset: 12pt, fill: pr-surface)[
    #align(center)[#text(size: 18pt)[📋]]
    #v(4pt)
    #align(center)[#text(weight: "bold")[Setup]]
    Registry → project wizard → confirmed rubrics
  ]
  #block(stroke: 0.75pt + pr-border, radius: 6pt, inset: 12pt, fill: pr-surface)[
    #align(center)[#text(size: 18pt)[✏]]
    #v(4pt)
    #align(center)[#text(weight: "bold")[Marking]]
    Reviewers enter draft/submitted marks per criterion
  ]
  #block(stroke: 0.75pt + pr-border, radius: 6pt, inset: 12pt, fill: pr-surface)[
    #align(center)[#text(size: 18pt)[📊]]
    #v(4pt)
    #align(center)[#text(weight: "bold")[Deliverables]]
    Live views + Excel/CSV exports + panel reports
  ]
]

== Terminology

#table(
  columns: (auto, 1fr),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  fill: (_, row) => if calc.odd(row) { pr-surface } else { white },
  [*Term*], [*Meaning*],
  [Project], [A review event (formerly "session"). Has lifecycle: Draft → Active → Closed.],
  [All Students], [Institution-wide student registry shared across projects.],
  [Panel], [A group of students reviewed together (e.g. Panel A).],
  [Review round], [A rubric-backed marking period within a project (e.g. "Oral presentation").],
  [Rubric], [Weighted criteria with max marks. Must be *confirmed* before marking opens.],
  [Assignment], [A reviewer's scope: project + review round + panel.],
  [Draft mark], [Saved but not final; can be edited freely while marking is open.],
  [Submitted mark], [Final criterion entry for that reviewer (subject to freeze/lock rules).],
  [Flagged mark], [Criterion score changed after rubric re-confirmation; visible to coordinators.],
  [Portal credentials], [A one-time login URL + password emailed to a reviewer; no WordPress account required.],
)

== Access URLs

Replace `your-site.edu` with your institution's WordPress domain.

#url-box("Coordinator workspace", "https://your-site.edu/reviews/")
#v(6pt)
#url-box("Reviewer marking workspace (portal link)", "https://your-site.edu/reviews/mark/?token=…")

#screenshot(
  "01-landing-login",
  "Landing page shown to unauthenticated visitors at `/reviews/`.",
  "Open `/reviews/` in a private/incognito browser window before logging in. Capture the full page with the Scorva wordmark and Log in button.",
)

== Signing in

=== Coordinators

Coordinators sign in with their WordPress account:

#procedure("Log in as coordinator", "coordinator")[
  + Navigate to #raw("https://your-site.edu/reviews/", lang: "").
  + Click *Log in*. You will be redirected to the WordPress login page.
  + Enter your username/email and password.
  + After login, you are routed to the Coordinator dashboard at `/reviews/`.
  + Users with both coordinator and reviewer roles land on the Coordinator dashboard; use the top navigation to switch to the Marking workspace.
]

=== Reviewers — portal access (no WordPress account needed)

Reviewers receive a personalised *invitation email* from their coordinator. The email contains:

- A *Review link* (unique URL with an access token)
- A *Password* for that link

#procedure("Log in as reviewer via portal", "reviewer")[
  + Open the most recent invitation email from your coordinator.
  + Click the *Review link* (or paste it into a browser).
  + On the password screen, enter the *Password* from the same email.
  + Click *Open review portal*. You are taken directly to your assignments.
  + If the link shows "This review link is not valid", open the *most recent* invitation email — earlier emails contain expired links.
]

#screenshot(
  "36-portal-login",
  "Reviewer portal login screen (password entry after clicking the review link).",
  "Open a portal URL with a valid token (from a 'Send credentials' action in the wizard). Capture the password entry screen before submitting.",
)

#screenshot(
  "02-workspace-top-nav",
  "Workspace top navigation with user menu and role switcher (when applicable).",
  "Log in as a user with both coordinator and reviewer access. Capture the top bar showing display name, logout, and any link to the Marking workspace.",
)

#warning[
  If you received more than one invitation email, always use the *most recent* password — earlier tokens are invalidated when credentials are regenerated. Contact your coordinator if you cannot access the portal.
]

#pagebreak()

#part-page(
  "Part I",
  "Coordinator procedures — registry, setup, progress, reports, closure",
  pr-coordinator,
)

= Coordinator overview

#role-badge("coordinator")

Coordinators orchestrate the full review lifecycle:

+ Maintain *All Students* (registry)
+ Create projects and run the *Setup wizard*
+ Confirm rubrics and *open for marking*
+ Monitor *Progress* and chase incomplete marking
+ Download *Reports* for committees
+ *Close* the project when complete

#screenshot(
  "03-coordinator-nav",
  "Coordinator sidebar navigation: Dashboard, All Students, and in-project sections.",
  "Log in as coordinator, open any active project. Capture the left sidebar showing Dashboard, All Students, and the project block (Setup wizard, Progress, Reports, Audit log, Panel Report settings, Close project).",
)

= Dashboard & creating a project

== Viewing projects

The *Dashboard* lists all projects as cards with status chips:

- *Draft* — setup in progress; marking not yet open
- *Active* — marking is open for confirmed rubrics
- *Closed* — marking stopped; reviewer portal access may be disabled

Filter by status using the chips at the top. Click a project card to open its setup wizard.

#screenshot(
  "04-dashboard",
  "Project dashboard with status filters and project cards.",
  "Coordinator dashboard at `/reviews/#/` with at least two projects in different states (Draft, Active). Include the status filter row and Create project control.",
)

== Creating a new project

#procedure("Create a project", "coordinator")[
  + Add students in *All Students* first (registration number and name are required).
  + On the Dashboard, click *Create project*.
  + Enter a descriptive *title* (e.g. "BSc Computer Science — Final Year 2026").
  + Search *All Students* and *Add* each enrollee to the project roster (use the enrolment search — not the registry table search).
  + Click *Create project*. You are taken to the Setup wizard → *Students* step.
]

#tip[
  You can also enrol more students in the wizard via CSV import on the Students step, but the registry must already contain those students.
]

= All Students (registry)

Before students appear in a project, they must exist in *All Students*.

== Add students manually

#procedure("Add a student to the registry", "coordinator")[
  + Sidebar → *All Students*
  + Click *Add student* (header button — opens the form below the page title)
  + In the *Add student* form, complete *Registration number* and *Name* (required), then optional Program and Batch
  + Click *Add student* in the form (not the table *Search students* field)
  + Confirm the new row appears in the registry table
]

== Import students via CSV

#procedure("Bulk-import registry students", "coordinator")[
  + All Students → *Import students*
  + Download the CSV template if needed
  + Upload your file; map columns to registry fields
  + Review import results (partial success shows row-level errors)
  + Download the error CSV if any rows failed
]

#screenshot(
  "05-registry",
  "All Students registry with search, add, and import controls.",
  "All Students page showing the student table, search field, Add student button, and Import students toggle.",
)

#screenshot(
  "06-csv-import-mapper",
  "CSV column mapping interface during student import.",
  "Start a student import with a sample CSV loaded. Capture the column mapper with at least three mapped fields.",
)

= Setup wizard

Open a project → *Setup wizard*. Six sequential steps govern prerequisites:

#table(
  columns: (auto, auto, 1fr),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  fill: (_, row) => if calc.odd(row) { pr-surface } else { white },
  [*Step*], [*Tab*], [*Purpose*],
  [1], [Students], [Enrol registry students into this project (CSV or manual).],
  [2], [Panels], [Create panels and assign enrolled students.],
  [3], [Reviewers], [Add reviewers, send portal credentials via email; bulk CSV import supported.],
  [4], [Reviews & rubrics], [Define review rounds, build rubrics, confirm rubrics.],
  [5], [Panel assignments], [Assign reviewers to panels per review round.],
  [6], [Open reviews], [Activate marking per review round; open project for marking.],
)

#screenshot(
  "07-wizard-nav",
  "Setup wizard step navigation across all six steps.",
  "Open an in-progress project wizard. Capture the horizontal step tabs: Students, Panels, Reviewers, Reviews & rubrics, Panel assignments, Open reviews.",
)

== Step 1 — Students

#procedure("Enrol students in a project", "coordinator")[
  + Ensure students exist in *All Students* first.
  + Wizard → *Students* → *Import Students* (optional CSV enrolment).
  + CSV assigns existing registry students to panels and optional project titles.
  + Verify the enrolled list shows at least one student before continuing.
  + Click *Continue to Panels*.
]

#warning[
  Project CSV import does *not* create new registry students. Create them in All Students first.
]

#screenshot(
  "08-wizard-students",
  "Students wizard step with enrolled list and import option.",
  "Students step showing enrolled count, Import Students button, and at least one student row in the table.",
)

== Step 2 — Panels

#procedure("Configure panels", "coordinator")[
  + Add panels (e.g. Panel A, Panel B).
  + Assign each enrolled student to exactly one panel.
  + Resolve any unassigned students before continuing.
  + Click *Continue to Reviewers*.
]

#screenshot(
  "09-wizard-panels",
  "Panels step with panel list and student assignments.",
  "Panels step showing at least two panels and the student-to-panel assignment UI.",
)

== Step 3 — Reviewers

Reviewers do *not* need WordPress accounts. Scorva generates a secure portal URL + password for each reviewer and emails it directly.

#procedure("Add reviewers and send portal credentials", "coordinator")[
  + Add reviewers individually (name + email) or via *Import reviewers* CSV; assign each to a panel.
  + For each new reviewer row, click *Send credentials*.
  + Scorva emails the reviewer a personalised login URL and password. The status chip updates to *Sent \<date\>*.
  + If SMTP is not yet configured, the status shows *Generated, not delivered* — use *View link* to copy the portal URL and password manually, then share with the reviewer out-of-band.
  + To re-send the same credentials, click *Resend*. To issue a new password (invalidating the old one), click *Regenerate*.
  + Click *Continue to Reviews & rubrics* (or open that wizard tab).
]

#tip[
  *View link* opens a modal with a copyable login URL and password — useful when emailing manually or verifying the reviewer's link. *Regenerate* creates a fresh password and emails it automatically; the reviewer's previous password stops working immediately.
]

#screenshot(
  "10-wizard-reviewers",
  "Reviewers step with roster, credential status, and action buttons.",
  "Reviewers step showing reviewer names, emails, 'Sent <date>' status chips, and Send/Resend/View link buttons.",
)

== Step 4 — Reviews & rubrics

Each *review round* has its own rubric. Rubrics follow a lifecycle:

#table(
  columns: (auto, 1fr),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  [*State*], [*Meaning*],
  [Draft], [Editable; marking blocked.],
  [Confirmed], [Locked structure; marking can open.],
  [Unlocked], [Re-opened for edits; existing marks may be flagged.],
)

#procedure("Create and confirm a rubric", "coordinator")[
  + Each project includes at least *Review 1*; add more rounds with *Add review round* if needed.
  + In the rubric table, enter each criterion label and *Max marks* (required — total marks must not show "—").
  + Click *Save*, then *Confirm* on the rubric card.
  + In the confirmation dialog, click *Confirm rubric* and verify the status chip shows *Confirmed*.
  + If the *Panel assignments* wizard tab stays disabled, refresh the browser page (wizard step flags reload from the server).
  + Repeat for each review round, then open *Panel assignments*.
]

#danger[
  *Re-confirming* a rubric after marks exist may *flag* changed criterion scores. Coordinators see flagged marks in progress views and exports. Always review the consequence summary before re-confirming.
]

#screenshot(
  "11-wizard-rubric-builder",
  "Rubric builder with criteria, weights, and confirm action.",
  "Reviews & rubrics step with rubric builder open showing at least three criteria, weight column, and Confirm rubric button.",
)

#screenshot(
  "12-rubric-confirm-dialog",
  "Rubric confirmation dialog with consequence summary.",
  "Click Confirm rubric and capture the confirmation dialog before accepting.",
)

== Step 5 — Panel assignments

#procedure("Assign reviewers to panels", "coordinator")[
  + For each review round, assign reviewers to panels.
  + Ensure every panel has the required reviewer coverage.
  + Resolve unassigned panels before continuing.
  + Click *Continue to Open reviews*.
]

#screenshot(
  "13-wizard-assignments",
  "Panel assignments matrix per review round.",
  "Panel assignments step showing reviewers assigned to panels for one review round.",
)

== Step 6 — Open reviews

#procedure("Open marking", "coordinator")[
  + Activate marking for each review round individually.
  + When all prerequisites are met, click *Open for marking* at the project level.
  + Project status changes from *Draft* to *Active*.
  + Reviewers see assignments in their marking workspace as soon as they log in via their portal link.
]

#screenshot(
  "14-wizard-open-marking",
  "Open reviews step with per-round activation and Open for marking button.",
  "Open reviews step showing review round toggles and the prominent Open for marking action.",
)

= Progress monitoring

Sidebar → *Progress* (within a project).

Use this *control room* to identify incomplete marking before closing:

- Accordion by review round → panel → reviewer
- Completion percentages and mark status counts
- Student-level score breakdown (read-only; totals are server-computed)
- Flagged marks highlighted

#procedure("Chase incomplete marking", "coordinator")[
  + Open Progress for the project.
  + Expand review rounds with less than 100% completion.
  + Identify panels/reviewers with *Not started* or *In progress* marks.
  + Contact reviewers directly (outside the plugin) or use *Resend* on the Reviewers wizard step to re-email their portal link.
  + Refresh progress after marking sessions.
]

#screenshot(
  "15-progress-accordion",
  "Progress view with review/panel accordion and completion bars.",
  "Progress page with at least one review round expanded showing panel-level completion percentages.",
)

#screenshot(
  "16-score-breakdown",
  "Student score breakdown panel (read-only combined scores).",
  "Select a student in Progress and capture the score breakdown showing weighted totals per review round.",
)

== Panel unfreeze requests

When a reviewer has frozen their scores and needs to edit, they submit an *unfreeze request*. Coordinators see pending requests on the Dashboard and can approve or deny them.

#screenshot(
  "17-unfreeze-requests",
  "Pending panel unfreeze requests on the coordinator dashboard.",
  "Dashboard or dedicated unfreeze panel showing at least one pending request with approve/deny actions.",
)

= Reports & exports

Sidebar → *Reports*. Four live tabs plus downloads:

#table(
  columns: (auto, 1fr),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  [*Tab*], [*Purpose*],
  [Rubric marks], [Live matrix of criterion marks by student/reviewer. Export CSV/Excel.],
  [Overall scores], [Combined weighted scores per student. Export CSV/Excel.],
  [Consolidated], [All students across all confirmed review rounds. Export CSV/Excel.],
  [Downloads], [Committee deliverables: panel roster, marks matrix, scores matrix, offline scoring PDF.],
)

== Locking reports

Coordinators can *lock* report views to prevent further mark changes during export finalisation, and *unlock* when needed.

#screenshot(
  "18-reports-tabs",
  "Reports page tab navigation and review round selector.",
  "Reports page showing all four tabs with Rubric marks active and the review round dropdown.",
)

#screenshot(
  "19-reports-marks-matrix",
  "Rubric marks live matrix with sort and export buttons.",
  "Rubric marks tab populated with data. Include column headers, a few student rows, and CSV/Excel export buttons.",
)

#screenshot(
  "20-reports-downloads",
  "Downloads tab with project-wide and per-review report cards.",
  "Downloads tab showing report cards with Excel and CSV buttons for panel roster, marks matrix, scores matrix, and offline scoring sheet.",
)

== Offline scoring sheet (PDF)

The *Offline scoring sheet* PDF supports paper-based backup marking. Generate it from the Downloads tab per review round.

#screenshot(
  "21-offline-scoring-pdf",
  "Offline scoring sheet card on the Downloads tab.",
  "Downloads tab focused on the Offline scoring sheet card with download button visible.",
)

= Panel Report settings

Sidebar → *Settings → Panel Report* (within a project).

Configure panel report PDF content and branding used by panel heads when generating panel reports.

#screenshot(
  "22-panel-report-settings",
  "Panel Report settings page for a project.",
  "Panel Report settings with configurable fields and save action.",
)

= Audit log

Sidebar → *Audit log*.

Review governance actions: mark overrides, rubric unlocks, session close/reopen, and other audited events. Each entry includes actor, timestamp, and reason where applicable.

#screenshot(
  "23-audit-log",
  "Audit log listing governance events for a project.",
  "Audit log with at least three entries showing action type, user, and timestamp.",
)

= Closing & reopening a project

Sidebar → *Close project* (End project section).

#warning[
  Closing a project stops marking and may disable reviewer portal access. Export all committee deliverables *before* closing.
]

#procedure("Close a project safely", "coordinator")[
  + Complete the pre-close checklist links: Progress → Reports (Downloads) → Audit log.
  + Open *Close project*.
  + Review the close preview (active reviewers, incomplete marks warning).
  + Optionally check *Also disable provisioned reviewer accounts*.
  + Confirm in the dialog. Project status becomes *Closed*.
]

#screenshot(
  "24-close-project",
  "Close project page with pre-close checklist and preview.",
  "Close project page showing checklist links, summary preview, and Close project button.",
)

#procedure("Reopen a closed project", "coordinator")[
  + On the Close project page for a closed project, click *Reopen project*.
  + Confirm in the dialog. Status returns to *Active*; marking rules apply again.
]

#pagebreak()

#part-page(
  "Part II",
  "Reviewer procedures — portal login, assignments, marking, freeze, panel head tasks",
  pr-reviewer,
)

= Reviewer overview

#role-badge("reviewer")

Reviewers work in a focused flow:

#grid(
  columns: (1fr, auto, 1fr, auto, 1fr, auto, 1fr),
  gutter: 6pt,
  align: horizon,
)[
  #block(stroke: 0.75pt + pr-border, radius: 6pt, inset: 8pt, fill: pr-reviewer.lighten(92%))[
    #align(center)[*1. Portal link* \
    Open email link, enter password]
  ],
  [→],
  #block(stroke: 0.75pt + pr-border, radius: 6pt, inset: 8pt, fill: pr-reviewer.lighten(92%))[
    #align(center)[*2. Assignments* \
    Pick panel]
  ],
  [→],
  #block(stroke: 0.75pt + pr-border, radius: 6pt, inset: 8pt, fill: pr-reviewer.lighten(92%))[
    #align(center)[*3. Marking grid* \
    All students]
  ],
  [→],
  #block(stroke: 0.75pt + pr-border, radius: 6pt, inset: 8pt, fill: pr-reviewer.lighten(92%))[
    #align(center)[*4. Rubric form* \
    Per-student criteria]
  ],
]

You only see students *assigned to you*. You cannot access other panels or unassigned students.

= Portal login

Open the invitation email from your coordinator and click the *Review link*. You will see the password entry screen.

#screenshot(
  "36-portal-login",
  "Portal login screen — password entry after clicking the review link.",
  "Open a reviewer portal URL (from 'Send credentials'). Capture the password field and 'Open review portal' button.",
)

#procedure("Log in via the portal", "reviewer")[
  + Open the invitation email and click *Review link* (or copy-paste the URL).
  + Enter the *Password* from the same email.
  + Click *Open review portal*.
  + You are taken directly to *Your assignments*.
  + If you receive a new invitation email (after *Regenerate*), the previous password no longer works — always use the latest email.
]

#warning[
  Do not share your portal link or password. The link is personal and grants direct access to your marking workspace without any additional login step.
]

= Your assignments

After portal login, the *Your assignments* page lists available and blocked assignments.

- *Available* assignments — rubric confirmed, project active, marking activated → click to open marking grid
- *Unavailable assignments* — setup incomplete; message explains why (e.g. rubric not confirmed, project closed)

#screenshot(
  "25-reviewer-assignments",
  "Reviewer assignments page with available and unavailable cards.",
  "Marking workspace home showing at least one available assignment card and one unavailable assignment with reason text.",
)

#table(
  columns: (auto, 1fr),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  [*Blocked reason*], [*What it means*],
  [Project not open], [Coordinator has not clicked Open for marking.],
  [Rubric not confirmed], [Coordinator must confirm the rubric on the wizard.],
  [Marking inactive], [Coordinator must activate this review round.],
  [Project closed], [Marking has ended for this project.],
  [Coordinator locked], [Coordinator locked marks for this review round.],
)

= Marking grid

Click an assignment card to open the *marking grid* — all students in your panel for that review round.

Features:

- Row per student with registration number, name, project title
- Quick status chips (not started / in progress / complete)
- Attendance indicator
- *Update score* on each row opens the rubric form in a modal
- *Freeze scores* (panel-level) when all marks are ready for coordinator reports

#screenshot(
  "26-marking-grid",
  "Marking grid with student rows and status chips.",
  "Marking grid for one panel showing column headers, several students with varied mark statuses, and Freeze my scores button.",
)

== Mobile / narrow view

On narrow screens, the grid collapses to *student cards* with the same information.

#screenshot(
  "27-marking-grid-mobile",
  "Mobile card layout for the marking grid.",
  "Resize browser to mobile width (~375px) on the marking grid and capture the card layout.",
)

= Entering marks (rubric form)

#procedure("Mark a student", "reviewer")[
  + Open the marking grid for your assignment.
  + Click *Update score* on a student row (do not use *Freeze scores* unless you intend to lock the whole panel for that review).
  + In the modal, set *Attendance* (Present / Absent) if shown.
  + Enter a score for each criterion (half-point increments where enabled); scores are validated against max marks inline.
  + Click *Save* to store a *Draft* mark; the grid shows the score and draft status.
  + Repeat for each student, then use *Freeze scores* when the panel is complete.
]

#tip[
  *Draft* marks can be edited until you freeze scores or the coordinator locks the review. If you see *No assignments yet*, contact your coordinator — they may need to send your portal credentials or open the project for marking.
]

#screenshot(
  "28-rubric-form",
  "Score entry modal (rubric form) with criterion scores and attendance.",
  "Click Update score on the marking grid; capture the modal with criterion inputs, attendance, and Save button.",
)

#screenshot(
  "29-validation-error",
  "Inline validation error on rubric form.",
  "Enter an invalid score (above max marks) and capture the inline error message before correcting.",
)

== Flagged marks

If a coordinator re-confirms a rubric after you submitted marks, affected scores may show a *flagged* indicator. You may need to review and re-submit. Contact your coordinator if unsure.

#screenshot(
  "30-flagged-mark",
  "Flagged mark indicator on a criterion score.",
  "Student row or rubric form showing a flagged mark chip on at least one criterion.",
)

= Freezing & unfreezing scores

When you have submitted marks for all students in your assignment:

#procedure("Freeze your scores", "reviewer")[
  + On the marking grid, click *Freeze my scores*.
  + Read the confirmation dialog.
  + Confirm. Your marks become read-only.
  + To edit after freezing, submit an *unfreeze request* with a reason.
]

#screenshot(
  "31-freeze-scores-dialog",
  "Freeze my scores confirmation dialog.",
  "Click Freeze my scores and capture the confirmation dialog.",
)

#procedure("Request an unfreeze", "reviewer")[
  + On a frozen assignment, click *Request unfreeze*.
  + Enter a brief reason (required).
  + Submit. Status shows *Pending* until a coordinator or panel head acts.
  + When approved, you can edit and re-freeze.
]

#screenshot(
  "32-unfreeze-request",
  "Unfreeze request form with reason field.",
  "Frozen assignment showing Request unfreeze button and the reason dialog.",
)

= Panel head responsibilities

If you are designated *panel head* (panel coordinator) for a panel, your assignment card also links to *Panel report*.

Panel heads can:

- Generate the panel report PDF for their panel
- Approve or deny unfreeze requests from co-reviewers on the same panel

#screenshot(
  "33-panel-head-card",
  "Assignment card showing Panel report link for panel heads.",
  "Assignment card for a panel head user showing both Mark students and Panel report links, plus co-reviewer chips.",
)

#screenshot(
  "34-panel-report-page",
  "Panel report generation page.",
  "Panel report page with preview/download controls for the panel head.",
)

#screenshot(
  "35-panel-head-unfreeze",
  "Panel head unfreeze request queue.",
  "Panel head view of pending unfreeze requests from co-reviewers with approve/deny actions.",
)

#pagebreak()

= Troubleshooting

#table(
  columns: (1fr, 1fr, auto),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  fill: (_, row) => if calc.odd(row) { pr-surface } else { white },
  [*Problem*], [*Likely cause*], [*Who fixes*],
  [Cannot see any assignments], [Credentials not sent, portal session expired, or setup incomplete], [Coordinator],
  [Portal link says "not valid"], [Using an old invitation email — regenerated token replaces the previous one], [Reviewer — open latest email],
  [Stuck after filling reg. no. on registry], [Used table search instead of *Add student* form — fill *Name* and submit form], [Coordinator],
  [Panel assignments tab disabled], [Rubric missing max marks or not confirmed — refresh page after confirm], [Coordinator],
  [Assignment listed as unavailable], [Rubric unconfirmed, marking inactive, or project closed], [Coordinator],
  [Score will not save], [Project closed, coordinator lock, or panel frozen], [Coordinator / Panel head],
  [Wrong max marks error], [Score exceeds criterion max], [Reviewer — correct the value],
  [Attendance conflict], [Co-reviewers disagree on attendance consensus], [Reviewers — align; Coordinator if stuck],
  [Portal session expired mid-session], [Token session timed out — revisit portal URL and re-enter password], [Reviewer],
  [Status shows "Generated, not delivered"], [SMTP not configured or email bounce — use View link to share credentials manually], [Coordinator],
  [Export empty or missing columns], [Rubric not confirmed or no marks submitted], [Coordinator],
)

= Quick reference card

#block(
  stroke: 1pt + pr-primary,
  radius: 8pt,
  inset: 16pt,
  fill: pr-primary.lighten(95%),
  width: 100%,
)[
  #text(weight: "bold", fill: pr-primary, size: 12pt)[Coordinator checklist]
  #v(6pt)
  #grid(
    columns: (1fr, 1fr),
    gutter: 16pt,
  )[
    - ☐ All Students populated
    - ☐ Project created
    - ☐ Panels & reviewers configured
    - ☐ Credentials sent to reviewers
    - ☐ Rubrics confirmed
    - ☐ Panel assignments complete
    - ☐ Open for marking
    - ☐ Progress monitored
    - ☐ Reports exported
    - ☐ Project closed
  ]
  #v(12pt)
  #text(weight: "bold", fill: pr-reviewer, size: 12pt)[Reviewer checklist]
  #v(6pt)
  #grid(
    columns: (1fr, 1fr),
    gutter: 16pt,
  )[
    - ☐ Open portal link from invitation email
    - ☐ Enter password → Open review portal
    - ☐ Open assignment
    - ☐ Mark all students
    - ☐ Submit (not just draft)
    - ☐ Freeze scores
    - ☐ Panel report (if panel head)
  ]
]

#pagebreak()

#heading(outlined: true, level: 1, numbering: none)[One-page process flow]

// ── One-page process flow (appendix) ──────────────────────────────────────────

#block(
  breakable: false,
  width: 100%,
)[
  #block(
    fill: pr-primary,
    inset: (x: 14pt, y: 10pt),
    radius: 6pt,
    width: 100%,
  )[
    #grid(
      columns: (1fr, auto),
      align(left + horizon)[
        #text(fill: white, font: font-display, size: 16pt, weight: "bold")[
          One-page process flow
        ]
        #v(2pt)
        #text(fill: white.transparentize(25%), size: 9pt)[
          End-to-end lifecycle · Coordinator setup gates reviewer marking
        ]
      ],
      align(right + horizon)[
        #text(size: 8pt, fill: white.transparentize(30%))[Quick reference · tear-out page]
      ],
    )
  ]

  #v(8pt)

  #set text(size: 8.5pt)
  #set par(leading: 0.55em, justify: false)

  // Shared entry
  #flow-node(
    "1 · Sign in",
    detail: [Coordinator: `/reviews/` → WP login · Reviewer: open portal link from email → enter password],
    accent: pr-primary,
  )
  #flow-arrow()

  #grid(
    columns: (1fr, 28pt, 1fr),
    column-gutter: 6pt,
    row-gutter: 0pt,
  )[
    // ── Coordinator lane ──
    #block(width: 100%)[
      #align(center)[
        #box(
          fill: pr-coordinator.lighten(88%),
          stroke: 0.75pt + pr-coordinator,
          radius: 4pt,
          inset: (x: 10pt, y: 4pt),
        )[
          #text(size: 8pt, weight: "bold", fill: pr-coordinator)[COORDINATOR · `/reviews/`]
        ]
      ]
      #v(6pt)
      #flow-node("2 · All Students", detail: "Registry · manual add or CSV import", accent: pr-coordinator)
      #flow-arrow()
      #flow-node("3 · Create project", detail: "Dashboard → title · status: Draft", accent: pr-coordinator)
      #flow-arrow()
      #flow-node("4 · Setup wizard", detail: "Students → Panels → Reviewers", accent: pr-coordinator)
      #flow-arrow()
      #flow-node("5 · Reviews & rubrics", detail: "Build criteria · Confirm rubric", accent: pr-coordinator)
      #flow-arrow()
      #flow-gate("6 · Gate: Rubric confirmed", "Reviewers blocked until this step completes")
      #flow-arrow()
      #flow-node("7 · Panel assignments", detail: "Assign reviewers to panels per round", accent: pr-coordinator)
      #flow-arrow()
      #flow-node("8 · Open reviews", detail: "Activate rounds · Open for marking → Active", accent: pr-coordinator)
      #flow-arrow()
      #flow-gate("9 · Gate: Marking open", "Assignments appear in reviewer workspace")
      #flow-arrow()
      #flow-node("10 · Progress", detail: "Monitor completion · Handle unfreeze requests", accent: pr-coordinator)
      #flow-arrow()
      #flow-node("11 · Reports", detail: "Marks · Scores · Consolidated · Downloads", accent: pr-coordinator)
      #flow-arrow()
      #flow-node("12 · Close project", detail: "Export first · Disable reviewer access · Closed", accent: pr-coordinator)
    ]

    // ── Centre handoffs ──
    #align(center)[
      #v(42pt)
      #flow-handoff("Gate 6")
      #v(108pt)
      #flow-handoff("Gate 9")
      #v(88pt)
      #flow-handoff("Done")
    ]

    // ── Reviewer lane ──
    #block(width: 100%)[
      #align(center)[
        #box(
          fill: pr-reviewer.lighten(88%),
          stroke: 0.75pt + pr-reviewer,
          radius: 4pt,
          inset: (x: 10pt, y: 4pt),
        )[
          #text(size: 8pt, weight: "bold", fill: pr-reviewer)[REVIEWER · portal link from email]
        ]
      ]
      #v(6pt)
      #block(
        fill: pr-surface,
        stroke: 0.75pt + pr-border,
        radius: 4pt,
        inset: 8pt,
        width: 100%,
      )[
        #text(size: 8pt, fill: pr-muted)[
          *Waiting* — assignment visible but unavailable until Gates 6 & 9 pass
        ]
      ]
      #v(6pt)
      #flow-node("A · Portal login", detail: "Click review link in email · enter password · Open review portal", accent: pr-reviewer)
      #flow-arrow()
      #flow-node("B · Your assignments", detail: "Pick project · review round · panel", accent: pr-reviewer)
      #flow-arrow()
      #flow-node("C · Marking grid", detail: "All students in panel · status chips", accent: pr-reviewer)
      #flow-arrow()
      #flow-node("D · Rubric form", detail: "Attendance · criterion scores · draft or submit", accent: pr-reviewer)
      #flow-arrow()
      #flow-node("E · Freeze scores", detail: "Lock personal marks when complete", accent: pr-reviewer)
      #v(4pt)
      #block(
        fill: pr-reviewer.lighten(94%),
        stroke: 0.75pt + pr-reviewer.lighten(40%),
        radius: 4pt,
        inset: 8pt,
        width: 100%,
      )[
        #text(size: 8pt, weight: "bold", fill: pr-reviewer)[Panel head only]
        #v(2pt)
        #text(size: 8pt, fill: pr-muted)[
          Panel report PDF · Approve co-reviewer unfreeze requests
        ]
      ]
      #v(6pt)
      #block(
        fill: pr-surface,
        stroke: 0.75pt + pr-border,
        radius: 4pt,
        inset: 8pt,
        width: 100%,
      )[
        #text(size: 8pt, fill: pr-muted)[
          *Need to edit after freeze?* Request unfreeze → coordinator or panel head approves
        ]
      ]
    ]
  ]

  #v(8pt)

  // Lifecycle strip
  #block(
    fill: pr-surface,
    stroke: 0.75pt + pr-border,
    radius: 4pt,
    inset: 10pt,
    width: 100%,
  )[
    #text(size: 8pt, weight: "bold", fill: pr-primary)[Project lifecycle]
    #v(4pt)
    #grid(
      columns: (auto, 1fr, auto, 1fr, auto, 1fr, auto),
      column-gutter: 4pt,
      align: horizon,
    )[
      #box(fill: pr-muted.lighten(80%), inset: 4pt, radius: 3pt)[#text(size: 7.5pt)[*Draft*]]
      #align(center)[#text(size: 9pt, fill: pr-muted)[→]]
      #box(fill: pr-coordinator.lighten(85%), inset: 4pt, radius: 3pt)[#text(size: 7.5pt)[*Active*]]
      #align(center)[#text(size: 9pt, fill: pr-muted)[→]]
      #box(fill: pr-danger.lighten(88%), inset: 4pt, radius: 3pt)[#text(size: 7.5pt)[*Closed*]]
      #h(1fr)
      #text(size: 7.5pt, fill: pr-muted)[
        Draft = setup · Active = marking open · Closed = no new marks
      ]
    ]
  ]

  #v(6pt)

  #grid(
    columns: (1fr, 1fr, 1fr),
    gutter: 8pt,
  )[
    #block(stroke: 0.5pt + pr-border, radius: 4pt, inset: 8pt)[
      #text(size: 7.5pt, weight: "bold", fill: pr-coordinator)[Coordinator URLs]
      #v(2pt)
      #text(size: 7pt)[`/reviews/` \
      `#/registry` · `#/session/{id}/wizard` \
      `#/session/{id}/progress` · `#/session/{id}/reports`]
    ]
    #block(stroke: 0.5pt + pr-border, radius: 4pt, inset: 8pt)[
      #text(size: 7.5pt, weight: "bold", fill: pr-reviewer)[Reviewer URLs]
      #v(2pt)
      #text(size: 7pt)[`/reviews/mark/?token=…` (portal link) \
      `#/` assignments · `#/mark/{s}/{r}/{p}` \
      `#/panel-report/{s}/{r}/{p}` (panel head)]
    ]
    #block(stroke: 0.5pt + pr-border, radius: 4pt, inset: 8pt)[
      #text(size: 7.5pt, weight: "bold", fill: pr-warning)[Hard stops]
      #v(2pt)
      #text(size: 7pt)[
        Rubric unconfirmed · Marking inactive \
        Project closed · Coordinator lock · Frozen scores \
        Portal session expired
      ]
    ]
  ]
]

= Document maintenance

#table(
  columns: (auto, 1fr),
  inset: 10pt,
  stroke: 0.5pt + pr-border,
  [*Item*], [*Detail*],
  [Source file], [`docs/sop/project-reviews-sop.typ`],
  [Theme], [`docs/sop/lib/theme.typ` — `sop-screenshots-dir`, `use-live-screenshots`],
  [Regenerate captures], [`npm run build` then `npm run sop:screenshots` from plugin root; copy printed `sop-screenshots-dir` into `lib/theme.typ`],
  [Compile PDF], [`cd docs/sop && typst compile project-reviews-sop.typ`],
  [Output], [`docs/sop/project-reviews-sop.pdf`],
)

#v(2em)
#align(center)[
  #text(size: 9pt, fill: pr-muted)[
    End of document · Scorva SOP v1.2
  ]
]
