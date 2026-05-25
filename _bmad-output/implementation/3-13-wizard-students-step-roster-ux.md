# Story 3.13: Wizard Students step — roster UX, registry gate, tab nav

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **program coordinator**,
I want the project wizard **Students** step to focus on CSV import from the master student list, with clearer labels, collapsible import, and tab-style step navigation,
So that I enrol the right cohort without clutter, know when students must be added to **All Students** first, and can scan the wizard comfortably.

## Acceptance Criteria

1. **Remove inline registry search (Students step only)**
   - **Given** `#/session/:id/wizard?step=students`
   - **When** the coordinator views the step
   - **Then** there is **no** “Search registry” label, search input, or per-result **Add** list
   - **And** `SessionWizard.jsx` no longer fetches `/students?search=…` for this step (remove `registrySearch`, `registryResults`, related `useEffect`, and `enrolStudent` usage tied to search UI only)
   - **And** `POST /sessions/{id}/students` remains available for other flows (dashboard create, tests) — no REST removal

2. **Section rename — Add students**
   - **Given** the Students step
   - **When** rendered
   - **Then** the primary section heading is **Add students** (not “Project roster”)
   - **And** intro copy explains: students must exist in **All Students** before they can be added to this project; use CSV import below (no mention of searching the registry on this page)

3. **CSV block — labels and collapsible import**
   - **Given** the enrol CSV mapper (`importType="session-enrol"`)
   - **When** `IMPORT_TYPE_CONFIG` renders
   - **Then** `title` is **Add Student from CSV** (not “Re-enrol students from CSV”)
   - **And** the mapper panel is **hidden by default**
   - **When** the coordinator clicks **Import Students** (secondary button in the **Add students** section)
   - **Then** the mapper panel becomes visible; clicking again (label **Hide import** or equivalent) collapses it
   - **Pattern:** mirror `Registry.jsx` `showImport` toggle (`useState(false)` + conditional render), not always-visible `mt-6` block

4. **Enrolled list heading**
   - **Given** enrolled students in the project
   - **When** the roster table section renders
   - **Then** the heading is **Students Added to this Project (N)** where `N` is `enrolled.length` (not “Project roster (N)”)

5. **Registry gate — all-or-nothing before enrol**
   - **Given** a CSV mapped for import with one or more `reg_no` values
   - **When** any registration number is **not** in `pr_students` (global registry / **All Students**)
   - **Then** the server **does not** enrol or update any row for that request
   - **And** responds with `400` / code `pr_students_not_in_registry` and payload including `missing_reg_nos: string[]` (unique, sorted, trimmed)
   - **And** message instructs adding those students to **All Students** first, then re-importing
   - **When** every `reg_no` exists in the registry
   - **Then** existing `import_enrolment` behaviour runs (enrol, update panel/title, create panels as today)

6. **Client messaging for missing registry students**
   - **Given** import fails with `pr_students_not_in_registry`
   - **When** the UI shows the error
   - **Then** a **warning** `Notice` lists every missing reg no (comma-separated or bullet list)
   - **And** copy includes: add them in **All Students** (`#/registry`) before importing to this project
   - **And** a link styled like existing template links: **Open All Students** → `Link` to `/registry` (HashRouter)
   - **And** no partial success toast when the gate fails (zero rows changed)

7. **Instructional copy — All Students**
   - **Given** the **Add students** section (above the Import Students button)
   - **When** displayed
   - **Then** include a short info block (info/warning tone, not error) with:
     - Students must be created in **All Students** before they appear in a project CSV import
     - Link to **All Students** to add or import registry students
     - Note that project CSV only assigns **existing** registry students to panels (and optional project title)

8. **Coordinator nav — All Students label**
   - **Given** coordinator sidebar global nav
   - **When** rendered
   - **Then** the registry item label is **All Students** (not “Student Registry”)
   - **And** `Registry.jsx` `PageHeader` title is **All Students**; description may still mention enrolling in projects

9. **Spacing — Draft project banner vs wizard nav**
   - **Given** a draft project with the “Draft project” callout visible
   - **When** the wizard renders
   - **Then** there is clear vertical separation between the draft callout and `WizardNav` (target ≥ 24px perceived gap — e.g. `mb-6` on callout and/or `mt-6` on nav, adjust `WizardNav` top margin so it is not flush under the banner)

