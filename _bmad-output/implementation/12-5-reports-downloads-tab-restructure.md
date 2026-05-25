# Story 12.5: Reports — URL tabs + canonical Downloads catalog

Status: review

<!-- Epic 12 order: 5 of 5. Depends on 12-1..12-4. Removes legacy seven ReportQueryService downloads from coordinator UI. -->

## Story

As a **project coordinator**,
I want Reports **tabs** and the selected **review round** reflected in the page URL,
So that I can bookmark or share a direct link (e.g. Consolidated view or Rubric marks for Review 2) and stay on the same tab after refresh.

As a **project coordinator**,
I want the Reports **Downloads** tab to list only **committee deliverables** we still use — each with the right format buttons,
So that I am not confused by legacy exports that duplicate the live matrices or outdated schemas.

## Acceptance Criteria

### 1. Reports tabs in URL (`tab` query param)

1. **Given** the coordinator opens Reports at `#/session/{id}/reports`  
   **When** no `tab` query param is present  
   **Then** the active tab is **Rubric marks** (`marks`) — same default as today.

2. **Given** a URL with `?tab={id}` where `{id}` is one of: `marks` | `scores` | `consolidated` | `downloads`  
   **When** the page loads or the user refreshes  
   **Then** that tab is selected and its content loads (marks/scores live view, consolidated table, or downloads grid).

3. **Given** an unknown or empty `tab` value  
   **When** the page loads  
   **Then** fall back to `marks` **without** leaving a junk param in the URL (replace invalid value on first render, same pattern as wizard `step`).

4. **Given** the user clicks a tab in the tablist  
   **When** the tab changes  
   **Then** `setSearchParams` updates `tab` in the URL (HashRouter: `?tab=…` after the hash route).

5. **And** browser **Back/Forward** changes the active tab (read tab from `useSearchParams`, do not keep tab only in `useState`).

6. **And** deep links work for coordinators with `pr_view_reports`, e.g.  
   `#/session/12/reports?tab=consolidated`  
   `#/session/12/reports?tab=downloads`

### 2. Review round in URL (`review` query param) — marks & scores tabs

7. **Given** `tab` is `marks` or `scores`  
   **When** `?review={review_id}` is present and that review exists and is **confirmed**  
   **Then** the review `<select>` matches that id.

8. **Given** `review` is missing, invalid, or not confirmed  
   **When** marks/scores tab is active  
   **Then** keep existing behaviour: auto-select first confirmed review (or empty state if none).

9. **Given** the user changes the review round dropdown on marks or scores  
   **When** the selection changes  
   **Then** update `review` in the URL via `setSearchParams` (preserve `tab`).

10. **And** refreshing on `?tab=marks&review=5` keeps the same review selected and reloads marks-grid / scores-matrix.

11. **And** `review` is **optional** on `consolidated` and `downloads` (ignored there unless implemented for downloads review selector — see AC 14).

### 3. Remove legacy downloads (seven from 7.3)

12. **Given** the Reports Downloads tab today (seven legacy `ReportQueryService` types from 7.3, still in `report_catalog()`)  
    **When** this story ships  
    **Then** these are **removed** from coordinator UI and `Rest_Reports::report_catalog()`:
    - Student master · Marks detail · Review summary · Combined scores · Panel progress · Audit log · Rubric scores (flat)

13. **And** `ReportQueryService::ALL_TYPES` and `download_report()` may remain for internal/scripts/tests but are **not** exposed in the coordinator catalog.

14. **And** `GET /sessions/{id}/reports/download?type=…` for removed types returns **410 Gone** or **400** with clear message (pick one; prefer **410** for deprecated types) — document in tests.

### 4. Canonical Downloads catalog (only these)

| # | Label | Scope | Excel | CSV | PDF | REST / notes |
|---|-------|-------|-------|-----|-----|----------------|
| A | **Panel roster** | Per review | ✓ | ✓ | — | `12-2` `…/reviews/{id}/panel-roster/download` |
| B | **Consolidated student scores** | Per project | ✓ | ✓ | — | `12-3` `…/consolidated-student-scores/download` |
| C | **Offline scoring sheet** | Per review + panel | — | — | ✓ | `12-4` `…/offline-scoring-sheet/pdf` |
| D | **Rubric marks matrix** | Per review | ✓ | ✓ | — | Reuse marks-tab export (7.6) — linked here |
| E | **Overall scores matrix** | Per review | ✓ | ✓ | — | Reuse scores-tab export (7.9) — linked here |

15. **Given** each Excel row in A, B, D, E  
    **When** the user views the Download card  
    **Then** **Download CSV** sits beside **Download Excel** (UX-DR31).

16. **Given** report D or E on Downloads  
    **When** downloading  
    **Then** user picks **review** from selector (confirmed reviews only; roster A may allow all reviews per 12-2).

