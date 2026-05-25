# Story 11.2: Panel report settings — report-layout preview (WYSIWYG)

Status: review

<!-- Ultimate context engine analysis completed — coordinator Panel Report settings should mirror PDF page structure so editors see which region they are changing; builds on 11-1 PDF renderer and existing PanelReportSettings.jsx -->

## Story

As a **project coordinator** configuring the Review Report PDF for a project,
I want the **Panel Report** settings screen to look like the printed report layout (letterhead, title block, scores table, signatures),
So that I can see **which portion of the document** I am editing while I change labels, toggles, and letterhead content.

## Background — current vs target

| Today | Target |
|-------|--------|
| Route: `#/session/{id}/settings/panel-report` (`PanelReportSettings.jsx`) | Same route |
| Generic admin form: stacked `FieldRow` sections (Letterhead / Report details / Scores table / Footer) | **Single “document” canvas** styled like the PDF |
| Settings lock banner above form | Unchanged — lock banner stays **above** the document preview |
| PDF output from `PanelReportPdfService::build_panel_body()` | Preview uses **same class names and visual hierarchy** as PDF HTML (not pixel-perfect Dompdf, but unmistakably the same report) |

**Out of scope:** Changing PDF generation logic, REST schema, freeze/unfreeze behaviour, or reviewer `PanelReportPage.jsx` (unless a tiny shared CSS import is extracted).

**Reference URL (dev):** `http://localhost:3000/reviews/#/session/1/settings/panel-report` (hash path; coordinator SPA).

## Acceptance Criteria

### 1. Report-shaped layout shell

1. **Given** a coordinator opens Panel Report settings  
   **When** the page loads (unlocked or frozen)  
   **Then** the editable template appears inside a **document preview** container (e.g. `.pr-panel-report-preview`) with:
   - Centered **letterhead** block (logo + department/school lines)
   - **Report title** (`settings.report.title`, default `Review Report`)
   - **Metadata table** (program / semester when set; placeholder review/panel/reviewer lines for context)
   - **Scores table** mock with column headers driven by `settings.table` toggles and header strings
   - **Reviewer legend** when `show_reviewer_legend` is true
   - **Signatures** section with heading, left reviewer lines, right HoD block

2. **And** the preview uses PDF-aligned CSS class names where possible: `.letterhead`, `.letterhead-title`, `.letterhead-subtitle`, `.report-title`, `.meta-table`, `table.scores`, `.reviewer-legend`, `.sig-section`, `.sig-layout`, `.sig-line` (see `PanelReportPdfService::document_styles()`).

3. **And** the preview is scoped so styles **do not leak** into the rest of the coordinator app (wrapper class + dedicated stylesheet).

### 2. Region-aware editing (WYSIWYG)

4. **Given** the letterhead region  
   **When** the coordinator edits logo, width, department, or school lines  
   **Then** controls live **inside or directly adjacent to** that letterhead block (not only in a separate generic section below the page).

5. **Given** the report title / program / semester  
   **When** edited  
   **Then** values update **in place** in the title and metadata table preview (live, no save required to refresh preview).

6. **Given** a table column toggle or header label  
   **When** changed  
   **Then** the **mock scores table** adds/removes/renames columns immediately (Sr. No., Reg No, Student, Attendance, Project title, Guide, Reviewer 1…3, Final Marks per config).

7. **Given** signature / HoD / panel coordinator / generated datetime options  
   **When** changed  
   **Then** the **signature mock** and footer note update in the bottom region of the preview.

8. **And** each major region has a subtle **region label** for accessibility (visually quiet, e.g. `sr-only` or small muted caption): “Letterhead”, “Report details”, “Scores table”, “Signatures” — so screen-reader users get the same mental model.

### 3. Sample data (static fixture)

9. **Given** no live panel data on the settings page  
   **When** the preview renders  
   **Then** use a **fixed sample fixture** (2–3 students, 2–3 reviewer columns) with realistic placeholder copy, e.g.:
   - Review Number: `Review 1`
   - Panel Name: `Panel A`
   - Reviewers: `Dr. Sample One, Dr. Sample Two`
   - Students: reg nos, names, `P`/`A` attendance, short project titles, guide names

10. **And** sample numeric cells use em-dash or sample scores — **not** loaded from REST (settings page has no panel context).

11. **And** reviewer column headers use `settings.table.reviewer_header_pattern` (default `R{n}` → `R1`, `R2`, …) matching PDF behaviour.

