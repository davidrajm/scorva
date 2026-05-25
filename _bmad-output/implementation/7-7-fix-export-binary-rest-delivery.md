# Story 7.7: Fix report export binary delivery (Excel + CSV)

Status: in-progress

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want Excel and CSV report downloads to open correctly in Excel and other spreadsheet tools,
So that committee deliverables are usable without manual repair or re-export.

## Acceptance Criteria

### 1. Root cause — REST must not JSON-wrap file bodies

1. **Given** any report download endpoint returns binary or plain-text file content  
   **When** the HTTP response is sent to the browser  
   **Then** the response body is **raw bytes / raw text** with the declared `Content-Type`  
   **And** the body is **not** wrapped in a JSON envelope (`application/json` with quoted string, `{ "data": ... }`, etc.).

2. **Documented root cause (do not re-litigate in implementation):**  
   `Rest_Reports::download_report()` and `marks_grid_download()` (and `Rest_Panel_Reports::download_pdf()`) return `new WP_REST_Response($body, 200)` where `$body` is XLSX/CSV/PDF bytes. WordPress REST **always JSON-encodes** `WP_REST_Response::get_data()` in `rest_pre_echo_response` unless `rest_pre_serve_request` short-circuits. That corrupts ZIP-based `.xlsx` (Excel: “file format or extension is not valid”) and turns multi-line CSV into a single JSON string line (Excel: one row).

3. **Given** `ExportService::to_xlsx()` / `to_csv()` unit tests  
   **When** run in isolation  
   **Then** they already pass — **do not rewrite ExportService** unless a separate CSV line-ending issue is proven after REST fix.

### 2. Affected endpoints (all must be fixed)

4. **Report gallery (seven types × two formats):**  
   `GET /project-reviews/v1/sessions/{id}/reports/{type}/download?format=csv|xlsx`  
   — `Rest_Reports::download_report()`

5. **Rubric marks matrix Excel:**  
   `GET /project-reviews/v1/sessions/{session_id}/reviews/{review_id}/marks-grid/download?format=xlsx&layout=…`  
   — `Rest_Reports::marks_grid_download()`

6. **Panel head PDF (same anti-pattern):**  
   `GET /project-reviews/v1/reviewer/panel-reports/{session_id}/{review_id}/{panel_id}/pdf`  
   — `Rest_Panel_Reports::download_pdf()`  
   **And** apply the **same** binary-serve helper so PDF downloads do not regress.

### 3. Implementation pattern (required)

7. **When** implementing the fix  
   **Then** add a small shared helper (suggested: `includes/rest/class-rest-binary-response.php` or method on `Rest_Bootstrap`) that:
   - Builds `WP_REST_Response` with file body + `Content-Type` + `Content-Disposition`
   - Sets a **private marker** on the response (e.g. `$response->header('X-PR-Serve-Raw', '1')` or a namespaced flag in response data metadata WordPress preserves)
   - Registers **one** `rest_pre_serve_request` filter (priority 10) that:
     - Detects marked responses
     - Sends status + headers
     - `echo` raw body (string bytes, not `wp_json_encode`)
     - Returns `true` to skip default JSON echo

8. **And** register the filter once from `Rest_Bootstrap::register_routes()` or plugin bootstrap — **not** per-route duplicate filters.

9. **And** do **not** add SheetJS or client-side XLSX generation; keep server PhpSpreadsheet path (NFR9).

### 4. Verification — HTTP-level, not only `get_data()`

10. **Given** PHPUnit fixtures with marks and a session  
    **When** `download_report` / `marks_grid_download` is exercised through **full REST dispatch** (or a test helper that runs `rest_pre_serve_request` on the prepared response)  
    **Then** XLSX body starts with ZIP magic `PK` (bytes `50 4B`)  
    **And** CSV body contains **at least two** newline-separated records for a fixture with ≥2 data rows (count `substr_count($body, "\n")` ≥ 2 or parse with `str_getcsv` per line).