17. **Given** consolidated export B  
    **When** listed in catalog  
    **Then** `scope` is `session` (project-wide); no review selector on card.

### 5. Downloads UI structure

18. **Given** Downloads tab  
    **When** rendered  
    **Then** sections (headings):
    - **Project-wide** — Consolidated student scores (B)
    - **By review round** — review `<select>` then cards: Panel roster (A), Rubric marks (D), Overall scores (E), Offline PDF (C via `OfflineScoringSheetCard`)

19. **And** each card shows short description + column summary (helper text).

20. **And** in-progress disables buttons; errors use `Notice`.

21. **Given** Downloads tab uses a review selector  
    **When** user changes review on Downloads  
    **Then** optionally sync `?review=` in URL (same param as marks/scores) so `?tab=downloads&review=3` is shareable — **recommended in this story**.

### 6. Table structures (reference — do not change schemas)

#### A — Panel roster — see `12-2-reports-panel-roster-export.md`

#### B — Consolidated student scores — see `12-3-reports-consolidated-student-export.md`

#### C — Offline PDF — see `12-4-reports-offline-scoring-pdf.md`

#### D / E — Match live **Rubric marks** / **Overall scores** tab exports (7.6, 7.9).

### 7. Tests & build

22. **And** `RestReportsTest::test_list_report_types_*` expects **five** catalog entries (A–E keys), not seven/nine legacy keys.

23. **And** test deprecated `download_report` type returns 410/400.

24. **And** no broken coordinator nav links to Reports.

25. **And** `npm run build` passes.

26. **Optional but valuable:** lightweight JS test or manual checklist in Dev Agent Record for `?tab=` + `?review=` refresh behaviour.

## Tasks / Subtasks

- [x] **JS:** `Reports.jsx` — `useSearchParams`; derive `tab` / `review` from URL; `setSearchParams` on tab click and review change; remove local-only `useState('marks')` for tab
- [x] **JS:** Refactor Downloads tab — section layout, review selector, wire A–E; remove `sessionDownloadReports` mapping of legacy keys
- [x] **JS:** Reuse export handlers from marks/scores tabs for D/E on Downloads
- [x] **PHP:** Replace `Rest_Reports::report_catalog()` with five canonical entries; add consolidated catalog entry if missing
- [x] **PHP:** Deprecated types on `download_report()` → 410 Gone
- [x] **PHP:** Comment in `ReportQueryService` — legacy types internal-only
- [x] **Tests:** Update catalog count; smoke panel-roster + consolidated + deprecated download

## Dev Notes

### URL pattern (HashRouter)

Coordinator app uses `HashRouter` (`src/coordinator/App.jsx`). Full examples:

```
#/session/12/reports
#/session/12/reports?tab=scores&review=3
#/session/12/reports?tab=downloads&review=3
#/session/12/reports?tab=consolidated
```

Query string lives **after** the hash path. Use React Router `useSearchParams` — same as:

```27:29:src/coordinator/pages/SessionWizard.jsx
	const [ searchParams, setSearchParams ] = useSearchParams();
	const stepParam = searchParams.get( 'step' );
	const currentStep = STEPS.includes( stepParam ) ? stepParam : 'students';
```

```40:45:src/coordinator/pages/Reports.jsx
const TABS = [
	{ id: 'marks', label: 'Rubric marks' },
	{ id: 'scores', label: 'Overall scores' },
	{ id: 'consolidated', label: 'Consolidated' },
	{ id: 'downloads', label: 'Downloads' },
];
```

**Implementation sketch:**

```javascript
const TAB_IDS = TABS.map( ( t ) => t.id );
const [ searchParams, setSearchParams ] = useSearchParams();
const tabParam = searchParams.get( 'tab' );
const tab = TAB_IDS.includes( tabParam ) ? tabParam : 'marks';

const goToTab = ( id ) => {
	setSearchParams( ( prev ) => {
		const next = new URLSearchParams( prev );
		next.set( 'tab', id );
		return next;
	} );
};
```

On mount, if `tabParam` is invalid, call `setSearchParams` once to normalize (avoid infinite loop — compare before set).

For `review`, after `loadReviews()` resolves, read `searchParams.get('review')`, validate against `confirmedReviews` (marks/scores) or `reviews` (downloads), else default first item.

### Current state (brownfield)

- Tabs use `useState('marks')` only — **lost on refresh** (`Reports.jsx` line 51).
- `report_catalog()` still returns **9** entries: 7 legacy + panel roster + offline PDF (`class-rest-reports.php` ~565–625).
- Downloads tab renders **all** non-review-scoped legacy cards via `sessionDownloadReports` plus review-scoped roster + `OfflineScoringSheetCard`.
- Consolidated workbook download exists at REST `consolidated-student-scores/download` (12-3) but is **not** in catalog yet — add in this story.
- Marks/scores matrix exports are **client + dedicated REST** on live tabs; Downloads should **call the same handlers**, not duplicate logic.

