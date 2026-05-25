# Story 2.5: Per-project guide and panel on enrolment (remove from All Students)

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **program coordinator**,
I want **All Students** to hold identity fields (`reg_no`, `name`, `program`, `batch`) while **guide** and **panel** are captured when a student is enrolled in a project,
So that the same student can have different guides and panels across projects without conflicting global registry data, and program is recorded once in the master list.

## Acceptance Criteria

1. **All Students registry — schema and API**
   - **Given** plugin upgrade runs `ensure_schema_patches`
   - **When** migration completes
   - **Then** `pr_students` has **no** `guide_name` column (dropped after data backfill — see Dev Notes)
   - **And** `pr_students` has `program` `varchar(64) NOT NULL DEFAULT ''` (added via patch on existing installs; included in `table_students()` for fresh installs)
   - **And** `table_students()` DDL in `Install.php` no longer defines `guide_name` and defines `program` **before** `batch`
   - **And** `StudentRepository` allowed fields are `reg_no`, `name`, `program`, `batch` (+ meta) only
   - **And** `GET/POST/PUT /students` include `program`; responses omit `guide_name`; writes with `guide_name` are ignored or rejected with `400` `pr_unknown_field` (pick one — prefer ignore for forward-compatible clients)
   - **And** student search (`GET /students?search=`) matches `program` in addition to `reg_no`, `name`, `batch`

2. **All Students UI and CSV**
   - **Given** `#/registry` (All Students)
   - **When** the coordinator views the table or add/edit form
   - **Then** base columns/fields are **Reg. no.**, **Name**, **Program**, **Batch** (in that order), then custom meta — **no** Guide or Panel columns
   - **Given** `StudentForm.jsx`
   - **When** add/edit renders
   - **Then** **Program** appears **before** **Batch**
   - **Given** registry CSV import (`importType="students"`)
   - **When** the coordinator maps columns
   - **Then** optional fields are `program` and `batch` (not `guide_name` or `panel`)
   - **And** `assets/csv/students-import-template.csv` header is `reg_no,name,program,batch` (no guide/panel)
   - **And** rows that include extra columns in the file may map to custom fields via meta if configured — do **not** silently store guide/panel on the student row
   - **And** registry search placeholder mentions program (e.g. “reg. no., name, program, batch”)

3. **Project enrolment — schema**
   - **Given** migrations run
   - **When** `pr_session_students` is patched
   - **Then** nullable columns exist:
     - `guide_emp_id` `varchar(64) NOT NULL DEFAULT ''` (institutional employee id; empty string = unset)
     - `guide_name` `varchar(255) NOT NULL DEFAULT ''`
   - **And** existing `panel_id` remains the canonical panel assignment (FK to `pr_panels`) — **no** duplicate free-text `panel` column on enrolment

4. **Project enrolment — CSV import (`session-enrol`)**
   - **Given** a project Students step CSV import
   - **When** the coordinator maps columns
   - **Then** required: `reg_no`, `panel` (panel name — creates panel if missing, same as today)
   - **And** optional: `project_title`, `guide_emp_id`, `guide_name`
   - **And** `SessionRepository::import_enrolment` persists guide fields on insert/update enrolment
   - **And** `assets/csv/session-enrol-template.csv` includes `reg_no,panel,project_title,guide_emp_id,guide_name` (order may match UI labels)
   - **Given** registry gate from story **3.13** (all `reg_no` must exist in All Students)
   - **When** any reg no is missing from registry
   - **Then** behaviour unchanged — `400` `pr_students_not_in_registry`, zero rows changed

5. **Project enrolment — roster UI and REST**
   - **Given** enrolled students on the wizard Students step (and any roster list using `GET /sessions/{id}/students`)
   - **When** listed
   - **Then** each item includes `guide_emp_id`, `guide_name`, `panel_id`, `panel_name`, `project_title` (existing), and student summary (`reg_no`, `name`, `program`, `batch`, …) **without** guide
   - **Given** `PUT /sessions/{id}/students/{student_id}`
   - **When** body includes `guide_emp_id`, `guide_name`, `panel_id`, and/or `project_title`
   - **Then** enrolment row updates accordingly (panel assign uses existing `assign_panel` + review sync)
   - **Given** wizard roster table
   - **When** coordinator edits guide fields and saves
   - **Then** UI exposes **Guide emp. ID**, **Guide name**, **Panel** (select), **Project title** per enrolled row (match dense wizard table patterns from **3.12**)