11. **Given** a downloaded marks rubric xlsx from the user scenario  
    **When** opened in Excel  
    **Then** file opens without format error  
    **And** grouped headers / data rows match on-screen matrix (smoke: non-empty sheet, header row present).

12. **And** existing `ExportServiceTest` and `RestReportsTest` cases that only assert `get_data()` remain; **add new** tests for raw HTTP body semantics (extend `RestReportsTest`).

### 5. Client-side CSV (marks matrix tab)

13. **Given** coordinator uses **Download CSV** on Rubric marks tab (client `rowsToCsv` in `reportsMarksMatrixUtils.js`)  
    **When** opened in Excel on Windows  
    **Then** multiple rows appear (header row 1, header row 2, data rows)  
    **If** Excel still shows one row after server fix, **Then** emit CSV with `\r\n` line endings in `rowsToCsv` (Blob unchanged) — only if still broken after AC §1–12.

### 6. Regression

14. **And** JSON REST endpoints (`marks-grid`, `scores-matrix`, report catalog list) still return `application/json` unchanged.  
15. **And** `./vendor/bin/phpunit` + `npm run build` green.

## Tasks / Subtasks

- [x] **Helper:** `Rest_Binary_Response` (or equivalent) + single `rest_pre_serve_request` filter
- [x] **Wire:** `Rest_Reports::download_report`, `marks_grid_download`, `Rest_Panel_Reports::download_pdf`
- [x] **Tests:** `RestReportsTest` — XLSX magic bytes + CSV multi-line via serve path; optional PDF `%PDF` prefix test
- [ ] **Smoke:** Manual download one ReportCard xlsx + csv + marks matrix xlsx from coordinator UI
- [x] Run `./vendor/bin/phpunit` and `npm run build`

## Dev Notes

### User report (2026-05-17)

- Excel error for `winter-25-26-project-01-reviews_review-1_marks_rubric.xlsx`: invalid format / corrupted — **marks grid download** filename pattern from `ReportsViewService::marks_grid_export()`.
- **All** Excel downloads affected → points to shared REST delivery, not PhpSpreadsheet generation.
- CSVs “all with one row” → classic symptom of JSON-wrapped string body or literal `\n` in a single line; fix REST first.

### Why unit tests missed this

| Test | What it checks | Gap |
|------|----------------|-----|
| `ExportServiceTest::test_to_xlsx_is_valid` | Writes `get_data()` to disk | Never hits REST JSON encoder |
| `RestReportsTest::test_marks_grid_download_xlsx_*` | `$response->get_data()` non-empty | Same — no `rest_pre_echo_response` |
| `RestReportsTest::test_download_rubric_scores_csv_*` | String contains `Project ID` | Does not assert newline row count |

### Reference implementation sketch

```php
// includes/rest/class-rest-binary-response.php
final class Rest_Binary_Response {
    public const SERVE_RAW_HEADER = 'X-PR-Serve-Raw';

    public static function register(): void {
        add_filter('rest_pre_serve_request', [self::class, 'maybe_serve_raw'], 10, 4);
    }

    public static function from_body(
        string $body,
        string $content_type,
        string $filename
    ): \WP_REST_Response {
        $response = new \WP_REST_Response($body, 200);
        $response->header('Content-Type', $content_type);
        $response->header(
            'Content-Disposition',
            'attachment; filename="' . sanitize_file_name($filename) . '"'
        );
        $response->header(self::SERVE_RAW_HEADER, '1');
        return $response;
    }

    public static function maybe_serve_raw(
        bool $served,
        $result,
        \WP_REST_Request $request,
        \WP_REST_Server $server
    ): bool {
        if ($served || !($result instanceof \WP_REST_Response)) {
            return $served;
        }
        if (($result->get_headers()[self::SERVE_RAW_HEADER] ?? '') !== '1') {
            return $served;
        }
        $data = $result->get_data();
        if (!is_string($data)) {
            return $served;
        }
        status_header($result->get_status());
        foreach ($result->get_headers() as $key => $value) {
            if ($key === self::SERVE_RAW_HEADER) {
                continue;
            }
            header($key . ': ' . $value);
        }
        echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary
        return true;
    }
}
```

