# Story 12.4: Reports — offline scoring sheet PDF (per panel, per review)

Status: review

<!-- Depends on 11-1 PDF stack. Epic 12 order: 4 of 5. Baseline offline PDF shipped 2026-05-18; this story adds per-reviewer sheets + Overall Review Report layout. -->

## PDF table layouts (spec)

Each **panel** in the review-level download is a **section** (page break before the next panel). Within one panel, pages render **in reviewer order**, then the **Overall Review Report** last.

Configurable identity columns (Sr. No., Reg no, Student, Attendance, Project title, Guide) follow the same toggles as the signed **Review Report** (`panel_report_pdf` template). Cells are **blank** for handwriting — no `—`, no `0`, no draft labels.

### A. Per-reviewer scoring sheet (one page per reviewer, before overall)

One full page per assigned reviewer on the panel. Header block matches Review Report metadata (letterhead, Review Number, Panel Name, etc.). Report title line identifies the reviewer, e.g. **Reviewer 1 — Scoring Sheet** (ordinal from roster order).

| Sr. No. | Reg no | Student | At | Project title | Guide | Score |
|--------:|--------|---------|----|---------------|-------|------:|
| 1 | … | … | P | … | … | *(blank)* |
| 2 | … | … | P | … | … | *(blank)* |
| … | | | | | | |

| Column | Width / notes |
|--------|----------------|
| Identity columns | Same as 11.1 / current `PanelReportPdfService` |
| **Score** | Single reviewer-total column; **min-width `4em`**; right-aligned; **empty cells** |

**Signatures (per-reviewer page only):**

| Left | Right |
|------|-------|
| **One** line: matching **Reviewer N** (name from roster if available) | HoD block per template (unchanged) |

Do **not** list other reviewers’ signature lines on this page. Do **not** show the reviewer legend mapping all ordinals (optional: omit legend entirely on per-reviewer pages).

---

### B. Overall Review Report (last page per panel)

Same letterhead and metadata block as 11.1. Centered title: **Overall Review Report** (not generic “Review Report” on this page only).

| Sr. No. | Reg no | Student | At | Project title | Guide | R1 | R2 | … | R*n* | Overall score |
|--------:|--------|---------|----|---------------|-------|---:|---:|---|-----:|----------------:|
| 1 | … | … | P | … | … | | | | | |
| 2 | … | … | A | … | … | | | | | |
| … | | | | | | | | | | |

| Column | Width / notes |
|--------|----------------|
| **R1 … R*n*** | Ordinal headers (`R{n}` or template `reviewer_header_pattern`); **min-width `4em` each**; blank cells |
| **Overall score** | Combined / weighted review total column (same meaning as signed **Final Marks**); header label **Overall score** on offline sheets; **min-width `4em`**; blank cells |
| Totals row | **Omit** (no blank total row at bottom) |

**Signatures (overall page):**

| Left | Right |
|------|-------|
| Panel coordinator line (if configured and not duplicated) + **Reviewer 1 … Reviewer R** (dedupe coordinator = reviewer per 11.1) | HoD block per template |
| Reviewer legend below table **allowed** (maps R1… to names) | |

---

### C. Document order (one review PDF)

Example: Review with Panel A (3 reviewers) and Panel B (2 reviewers).

```
Panel A — Reviewer 1 scoring sheet   [page]
Panel A — Reviewer 2 scoring sheet   [page]
Panel A — Reviewer 3 scoring sheet   [page]
Panel A — Overall Review Report      [page]
--- page break ---
Panel B — Reviewer 1 scoring sheet   [page]
Panel B — Reviewer 2 scoring sheet   [page]
Panel B — Overall Review Report      [page]
```

Signed panel-head **Review Report** PDF (11.1) is **unchanged** — still one table with scores + **Final Marks** and existing signature rules.

---

## Story

As a **project coordinator**,
I want to download **blank scoring sheet PDFs** for each panel and review round — per-reviewer sheets plus one **Overall Review Report** per panel,
So that each reviewer can score on paper with only their column, and coordinators can collect combined totals on the overall sheet before data entry.