10. **WizardNav — tab styling with icons**
    - **Given** any wizard step
    - **When** `WizardNav` renders
    - **Then** steps read as **horizontal tabs**: connected or segmented tab row with bottom border on the nav container, active tab with primary bottom border or filled tab surface (match design tokens)
    - **And** each step shows an **icon + label** (use `Icon` from `NavIcon.jsx`; add minimal SVG keys only if missing)
    - **Suggested icon map:** `students` → `users`, `panels` → `panel`, `reviewers` → `users`, `assignments` → `panel` or new `clipboard` icon, `rubrics` → `rubrics`, `reviews` → `wizard` or new `calendar` icon
    - **And** blocked steps keep `title` tooltip with blocker reason; completed/current/blocked states unchanged functionally (UX-DR9)
    - **And** remove arrow `→` separators between steps (tabs replace chevrons)

11. **Regression**
    - **Given** PHPUnit and build
    - **When** complete
    - **Then** extend `RestSessionsTest` / `SessionRepositoryTest` for registry gate (all missing → no DB change; all present → success)
    - **And** `composer test` + `npm run build` pass
    - **And** manual: toggle import, import valid CSV, import CSV with unknown reg no, wizard nav tabs on all steps, draft spacing

## Tasks / Subtasks

- [x] **SessionWizard.jsx:** Remove registry search UI/state; restructure Students step (AC 1–4, 7, 9)
- [x] **CsvImportMapper.jsx:** Update `session-enrol` title; optional `defaultCollapsed` / parent-controlled visibility (AC 3)
- [x] **SessionRepository + Rest_Sessions:** Pre-validate all `reg_no` in `import_enrolment`; `pr_students_not_in_registry` (AC 5)
- [x] **CsvImportMapper / apiErrors:** Map new error code to Notice + missing list + registry link (AC 6)
- [x] **CoordinatorNav.jsx + Registry.jsx:** Rename to **All Students** (AC 8)
- [x] **WizardNav.jsx + Icon.jsx:** Tab layout, per-step icons (AC 10)
- [x] **CSS/spacing:** Draft banner vs nav gap (AC 9)
- [x] **Tests + build** (AC 11)

## Dev Notes

### Product intent (user request)

Target URL: `http://sastt.local/reviews/#/session/1/wizard?step=students`

| # | Request | Implementation |
|---|---------|----------------|
| 1 | Remove Search registry + form | Delete wizard-only search block |
| 2 | Project roster → **Add students** | Section `h2` |
| 3 | Re-enrol CSV → **Add Student from CSV** | `IMPORT_TYPE_CONFIG['session-enrol'].title` |
| 4 | CSV hidden until **Import Students** | Toggle like Registry `showImport` |
| 5 | Project roster (n) → **Students Added to this Project (n)** | Table section `h3` |
| 6–7 | Unknown CSV reg nos → must be in **All Students** first | Server all-or-nothing gate + UI list + link |
| 8 | Draft div too close to nav | Margin on callout / `WizardNav` |
| 9 | Wizard steps like **tabs with icons** | `WizardNav` visual refresh |

### Target layout (Students step, top → bottom)

```
## Add students
(info callout: All Students prerequisite + Link to /registry)

[ Import Students ]  (secondary)  → toggles visibility

[ CsvImportMapper — title "Add Student from CSV" ]  (when visible)

## Students Added to this Project (12)
(table: reg no, name, project title, panel, Remove)

[ Continue to Panels ]
```

### Registry gate (server) — implementer sketch

```php
// SessionRepository::import_enrolment — before mutating loop
$missing = [];
foreach ($rows as $row) {
    $reg = trim((string) ($row['reg_no'] ?? ''));
    if ($reg === '') { continue; } // still fail row in loop OR collect as empty — keep row validation
    if ($students->find_by_reg_no($reg) === null) {
        $missing[] = $reg;
    }
}
$missing = array_values(array_unique($missing));
sort($missing, SORT_STRING);
if ($missing !== []) {
    return new \WP_Error(
        'pr_students_not_in_registry',
        __('Add these students to All Students before importing to this project.', 'project-reviews'),
        ['status' => 400, 'missing_reg_nos' => $missing]
    );
}
// existing foreach enrol/update logic
```

`Rest_Sessions::import_enrolment` should return the `WP_Error` unchanged (REST layer already maps errors).

**Breaking change vs today:** partial imports with per-row “Student not found in registry” no longer occur — entire batch fails upfront. Acceptable per product request.

### Client error mapping

Use `parseApiErrorMessage` or extend `src/shared/apiErrors.js` if `missing_reg_nos` is in `err.data`. Render:

> The following registration numbers are not in **All Students**: 25MDT9999, 25MDT8888. Add them in All Students, then run Import Students again.

