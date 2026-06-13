# Story 25.1: Program catalog — normalized program names

Status: draft

## Story

As a **coordinator managing the student registry and reports**,
I want **programs to come from a managed catalog instead of free text**,
So that "CSE", "Cse" and "cse" stop creating duplicate program buckets in filters, reports, and exports.

## Background — current behaviour (do not guess)

Confirmed: `students.program` is a free-text varchar. The REST layer only trims (`class-rest-students.php:542`), CSV import stores values verbatim, and no case-insensitive matching or canonicalization exists anywhere (`StudentRepository`, `StudentEnrolmentService`). Every distinct casing/spacing becomes a distinct program in report filters and consolidated exports.

## Acceptance Criteria

1. **Given** a new `pr_programs` table (id, name, optional code, timestamps; unique on case-insensitive name)
   **When** dbDelta runs via `Install`
   **Then** the schema is versioned like existing tables (`pr_db_version` bump) and covered by `InstallSchemaPatchTest`-style tests

2. **Given** program CRUD for coordinators
   **When** managing the catalog (list / add / rename / merge)
   **Then** REST endpoints exist under the plugin namespace with `pr_manage_students`-level capability, and renaming/merging updates affected student rows in one transaction with an audit entry

3. **Given** student create/edit (UI + REST)
   **Then** the program field becomes a select/autocomplete backed by the catalog; submitting a value that case-insensitively matches an existing program resolves to the canonical name; a genuinely new value either requires explicit "add to catalog" or is rejected — choose one behavior and apply it consistently in UI and REST

4. **Given** CSV student import
   **Then** program values are matched case-insensitively against the catalog; unmatched values are reported per-row (consistent with existing import error reporting) or auto-created behind an explicit import option

5. **Given** existing data
   **When** the migration runs
   **Then** distinct existing `students.program` values are seeded into the catalog with case-insensitive de-duplication (first-seen casing wins; coordinator can rename afterwards) and student rows are rewritten to canonical names

6. **Given** report filters and consolidated exports
   **Then** program dropdowns read from the catalog and previously-duplicate buckets collapse after migration

## Tasks / Subtasks

- [ ] Schema + `Install` patch + tests
- [ ] `ProgramRepository` + REST controller (list/create/rename/merge), audit logging
- [ ] Registry UI: program select/autocomplete in `StudentForm.jsx`, `SessionWizard.jsx` add-student flow
- [ ] CSV import matching in the student import service + error rows
- [ ] One-time migration (seed + rewrite) keyed off `pr_db_version`
- [ ] Update report filter sources; PHPUnit + Playwright coverage

## Dev Notes

### Risks / edge cases

- Merge must also touch any cached/derived report rows that store program text.
- Decide whether `batch` deserves the same treatment later — out of scope here, but design the repository so a `batches` sibling is easy.

### Out of scope

- Department catalog, academic-calendar validation (later stories)