## Acceptance Criteria

### 1. Scope & document structure

1. **Given** a confirmed review with panels that have enrolled students  
   **When** the coordinator downloads **Offline scoring sheet (PDF)**  
   **Then** one PDF is generated for the whole review.

2. **And** for **each panel**, pages appear in order: **one scoring sheet per reviewer** (roster order), then **one Overall Review Report** page for that panel.

3. **And** panels are separated by a **page break** (same as current multi-panel behaviour).

### 2. Per-reviewer sheets (table A)

4. **Given** a per-reviewer page  
   **When** the scores table renders  
   **Then** it includes the same configurable identity columns as 11.1 (per settings).

5. **And** exactly **one** score column (**Score** header or template-equivalent) with **blank** cells.

6. **And** the score column has **minimum width 4em** (CSS on `.col-reviewer` / score columns).

7. **And** the signature block lists **only** that reviewer’s line (plus HoD on the right when enabled) — no other reviewer signature lines.

8. **And** the page title/subtitle identifies the reviewer ordinal (e.g. **Reviewer 1 — Scoring Sheet**).

### 3. Overall Review Report (table B)

9. **Given** the last page for a panel in the offline PDF  
   **When** it renders  
   **Then** the report title is **Overall Review Report**.

10. **And** the table has identity columns plus **Reviewer 1 … Reviewer R** columns (ordinals, not names in headers) and an **Overall score** column — all cells **blank**.

11. **And** each reviewer score column and the Overall score column use **minimum width 4em**.

12. **And** there is **no** totals/summary row at the bottom.

13. **And** the signature section matches the full panel pattern (all reviewer lines + coordinator dedupe per 11.1); reviewer legend may appear on this page only.

### 4. Coordinator access & reuse

14. **Given** `pr_view_reports` on the project  
    **When** `GET /sessions/{session_id}/reviews/{review_id}/offline-scoring-sheet/pdf`  
    **Then** response is `application/pdf` via `Rest_Binary_Response`.

15. **And** panel-head-only restriction from signed Review Report **does not** apply.

16. **Given** `PanelReportPdfContextBuilder`  
    **When** building offline contexts  
    **Then** support distinct sheet kinds: `offline_reviewer` (single reviewer) and `offline_overall` (multi-reviewer + overall column), reusing `PluginSettings::panel_report_pdf()` toggles.

17. **And** signed panel report PDF (`MODE_SIGNED`) does **not** regress (scores, **Final Marks**, single-page layout).

### 5. Downloads tab (12-5)

18. **And** Reports **Downloads** lists **Offline scoring sheets** with review selector only (one PDF per review) — no UI change required if catalog already wired.

### 6. Tests

19. **And** PHPUnit asserts per-reviewer HTML: one score header, blank cells, single signature label for that ordinal.  
20. **And** overall page HTML: **Overall Review Report**, R1…Rn + **Overall score** headers, `min-width: 4em` (or equivalent class), no numeric marks.  
21. **And** multi-panel order: reviewer pages → overall page per panel, then next panel.  
22. **And** signed report regression tests still pass.

## Tasks / Subtasks

### Shipped (baseline 2026-05-18)

- [x] **PHP:** `MODE_OFFLINE_SCORING`, `render_scores=false`, blank reviewer cells
- [x] **REST:** review-level `.../offline-scoring-sheet/pdf`
- [x] **UI:** `OfflineScoringSheetCard` + catalog key
- [x] **Tests:** offline blank cells, multi-panel page breaks

### Remaining (this refinement)

- [x] **PHP:** `PanelReportPdfService` — emit per-reviewer + overall sheet sequence per panel (`offline_reviewer` / `offline_overall` contexts)
- [x] **PHP:** CSS `min-width: 4em` on reviewer and overall score columns
- [x] **PHP:** Overall offline page — **Overall score** column (blank); title **Overall Review Report**
- [x] **PHP:** Per-reviewer page — single **Score** column; filtered signature lines
- [x] **PHP:** `render_offline_scoring_multi()` — expand each panel into N+1 HTML sections with page breaks
- [x] **Tests:** update `PanelReportPdfTemplateTest` for new layouts; keep signed + REST smoke tests green