### Epic 12 order (do not reorder)

| Order | Story | Status |
|-------|-------|--------|
| 1 | 12-1 panel context live views | review |
| 2 | 12-2 panel roster export | review |
| 3 | 12-3 consolidated export | review |
| 4 | 12-4 offline PDF | review |
| 5 | **12-5** (this) | ready-for-dev |

### Catalog keys (suggested constants in `ReportsViewService`)

| Key | Constant / note |
|-----|-----------------|
| `panel_roster` | `PANEL_ROSTER_CATALOG_KEY` (exists) |
| `consolidated_student_scores` | Add `CONSOLIDATED_STUDENT_CATALOG_KEY` |
| `offline_scoring_sheet` | `OFFLINE_SCORING_SHEET_CATALOG_KEY` (exists) |
| `marks_matrix` | New — scope `review`, links to existing marks-grid download |
| `scores_matrix` | New — scope `review`, links to existing scores-matrix download |

`ReportCard` already supports `scope: 'review'`, `reviewId`, `reviews`, `onReviewIdChange`. For D/E, either extend `ReportCard` with custom download URLs or add thin wrapper cards that call the same `fetch` helpers as `Reports.jsx` `downloadExcel` / `downloadCsv`.

### Anti-patterns

- Do **not** remove `ReportQueryService` query methods until coordinators confirm no external scripts hit old URLs — UI removal + 410 on REST is enough.
- Do **not** duplicate marks/scores export SQL in new PHP types for D/E.
- Do **not** use `useState` for tab while also reading URL — single source of truth: `searchParams`.
- Do **not** ship partial catalog (legacy + new mixed).

### Regression scope

- Panel-head PDF, panel report page, review freeze/lock, marking grids — unchanged.
- Live tabs (marks, scores, consolidated) behaviour unchanged except URL sync.
- `7-10` empty cells / no `draft` label — unchanged on exports.

### Files to touch

| Area | File |
|------|------|
| Reports page | `src/coordinator/pages/Reports.jsx` |
| Downloads cards | `src/shared/components/ReportCard.jsx` (if needed for matrix URLs) |
| Offline PDF | `src/coordinator/components/OfflineScoringSheetCard.jsx` |
| REST catalog | `includes/rest/class-rest-reports.php` |
| Catalog constants | `includes/services/ReportsViewService.php` |
| Tests | `tests/RestReportsTest.php` |

### References

- [Source: src/coordinator/pages/Reports.jsx]
- [Source: includes/rest/class-rest-reports.php — `report_catalog()`]
- [Source: _bmad-output/implementation/7-3-report-download-ui.md]
- [Source: _bmad-output/implementation/7-5-reports-page-live-views-and-lock.md]
- [Source: _bmad-output/implementation/12-2-reports-panel-roster-export.md]
- [Source: _bmad-output/implementation/12-3-reports-consolidated-student-export.md]
- [Source: _bmad-output/implementation/12-4-reports-offline-scoring-pdf.md]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Reports tabs (`marks`, `scores`, `consolidated`, `downloads`) and review round sync via `useSearchParams` (`?tab=`, `?review=`); invalid `tab` normalized to `marks` on load.
- Downloads tab restructured: **Project-wide** (consolidated student scores) and **By review round** (panel roster, rubric marks matrix, overall scores matrix, offline PDF) with shared review selector and URL sync.
- `Rest_Reports::report_catalog()` returns five canonical entries; legacy `ReportQueryService` types return **410 Gone** on `GET …/reports/{type}/download`.
- `ReportCard` routes matrix and consolidated exports to dedicated REST endpoints (not legacy catalog download).
- `RestReportsTest`: five-entry catalog, 410 deprecated download, consolidated CSV multiline smoke. `npm run build` passes.

**Manual URL checklist:** `#/session/{id}/reports?tab=scores&review={id}` — refresh keeps tab/review; Back/Forward switches tabs; `?tab=downloads&review={id}` shares downloads review selector.

### File List

- src/coordinator/pages/Reports.jsx
- src/coordinator/components/ReportsNav.jsx
- src/coordinator/components/OfflineScoringSheetCard.jsx
- src/shared/components/ReportCard.jsx
- includes/rest/class-rest-reports.php
- includes/services/ReportsViewService.php
- includes/services/ReportQueryService.php
- tests/RestReportsTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php

### Change Log

- 2026-05-18: Story 12-5 — URL-backed Reports tabs/review; canonical five-item downloads catalog; legacy report downloads return 410.