6. **Data migration (existing installs)**
   - **Given** existing `pr_students.guide_name` values before upgrade
   - **When** `guide_name` column is added to `pr_session_students` and backfill runs
   - **Then** for each enrolment row, if `guide_name` is empty, set it from the linked student’s former registry `guide_name`
   - **And** `guide_emp_id` remains empty unless supplied later
   - **When** backfill completes
   - **Then** drop `guide_name` from `pr_students`
   - **And** no data loss for guides already used in reports tied to enrolled students

7. **Downstream consumers — read guide from enrolment**
   - **Given** reports, PDF panel reports, exports, or progress views that show **Guide**
   - **When** data is resolved for a student **in a project context** (`session_id` known)
   - **Then** use `pr_session_students.guide_name` / `guide_emp_id` for that session — **not** `pr_students`
   - **And** update at minimum:
     - `ReportsViewService` (marks/overall matrices, `list_enrolled_students` path)
     - `ReportQueryService` (student master / combined exports)
     - `PanelReportPdfContextBuilder` / `PanelReportPdfService`
     - `ReportsScoresTable.jsx` if it reads `student.guide_name`
   - **Given** a code path with only `student_id` and no session (pure registry list)
   - **Then** do not show guide (registry no longer has it)

8. **Tests and build**
   - **Given** PHPUnit suite
   - **When** complete
   - **Then** tests cover: schema patch adds `program` on students and guide columns on enrolment; registry student create/update with `program`, without guide; registry CSV/import with `program`; import enrolment with guide fields; backfill copies guide to enrolments; reports resolve guide from enrolment
   - **And** update `FakeWpdb`, seed SQL (`tests/sql/01_seed_demo_session.sql`), `StudentRepositoryTest`, `RestStudentsTest`, `RestSessionsTest`, `SessionRepositoryTest`, report tests as needed
   - **And** `composer test` + `npm run build` pass

## Tasks / Subtasks

- [x] **Install:** `ensure_student_program_column()` + `ensure_enrolment_guide_columns()` + `backfill_guide_name_to_enrolments()` + drop `pr_students.guide_name`; update `table_students()` (add `program` before `batch`) and `table_session_students()` DDL; call from `ensure_schema_patches()`
- [x] **StudentRepository + Rest_Students:** add `program` to CRUD, search, import; remove `guide_name`; update tests
- [x] **SessionRepository:** read/write `guide_emp_id`, `guide_name` on `enrol_student`, `import_enrolment`, new `update_guide(...)` or extend update helpers; extend `enrol_student` signature if needed
- [x] **Rest_Sessions:** `list_enrolled_students`, `update_enrolled_student`, `import_enrolment` payload/response shapes
- [x] **Registry UI:** add **Program** before **Batch** in `Registry.jsx`, `StudentForm.jsx`; remove Guide; extend `CsvImportMapper` students optional `program`; update search placeholder; update template asset
- [x] **Wizard Students step:** roster columns + PUT for guide fields; `CsvImportMapper` session-enrol optional guide columns; update session-enrol template
- [x] **Reports/PDF:** switch guide resolution to enrolment for session-scoped queries
- [x] **Tests + build**

## Dev Notes

### Product intent (user request)

> All students currently have reg_no, name, batch, guide_name, panel — but the guide can be different for each project, so remove guide_name and panel from the All Students db table. Also, in the project enrollment, the student should be added with guide_emp_id, guide_name, panel.

> Also, add **Program** in All Students, before **Batch** (global registry field, same pattern as `batch`).

**Clarification from codebase analysis:**

| User said | Actual today |
|-----------|----------------|
| `panel` on All Students DB | **Not** on `pr_students` — only in `students-import-template.csv` as an import column (currently **ignored** by `StudentRepository::import_rows` — only `reg_no`, `name`, `batch`, `guide_name` persist) |
| `guide_name` on All Students | **Yes** — `pr_students.guide_name` + Registry UI + registry CSV |
| `panel` on project enrolment | **Yes** — `pr_session_students.panel_id` + CSV `panel` column on `session-enrol` |
| `guide` on project enrolment | **Missing** — must add `guide_emp_id`, `guide_name` on `pr_session_students` |