## Dev Notes

### Difference from 11.1 panel report

| Feature | Review Report (11.1) | Offline sheets (12.4) |
|---------|----------------------|------------------------|
| Pages per panel | 1 | R reviewer sheets + 1 overall |
| Audience | Panel coordinator sign-off | Coordinator printing for field use |
| Score cells | Submitted/frozen totals | Blank |
| Combined column | **Final Marks** (populated) | **Overall score** (blank) on overall page only |
| Signatures | All reviewers | One reviewer per sheet; all on overall page |

### Implementation pointers

- **Current code:** `render_offline_scoring_multi()` builds one `build_panel_body()` per panel with **all** reviewer columns — replace with a loop: `foreach ($reviewers as $r) { build_reviewer_sheet($context, $r); }` then `build_overall_sheet($context)`.
- **Context flags:** extend builder with e.g. `sheet_kind` = `offline_reviewer` | `offline_overall` | `signed`; `active_reviewer_ordinal` for reviewer sheets.
- **Column builder:** `build_score_columns()` — branch on sheet kind: one `col-reviewer` vs all reviewers + `col-overall` (offline overall only).
- **Signatures:** pass filtered `signature_lines` in context for reviewer sheets (single left line).
- **CSS:** add e.g. `table.scores th.col-reviewer, table.scores td.col-reviewer, table.scores th.col-overall, table.scores td.col-overall { min-width: 4em; }` and stop relying on `col-shrink` alone for score columns on offline sheets.

### Out of scope

- Rubric criterion rows on paper (reviewer **totals** only — same as 11.1).
- Populated overall score on offline PDF (always blank).

### References

- [Source: includes/services/PanelReportPdfService.php]
- [Source: includes/services/PanelReportPdfContextBuilder.php]
- [Source: includes/services/PanelReportService.php]
- [Source: _bmad-output/implementation/11-1-panel-head-reports-pdf-freeze.md]

## Dev Agent Record

### Agent Model Used

Composer

### Completion Notes List

**Baseline (shipped):**

- `PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING` with `render_scores=false`; blank reviewer cells; no Final Marks column.
- `PanelReportService::generate_offline_scoring_pdf_for_review()`; REST review-level PDF; `OfflineScoringSheetCard`.
- Multi-panel PDF with CSS page breaks between panels.

**Refinement (2026-05-18):**

- `PanelReportPdfContextBuilder` sheet kinds: `offline_reviewer` (single **Score** column, one signature line, title `Reviewer N — Scoring Sheet`) and `offline_overall` (**R1…Rn** + blank **Overall score**, title **Overall Review Report**, full signatures + legend).
- `render_offline_scoring_multi()` expands each panel to R reviewer pages + 1 overall page; single-panel offline download uses the same path.
- CSS `min-width: 4em` on `.col-reviewer` / `.col-overall`; signed `MODE_SIGNED` layout unchanged.
- PHPUnit: per-reviewer/overall HTML assertions, multi-panel title order, signed regression; full suite 290 tests green.

### File List

- includes/services/PanelReportPdfContextBuilder.php
- includes/services/PanelReportPdfService.php
- includes/services/PanelReportService.php
- includes/rest/class-rest-reports.php
- src/coordinator/components/OfflineScoringSheetCard.jsx
- tests/PanelReportPdfTemplateTest.php
- tests/RestReportsTest.php

## Change Log

- 2026-05-18: Offline scoring sheet PDF (coordinator REST, builder offline mode, Downloads card, tests).
- 2026-05-18: Story refinement — per-reviewer scoring sheets, Overall Review Report page, 4em score columns, Overall score column; table layouts documented at top.
- 2026-05-18: Implemented refinement — sheet kinds, multi-page offline PDF per panel, tests updated.
