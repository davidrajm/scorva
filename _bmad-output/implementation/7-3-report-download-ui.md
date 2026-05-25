# Story 7.3: Report download REST and ReportCard gallery

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want a reports page with report cards and paired download buttons,
So that I can export committee deliverables including **flat rubric scores** (ID columns) without a separate format picker.

## Acceptance Criteria

1. **Given** a user with `pr_view_reports` on session reports route **When** they view the reports page **Then** seven `ReportCard` components display (six legacy + **Rubric scores (flat)**) with descriptions **And** each card has side-by-side **Download Excel** and **Download CSV** buttons (UX-DR31).

2. **When** a download is in progress **Then** buttons disable until complete **When** download completes **Then** browser receives correct `Content-Type` and filename.

3. **When** downloading **Rubric scores** **Then** file columns are `Project ID, Review ID, Reg No, Reviewer ID, Rubric ID, Score` (plain CSV; Excel without panel merges) **And** data is sourced from `ReportQueryService::TYPE_RUBRIC_SCORES`.

4. **And** Excel output for student master and marks detail still matches merge semantics on fixture session.

## Tasks / Subtasks

- [x] Add seventh report catalog entry for rubric scores (ReportCard renders from REST list)
- [x] Register `rubric_scores` in REST report types enum
- [x] Wire download routes to new query type (via `ALL_TYPES` validation)
- [x] Run `npm run build` and smoke-test reports page

## Dev Notes

### Prerequisites

- Story 7.2 `TYPE_RUBRIC_SCORES` query.
- Story 7.4 view in database.

**Covers:** FR20, FR21, FR26; UX-DR11, UX-DR31

### References

- [Source: _bmad-output/planning/epics.md — Story 7.3]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Seventh catalog entry in `Rest_Reports::report_catalog()`; coordinator Reports page already maps REST list to `ReportCard` components.
- Session sidebar now links to Reports (and other project views) when on `/session/:id/*` routes.
- Added `RestReportsTest` (7 catalog entries, rubric_scores CSV download); `WP_REST_Response` and `sanitize_title` test stubs.

### File List

- `includes/rest/class-rest-reports.php`
- `src/coordinator/CoordinatorNav.jsx`
- `tests/RestReportsTest.php`
- `tests/bootstrap.php`