This story **removes global guide**, **adds per-project guide fields**, **adds global `program`**, and **cleans registry CSV/UI** so coordinators do not think panel/guide belong on All Students.

### Schema patch — `program` on `pr_students` (implementer)

Add column (existing installs) before guide migration:

```php
// ensure_student_program_column()
program varchar(64) NOT NULL DEFAULT ''  // AFTER name, BEFORE batch in table_students()
```

Fresh-install DDL order in `table_students()`:

```sql
reg_no, name, program, batch, created_at, updated_at
```

Mirror `batch`: optional on create/import; empty string = unset; include in `StudentRepository::$allowed` and search LIKE clause (drop `guide_name` from search).

### Schema patch — enrolment guide (implementer)

Follow `ensure_project_title_columns()` pattern in `Install.php`:

```php
// pr_session_students — after panel_id or project_title
guide_emp_id varchar(64) NOT NULL DEFAULT ''
guide_name varchar(255) NOT NULL DEFAULT ''
```

Backfill (before drop):

```sql
UPDATE {prefix}pr_session_students ss
INNER JOIN {prefix}pr_students s ON s.id = ss.student_id
SET ss.guide_name = s.guide_name
WHERE (ss.guide_name = '' OR ss.guide_name IS NULL)
  AND s.guide_name != '';
```

Then `ALTER TABLE {prefix}pr_students DROP COLUMN guide_name` (guard with `column_exists`).

Update `table_students()` and `table_session_students()` in `Install.php` for fresh installs.

### Repository signatures

`SessionRepository::enrol_student` today:

```157:161:includes/repositories/SessionRepository.php
    public function enrol_student(
        int $session_id,
        int $student_id,
        ?int $panel_id = null,
        ?string $project_title = null
```

Extend with optional `?string $guide_emp_id = null, ?string $guide_name = null` or pass an options array — match project style (prefer explicit optional params for consistency with `project_title`).

`import_enrolment` row shape — extend docblock:

```php
@param list<array{
 *   reg_no: string,
 *   panel?: string,
 *   project_title?: string,
 *   guide_emp_id?: string,
 *   guide_name?: string
 * }> $rows
```

### REST shapes

`list_enrolled_students` — add to each item (sibling to `project_title`):

```php
'guide_emp_id' => trim((string) ($enrolment['guide_emp_id'] ?? '')),
'guide_name' => trim((string) ($enrolment['guide_name'] ?? '')),
```

`update_enrolled_student` — accept `guide_emp_id`, `guide_name` alongside existing `panel_id`, `project_title`.

`format_student_summary` in `Rest_Students` / `Rest_Sessions::format_student_summary` — include `program`; remove `guide_name`.

### UI — CsvImportMapper

**students** — replace optional fields (remove `guide_name`, add `program` before `batch`):

```js
const STUDENT_OPTIONAL = [
	{ key: 'program', label: 'Program' },
	{ key: 'batch', label: 'Batch' },
];
```

**session-enrol** — add optional:

```js
{ key: 'guide_emp_id', label: 'Guide employee ID' },
{ key: 'guide_name', label: 'Guide name' },
```

Keep `panel` required on session-enrol (already is).

### UI — Registry / StudentForm

`Registry.jsx` base columns order:

```js
{ key: 'reg_no', label: 'Reg. no.' },
{ key: 'name', label: 'Name' },
{ key: 'program', label: 'Program' },
{ key: 'batch', label: 'Batch' },
```

`StudentForm.jsx`: same field order; remove `guide_name`.

### Exports (optional alignment)

If `ReportQueryService` student master export lists `batch`, add `program` **before** `batch` in column order for consistency (session-scoped exports that join `pr_students`).

### Reports — critical call sites