### 4. Settings lock, save, and regression

12. **Given** settings are **frozen**  
    **When** the page renders  
    **Then** the document preview remains visible; all inputs inside it are **disabled** (same as today’s `fieldset disabled`).

13. **Given** Part A/B of story **11-1**  
    **When** this story ships  
    **Then** freeze/unfreeze, save (`PUT sessions/{id}/panel-report-settings`), and PDF download for panel coordinators are **unchanged** (no PHP regressions).

14. **And** `npm run build` passes.

15. **And** existing PHPUnit (`PluginSettingsTest`, `PanelReportPdfTemplateTest`, `PanelHeadTest`) passes without changes unless a shared CSS extract forces a trivial assertion update.

### 5. Responsive behaviour

16. **Given** viewport &lt; `lg` (1024px)  
    **When** the settings page is viewed  
    **Then** the document preview is horizontally scrollable inside a wrapper (`overflow-x-auto` or `TABLE_SCROLL_WRAPPER` pattern) rather than breaking the app shell — **do not** introduce page-level horizontal scroll (align with story **18-1** audit).

17. **And** desktop (primary) layout shows the preview at a readable width (max-width ~A4 proportion, e.g. `max-w-[210mm]` or `max-w-3xl` centered on surface).

### 6. Documentation / SOP

18. **Given** SOP screenshot id `22-panel-report-settings`  
    **When** implementation is complete  
    **Then** re-capture or update screenshot so the SOP shows the new report-layout settings UI (manual or `capture-sop-screenshots` if in automated list).

## Tasks / Subtasks

- [x] **CSS extract** (AC: 1–3, 16–17)
  - [x] Add `assets/css/panel-report-preview.css` (or `src/coordinator/panel-report-preview.css` imported from coordinator entry) with PDF-matching rules from `PanelReportPdfService::document_styles()`, scoped under `.pr-panel-report-preview`
  - [x] Enqueue/import in coordinator bundle; verify no global `table.scores` leakage
- [x] **Preview component** (AC: 1, 3, 9–11)
  - [x] Create `src/coordinator/components/PanelReportSettingsPreview.jsx` (+ `panelReportPreviewFixture.js` for sample students/reviewers)
  - [x] Map `settings` props → letterhead, meta rows, dynamic columns, legend, signatures
- [x] **Refactor settings page** (AC: 4–8, 12)
  - [x] Refactor `PanelReportSettings.jsx`: replace stacked `FieldRow` fieldsets with preview-first layout; embed inputs per region
  - [x] Keep settings lock section + Save + confirm dialogs unchanged
- [x] **Verify** (AC: 13–15, 18)
  - [x] Manual: `/#/session/1/settings/panel-report` — toggle columns, edit headers, see regions update
  - [x] `./vendor/bin/phpunit` and `npm run build`
  - [ ] Update SOP screenshot `22-panel-report-settings` if applicable

## Dev Notes

### Canonical files

| Area | Path |
|------|------|
| Settings page (refactor) | `src/coordinator/pages/PanelReportSettings.jsx` |
| New preview UI | `src/coordinator/components/PanelReportSettingsPreview.jsx` |
| Sample data | `src/coordinator/components/panelReportPreviewFixture.js` |
| Preview CSS | `assets/css/panel-report-preview.css` (import from coordinator `index.js` or existing CSS pipeline) |
| PDF source of truth (read-only) | `includes/services/PanelReportPdfService.php` — `build_panel_body()`, `document_styles()`, `render_*` |
| Settings persistence | `includes/services/SessionPanelReportSettings.php`, `includes/rest/class-rest-session-panel-report-settings.php` |
| Prior story | `_bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md` |

### PDF section order (must match preview)

```
letterhead → report title + meta-table → scores table → reviewer legend → signatures
```

From `PanelReportPdfService::build_panel_body()`:

```php
return $letterhead . $metadata . $table . $legend . $signatures;
```

### Settings shape (already in UI state)

Do not rename keys. `defaultSettings()` in `PanelReportSettings.jsx` defines:

- `letterhead.blocks` — image + text blocks
- `report` — `title`, `program_name`, `semester`, show flags
- `table` — column show toggles, `*_column_header`, `reviewer_header_pattern`, `show_reviewer_legend`, `project_title_field_key`
- `footer.show_generated_datetime`
- `signatures` — `show_panel_coordinator_line`, `hod.enabled|label|name`

