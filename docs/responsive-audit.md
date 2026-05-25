# Responsive CSS audit — Project Reviews plugin

**Story:** 18-1-mobile-first-css-media-query-audit  
**Date:** 2026-05-24  
**Policy:** NFR11 — desktop (1024px+) remains the **primary** product target; this audit aligns **cascade direction** (mobile-first CSS) without changing desktop layout intent.

## Canonical breakpoints (UX spec)

| Token | Min width | Usage |
|-------|-----------|--------|
| (base) | 0 | Single column, drawer sidebar, stacked cards |
| `sm` | 640px | Padding, two-column forms |
| `md` | 768px | 2-col dashboard cards, assignment grids |
| `lg` | 1024px | Persistent coordinator sidebar, marking grid table, 3-col dashboard |
| `xl` | 1280px | Max content width centered (`--pr-layout-content-max-width`) |

---

## Custom `@media` — `assets/css/app-shell.css`

| Line (post-refactor) | Query | Purpose | Classification |
|----------------------|-------|---------|----------------|
| ~519 | `min-width: 1024px` | Hide hamburger; restore in-flow coordinator sidebar; hide drawer backdrop; show collapse toolbar | **OK** — mobile-first enhancement block |

**Removed:** `@media (max-width: 1023px)` drawer block — behaviour moved to unprefixed base rules; desktop restored inside `min-width: 1024px`.

**Unprefixed mobile defaults (coordinator):** hamburger `display: inline-flex`; off-canvas drawer sidebar; backdrop; toolbar hidden in drawer mode.

**Reviewer / landing:** `#pr-root[data-app="reviewer"]` / `landing` body layout rules unchanged; no coordinator drawer selectors applied.

---

## Tailwind responsive prefixes — `src/**/*.{jsx,js}`

| Prefix | Occurrences (approx.) | Notes |
|--------|----------------------|--------|
| `sm:` | 46 | Mobile-first enhancements (padding, flex direction, grid cols) |
| `md:` | 5 | 2-col cards/grids from 768px |
| `lg:` | 6 | Persistent table layout, 3-col dashboard, dual marking grid |
| `xl:` | 0 | None in source |

**`max-sm:` / `max-md:` / `max-lg:` / `max-xl:`:** **0** — **OK**

**Custom `@media (max-width: …)` in `src/`:** **0** — **OK**

---

## Dual layout pattern (`hidden` + breakpoint)

| File | Pattern | Classification |
|------|---------|----------------|
| `src/reviewer/components/MarkingGrid.jsx` | `lg:hidden` (cards) / `hidden lg:block` (table) | **OK** — approved story 5.11; do not collapse to wide table on mobile |

---

## Table viewports & horizontal scroll

| Location | Mechanism | Classification |
|----------|-----------|----------------|
| `src/shared/tableStyles.js` | `TABLE_SCROLL_WRAPPER`, `TABLE_DATA_VIEWPORT` | **OK** — intentional inner scroll (stories 1-9, 1-10, 1-11) |
| `src/shared/TableScrollViewport.jsx` | Host + progressive row window | **OK** |
| `Registry.jsx`, `FacultyAccounts.jsx` | `TableDataViewport` | **OK** |
| `ProgressTable.jsx`, report tables | `TableDataViewport` | **OK** |
| `MarkingGrid.jsx` | `TableDataViewport` + dual layout | **OK** |
| `SessionWizard.jsx`, `ReviewAssignmentsStep.jsx`, `PanelReviewersStep.jsx`, `CsvImportMapper.jsx`, `AuditLog.jsx`, `RubricTable.jsx` | `TableScrollWrapper` | **OK** |
| `RubricTable.jsx` | `min-w-[28rem]` inside scroll wrapper | **OK** — scroll contained by parent |
| Wizard enrol table (`SessionWizard.jsx`) | `min-w-full` in `TableScrollWrapper` | **OK** |

**Page-level `overflow-x-auto` in `src/`:** only via `TABLE_SCROLL_WRAPPER` in `tableStyles.js` — **OK**

---

## `matchMedia` (JS)

| File | Query | Classification |
|------|-------|----------------|
| `src/shared/useSidebarLayout.js` | `(min-width: 1024px)` | **OK** — already mobile-first; closes drawer on resize to lg |

---

## JSX spot checks

| Item | Finding | Classification |
|------|---------|----------------|
| `Dashboard.jsx` | `grid gap-4 sm:grid-cols-2 lg:grid-cols-3` | **OK** |
| `PageHeader.jsx` | `flex-col … sm:flex-row` | **OK** |
| `MarkAssignments.jsx` | `md:grid-cols-2` (1 col at 375px) | **OK** |
| `SessionWizard.jsx` | `flex-wrap` on steps | **OK** |
| Reports matrices | Wide tables in viewports only | **Defer** — no redesign in this story (epic scope) |

---

## Build output

`build/*.css` — Tailwind emits `min-width` queries only (v3 defaults). **Do not edit by hand.**

---

## Summary

| Classification | Count |
|----------------|-------|
| **OK** | All audited source patterns |
| **Fix** | `app-shell.css` max-width drawer pair → refactored to mobile-first base + `min-width: 1024px` |
| **Defer** | Reports matrix density / new E2E viewport suite |

---

## Manual smoke checklist (AC 12–13)

Verify after responsive changes:

| Width | Routes | Expected |
|-------|--------|----------|
| 375px | Landing, dashboard, registry, wizard students, reviewer assignments, marking grid | No body horizontal scroll; tables scroll inside viewport; marking **cards** not table |
| 768px | Dashboard, assignments | 2-col where `md:` applies |
| 1024px / 1280px | Same routes | Persistent sidebar; 2–3 col grids; marking **table** visible |

See `tests/e2e/MVP_CHECKLIST.md` Prerequisites for optional 375px spot-check note.