| File | Change |
|------|--------|
| `ReportsViewService.php` ~319 | Join enrolment or pass enrolment guide into student row for matrix |
| `ReportQueryService.php` ~108, 121 | Export columns from enrolment per session |
| `PanelReportPdfContextBuilder.php` ~134 | `guide_name` from enrolment for panel PDF students array |
| `ReportsScoresTable.jsx` ~155 | Use enrolment-level `guide_name` from API |
| `PanelReportPdfService.php` | No change if context builder supplies correct field |

Panel report settings `show_guide_name` stays — data source moves to enrolment.

### Search

`StudentRepository::search` currently includes `guide_name` in LIKE — remove that column from the WHERE clause after drop.

### Per-review assignments

**Out of scope:** copying `guide_*` to `pr_review_student_panels` — guide is **project-level** (like session default `project_title`), not per review round. If product later needs per-review guide, follow **3.12** title override pattern in a follow-up story.

### Prerequisites

- Stories **2.1–2.4** (registry), **3.3**, **3.12**, **3.13** (enrolment CSV, project title, registry gate) — shipped; extend their patterns, do not rewrite.

### Do not

- Add guide back to registry as custom field by default
- Break `panel_id` FK semantics — CSV `panel` column still resolves to `pr_panels` via name
- Remove `show_guide_name` PDF setting — only change data source

### Manual test checklist

1. Upgrade DB → `program` column exists; registry table shows Program before Batch.
2. Upgrade DB on site with students that have `guide_name` → verify enrolments backfilled, registry form has no guide.
3. Import registry CSV with `program` and old `guide_name,panel` columns → program stored; guide/panel not stored on student.
4. Import session-enrol CSV with `guide_emp_id`, `guide_name`, `panel` → enrolled row shows all fields.
5. Same student, two projects, different guides → independent values.
6. Reports / panel PDF show guide from project enrolment.

### References

- [Source: includes/Install.php — `table_students`, `table_session_students`, `ensure_project_title_columns`]
- [Source: includes/repositories/StudentRepository.php — `import_rows`, `search`]
- [Source: includes/repositories/SessionRepository.php — `import_enrolment`, `enrol_student`]
- [Source: _bmad-output/implementation/3-12-student-project-title-per-review.md]
- [Source: _bmad-output/implementation/3-13-wizard-students-step-roster-ux.md]
- [Source: _bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md — guide column on PDF]

## Dev Agent Record

### Agent Model Used

(create-story workflow; dev-story: Composer)

### Debug Log References

### Completion Notes List

- Schema: `program` on `pr_students`; `guide_emp_id` / `guide_name` on `pr_session_students`; backfill from registry then drop `pr_students.guide_name`.
- Registry REST/UI/CSV: `program` before `batch`; guide/panel no longer on All Students.
- Enrolment: guide fields on import, REST list/update, wizard roster (guide emp. ID, guide name, panel select, project title).
- Reports: guide resolved from enrolment in `ReportsViewService`, `ReportQueryService` (student master adds Program column).
- Tests: 273 PHPUnit OK; `npm run build` OK.

### File List

- includes/Install.php
- includes/repositories/StudentRepository.php
- includes/repositories/SessionRepository.php
- includes/rest/class-rest-students.php
- includes/rest/class-rest-sessions.php
- includes/services/ReportsViewService.php
- includes/services/ReportQueryService.php
- src/coordinator/components/StudentForm.jsx
- src/coordinator/components/CsvImportMapper.jsx
- src/coordinator/pages/Registry.jsx
- src/coordinator/pages/SessionWizard.jsx
- assets/csv/students-import-template.csv
- assets/csv/session-enrol-template.csv
- tests/FakeWpdb.php
- tests/InstallSchemaPatchTest.php
- tests/StudentRepositoryTest.php
- tests/SessionRepositoryTest.php
- tests/ReportQueryServiceTest.php
- tests/sql/01_seed_demo_session.sql
- build/coordinator.js
- build/coordinator.asset.php

## Change Log

- 2026-05-17: Story 2.5 created — move guide from global registry to per-project enrolment; registry CSV/UI cleanup.
- 2026-05-17: Added **Program** on All Students (`program` column, UI/CSV before Batch).
- 2026-05-17: Implemented story 2.5 — per-project guide on enrolment, registry program field, migrations, UI, reports, tests.