### Implementation approach (recommended)

1. **Shared visual vocabulary, not shared PHP→React pipeline** — Duplicating layout in JSX is acceptable; extracting PHP HTML to React is not required. Keep preview maintenance cost low by copying **class names and spacing** from `document_styles()`.

2. **Inline editing pattern** — Prefer controls embedded in each region (e.g. column header inputs in `<th>`, letterhead text inputs under styled headings) over a separate “form below preview” split. Optional: faint dashed outline on hover/focus-within for “editable region”.

3. **Column config** — Reuse `TABLE_COLUMNS` and `ColumnConfigRow` logic but render toggles/header inputs **in the mock table header row** for enabled columns; disabled columns hidden from header and body.

4. **Logo** — Show `logoPreview` in letterhead; file input + width control under logo in letterhead zone.

5. **Do not** call PDF endpoint for preview — no Dompdf in browser.

### Anti-patterns (prevent LLM mistakes)

- Do **not** change `PanelReportPdfService` unless extracting CSS constants to a comment-only reference.
- Do **not** remove freeze/save REST handlers.
- Do **not** use live session panel data on this route (no new REST endpoint).
- Do **not** break `TABLE_COLUMNS` `alwaysOn` for Sr. No. and Student name.
- Do **not** apply `table.scores` styles globally without `.pr-panel-report-preview` scope.

### Testing

| Layer | Action |
|-------|--------|
| Manual | Load settings route; edit each region; freeze → inputs disabled; save → reload persists |
| PHPUnit | Run full suite — expect green |
| JS unit | Optional: shallow render preview with varied `settings.table` — only if quick; not mandatory |
| E2E | No new E2E required; SOP screenshot optional |

### Previous story intelligence (11-1)

- PDF uses Times-like serif, 1 pt black borders, gray header background `#f5f5f5` — preview should mirror.
- Attendance column default header `At` in settings; cells `P`/`A` in exports — preview fixture should show short attendance.
- Story 11-1 moved template config to **per-project** coordinator settings (`SessionPanelReportSettings`), not WP Admin-only.
- Reviewer on-screen report (`PanelReportPage`) already uses `ReportsScoresTable` for WYSIWYG table — **this story is coordinator settings only**.

### Project structure

- Coordinator SPA: `src/coordinator/`, routes in `App.jsx` path `/session/:id/settings/panel-report`
- Shared components: `src/shared/components/`
- Design tokens: existing Tailwind semantic classes (`border-border`, `bg-surface`, `text-text-muted`) for chrome **around** the preview; **inside** preview use PDF CSS file

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Replaced stacked FieldRow form with WYSIWYG `PanelReportSettingsPreview`: letterhead, report title/meta, scores table (inline column toggles/headers), legend, signatures — all live-updating from `settings` state.
- Added `assets/css/panel-report-preview.css` scoped under `.pr-panel-report-preview` (PDF class names; no global `table.scores` leakage). Imported from `src/coordinator/index.js`.
- Sample fixture: 3 students, 3 reviewer columns (`R{n}` pattern); horizontal scroll via `TABLE_SCROLL_WRAPPER` below lg.
- `npm run build` and `./vendor/bin/phpunit` (347 tests) pass. No PHP changes.
- SOP screenshot `22-panel-report-settings` not re-captured in this session — run `capture-sop-screenshots` or manual capture when validating UI.

### File List

- assets/css/panel-report-preview.css (new)
- src/coordinator/index.js
- src/coordinator/pages/PanelReportSettings.jsx
- src/coordinator/components/PanelReportSettingsPreview.jsx (new)
- src/coordinator/components/panelReportPreviewFixture.js (new)
- src/coordinator/components/panelReportTableConfig.js (new)
- build/coordinator.js, build/coordinator.css, build/coordinator-rtl.css, build/coordinator.asset.php

### Change Log

- 2026-05-24: Story 11-2 — panel report settings WYSIWYG document preview (coordinator SPA).

## References

- [Source: src/coordinator/pages/PanelReportSettings.jsx]
- [Source: includes/services/PanelReportPdfService.php — `document_styles`, `build_panel_body`]
- [Source: _bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md]
- [Source: tests/e2e/sop-screenshot-manifest.ts — `22-panel-report-settings`]
- [Source: _bmad-output/implementation/18-1-mobile-first-css-media-query-audit.md — table/preview horizontal scroll]