### Files to touch (expected)

| File | Change |
|------|--------|
| `src/coordinator/pages/SessionWizard.jsx` | Students step UX, remove search, spacing |
| `src/coordinator/components/CsvImportMapper.jsx` | `session-enrol` title; optional collapse prop |
| `src/shared/components/WizardNav.jsx` | Tab UI + icons per step |
| `src/shared/components/NavIcon.jsx` (`Icon`) | New icons only if needed |
| `src/coordinator/CoordinatorNav.jsx` | All Students label |
| `src/coordinator/pages/Registry.jsx` | Page title All Students |
| `includes/repositories/SessionRepository.php` | Registry preflight |
| `includes/rest/class-rest-sessions.php` | Pass through `WP_Error` |
| `tests/RestSessionsTest.php` | Gate tests |
| `tests/SessionRepositoryTest.php` | Optional repository-level test |

### Do NOT change

- Dashboard optional student attach at project create (`3-8`) — separate flow
- Registry CSV import (`importType="students"`) — still creates registry rows
- Enrolled table columns, project title blur save, `has_scores` remove guard (`3-12`)
- Wizard step order: `students → panels → reviewers → assignments → rubrics → reviews` (`3-11`)
- `POST /sessions/{id}/enrol` route path — only response behaviour on unknown reg nos

### WizardNav tab styling hints

- Container: `border-b border-border` with `flex` row, no `flex-wrap gap-2` pill buttons
- Tab button: `flex items-center gap-2 px-4 py-3 -mb-px border-b-2 border-transparent`
- Active: `border-primary text-primary font-semibold`
- Complete (not current): `text-text hover:border-border`
- Blocked: `opacity-60 cursor-not-allowed` + `title`
- Use `role="tablist"` / `aria-selected` on active tab for a11y

### Previous story intelligence

- **3-3:** Original search + CSV enrol — this story **replaces** search UX on wizard only
- **3-12:** Roster table with `project_title`, `session-enrol` template with `project_title` column — keep
- **3-9 / Registry:** Collapsible import pattern — copy `showImport` toggle
- **10-1:** Project terminology — use **project** not session in user copy

### References

- [Source: src/coordinator/pages/SessionWizard.jsx — Students step lines 334–487]
- [Source: src/coordinator/components/CsvImportMapper.jsx — `session-enrol` config lines 26–37]
- [Source: src/coordinator/pages/Registry.jsx — `showImport` toggle lines 19, 110–118]
- [Source: includes/repositories/SessionRepository.php — `import_enrolment` lines 431–496]
- [Source: _bmad-output/planning/ux-design-specification.md — WizardNav UX-DR9]
- [Source: _bmad-output/implementation/3-3-wizard-students-enrolment.md]
- [Source: _bmad-output/implementation/3-12-student-project-title-per-review.md]

## Dev Agent Record

### Agent Model Used

Composer (dev-story agent)

### Debug Log References

### Completion Notes List

- Students step: removed registry search; **Add students** section with All Students info callout and collapsible CSV import (`showStudentImport` toggle).
- Server: `import_enrolment` all-or-nothing registry preflight returns `pr_students_not_in_registry` with sorted `missing_reg_nos`; no partial enrol on gate failure.
- Client: CsvImportMapper shows warning Notice listing missing reg nos + **Open All Students** link; `session-enrol` title **Add Student from CSV**.
- Nav: coordinator sidebar and Registry page titled **All Students**; WizardNav tab row with icons; draft callout `mb-6` + nav `mt-6`.
- Tests: `RestSessionsTest::test_import_enrolment_rejects_missing_registry_students`, `SessionRepositoryTest` import gate tests. PHPUnit 266 OK; `npm run build` OK.

### File List

- `src/coordinator/pages/SessionWizard.jsx`
- `src/coordinator/components/CsvImportMapper.jsx`
- `src/coordinator/CoordinatorNav.jsx`
- `src/coordinator/pages/Registry.jsx`
- `src/shared/components/WizardNav.jsx`
- `src/shared/components/NavIcon.jsx`
- `includes/repositories/SessionRepository.php`
- `tests/RestSessionsTest.php`
- `tests/SessionRepositoryTest.php`
- `build/coordinator.js` (generated)
- `build/coordinator.css` (generated)
- `build/coordinator-rtl.css` (generated)

### Change Log

- 2026-05-17: Story created from coordinator UX request (wizard Students step).
- 2026-05-17: Implemented wizard Students roster UX, registry import gate, tab nav, and All Students labelling.