Refactor `Rest_Reports` / `Rest_Panel_Reports` to return `Rest_Binary_Response::from_body(...)` instead of hand-rolled `WP_REST_Response`.

### Files to touch

| File | Change |
|------|--------|
| `includes/rest/class-rest-binary-response.php` | **New** helper + filter |
| `includes/rest/class-rest-bootstrap.php` | Call `Rest_Binary_Response::register()` once |
| `includes/rest/class-rest-reports.php` | Use helper for download routes |
| `includes/rest/class-rest-panel-reports.php` | Use helper for PDF |
| `tests/RestReportsTest.php` | HTTP/raw body assertions |
| `tests/bootstrap.php` | Stub `rest_pre_serve_request` / server if needed |
| `src/coordinator/components/reportsMarksMatrixUtils.js` | Only if `\r\n` still needed (AC §13) |

### Do NOT

- Reimplement exports in JavaScript (except existing client CSV blob).
- Change `ReportQueryService` row shapes or merge plans.
- Add new report types or UI.

### Previous story intelligence (Epic 7)

- **7.1** — `ExportService` is correct; PhpSpreadsheet 2.x via Composer.
- **7.3** — `ReportCard.jsx` uses `fetch` + `response.blob()`; will work once server sends raw body + correct `Content-Type`.
- **7.6** — Marks matrix xlsx via `marks-grid/download`; client CSV via `rowsToCsv` (separate from REST).

### Architecture compliance

- NFR9: PhpSpreadsheet server-side; CSV same pipeline.
- NFR16: Extend PHPUnit with **serve-path** export tests.
- FR20 / FR21: Deliverable is **usable files**, not just generated strings in PHP memory.

### References

- [Source: includes/rest/class-rest-reports.php — download_report, marks_grid_download]
- [Source: includes/services/ExportService.php]
- [Source: src/shared/components/ReportCard.jsx]
- [Source: src/coordinator/pages/Reports.jsx — marks grid Excel fetch]
- [Source: _bmad-output/implementation/7-3-report-download-ui.md]
- [Source: _bmad-output/implementation/7-6-reports-marks-matrix-layout-sort-export.md]
- [WordPress: `rest_pre_serve_request` filter — serve non-JSON REST responses]

## Dev Agent Record

### Agent Model Used

Composer (create-story); Composer (dev-story)

### Debug Log References

- PHPUnit `header()` warnings under CLI resolved with `headers_sent()` guard before `status_header` / `header` in `maybe_serve_raw` (body echo unchanged).

### Completion Notes List

- Added `Rest_Binary_Response` with `X-PR-Serve-Raw` marker and single `rest_pre_serve_request` filter; registered once from `Rest_Bootstrap::register_routes()`.
- Refactored `Rest_Reports::download_report`, `marks_grid_download`, and `Rest_Panel_Reports::download_pdf` to return `Rest_Binary_Response::from_body()`.
- Extended `RestReportsTest` with serve-path assertions: CSV multi-line body, XLSX `PK` magic, PDF `%PDF` prefix.
- Test bootstrap: `apply_filters`, `status_header`, `sanitize_file_name`, `WP_REST_Server` stubs.
- `./vendor/bin/phpunit` (197 tests) and `npm run build` green. Client `rowsToCsv` unchanged (AC §13 — server REST fix only).
- **Pending:** Manual coordinator UI smoke (ReportCard xlsx/csv + marks matrix xlsx open in Excel).

### File List

- includes/rest/class-rest-binary-response.php (new)
- includes/rest/class-rest-bootstrap.php
- includes/rest/class-rest-reports.php
- includes/rest/class-rest-panel-reports.php
- tests/bootstrap.php
- tests/RestReportsTest.php

### Change Log

- 2026-05-17: Fix REST JSON wrapping of binary export bodies (xlsx/csv/pdf) via `rest_pre_serve_request` raw serve helper.
