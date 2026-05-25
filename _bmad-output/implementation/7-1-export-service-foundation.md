# Story 7.1: ExportService CSV and XLSX foundation

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **developer**,
I want a shared export pipeline for CSV and styled Excel,
So that all reports use one tested code path.

## Acceptance Criteria

1. **Given** PhpSpreadsheet is installed via Composer **When** `ExportService::to_csv($rows)` is called **Then** valid CSV is returned **When** `ExportService::to_xlsx($rows, $merge_plan, $styles)` is called **Then** valid `.xlsx` is returned with bold header, freeze pane, and numeric format for marks **And** `ExportServiceTest` asserts xlsx validity and expected merge cell counts on fixtures

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Epic 6 scores available for queries.

### Files / patterns
- Composer: `phpoffice/phpspreadsheet`.
- `ExportService::to_csv` / `to_xlsx` with merge plans (§11).
- Six `ReportCard` components; disable buttons during download (UX-DR31).
### Composer
Add PhpSpreadsheet; `ExportServiceTest` with merge count fixtures.

**Covers:** FR20, FR21; NFR9, NFR16

### References

- [Source: _bmad-output/planning/epics.md — Story 7.1]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

### File List
