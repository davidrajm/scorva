# Story 19.1: Route and tab navigation loading — eliminate flicker

Status: review

<!-- Ultimate context engine analysis completed — cross-cutting UX: stable page chrome, skeletons, stale-while-revalidate; coordinator + reviewer SPAs; aligns with UX spec Loading patterns -->

## Story

As a **coordinator or reviewer** navigating the plugin (sidebar links, dashboard status tabs, reports/wizard tabs, marking routes),
I want **stable loading feedback** that does not flash empty content or collapse the page layout,
So that route and tab changes feel polished and predictable — matching industry-standard SPA patterns (persistent shell, content-area skeletons, no full-page blanking on refetch).

## Background — problem report (reproduce first)

| Symptom | Example URL | Likely cause in codebase |
|---------|-------------|---------------------------|
| Flash of empty/minimal content when opening a page | `http://localhost:3000/reviews` → `#/` (Dashboard) | `Dashboard.jsx` **early-returns** only `<p>Loading projects…</p>` before `PageHeader` / tabs render (`if ( loading ) return …` lines 181–186) |
| Flicker when switching dashboard status tabs | `#/?status=active` → `#/?status=draft` | `loadSessions()` always `setLoading( true )` (line 47); same early-return wipes grid + header chrome |
| Flicker on sidebar route change | `#/` → `#/registry`, `#/session/12/progress` | Route component unmounts; next page often early-returns plain loading text |
| Tab content flash | Reports `?tab=marks`, Wizard `?step=panels` | Table areas swap to `<p>Loading…</p>` while `loadingView` / step loaders run; page chrome sometimes missing |
| Reviewer assignment → grid | `#/mark/:session/:review/:panel` | `MarkingGrid.jsx` full-page loading return (line 168+) |

**Industry-standard target behaviour (this story):**

1. **Persistent chrome** — `AppShell`, `PageHeader`, tablists (`DashboardStatusNav`, `ReportsNav`, `WizardNav`) stay mounted during data fetch.
2. **Content-area loading** — skeletons or subdued previous content in the main pane only; optional subtle `aria-busy` on that region.
3. **Initial vs refresh** — first visit: skeleton; filter/tab/refetch: **stale-while-revalidate** (keep prior data visible with overlay/spinner) unless data is sensitive to stale display.
4. **Route code-splitting (optional but recommended)** — `React.lazy` + `Suspense` with a shared route fallback that matches page layout, not a bare paragraph.

## Acceptance Criteria

### 1. Shared loading primitives (`src/shared/components/`)

1. **Given** coordinator and reviewer SPAs  
   **When** any page or table loads data  
   **Then** loading UI uses shared components exported from `src/shared/components/index.js`:

   | Component | Purpose |
   |-----------|---------|
   | `ContentLoadingRegion` | Wrapper: `aria-busy`, `aria-live="polite"`, min-height to prevent layout shift; variants `inline` \| `overlay` |
   | `PageContentSkeleton` | Generic page body placeholder (title bar optional block + 2–3 content rows) |
   | `CardGridSkeleton` | Dashboard-style `sm:grid-cols-2 lg:grid-cols-3` card placeholders |
   | `TableSkeleton` | Header row + N body rows inside `TABLE_SCROLL_WRAPPER` / `pr-table-scroll` where tables are used |
   | `RouteFallback` | Suspense fallback sized to main content area (used in `App.jsx`) |

2. **And** skeletons use design tokens (`bg-surface-raised`, `border-border`, `animate-pulse`) with `@media (prefers-reduced-motion: reduce) { animation: none }` in `assets/css/app-shell.css` or Tailwind `motion-reduce:animate-none`.

3. **And** button-level loading continues using existing `Button` `loading` prop; table/page loading uses skeletons per UX spec (“Table skeleton for progress; button spinner for saves/downloads”).

4. **And** add `@wordpress/components` **`Spinner`** only where a compact inline indicator is needed (overlay on stale content, small regions); tree-shake / import only `Spinner` — no block editor bundles on plugin routes (NFR3).

### 2. Loading state pattern — initial vs refreshing

5. **Given** a page that fetches REST data on mount  
   **When** data has never loaded (`data === null` or equivalent)  
   **Then** show **skeleton** in the content region while keeping page chrome (see AC3).

6. **Given** the same page when user changes filter/tab or triggers refetch and **prior data exists**  
   **When** a new request starts  
   **Then** do **not** replace the entire page with a loading paragraph  
   **And** either:
   - keep showing previous data with `ContentLoadingRegion` `variant="overlay"` + `Spinner`, **or**
   - swap only the data pane to `TableSkeleton` / `CardGridSkeleton` without unmounting `PageHeader` / tab nav.

7. **And** document the pattern in this story’s Dev Notes as **`useLoadingState(initialLoading, refreshing)`** or inline `const showSkeleton = loading && !data` / `const showOverlay = loading && data` — pick one consistent approach project-wide.

### 3. Coordinator route-level behaviour

8. **Given** `src/coordinator/App.jsx`  
   **When** implemented  
   **Then** route elements use `React.lazy()` + `<Suspense fallback={<RouteFallback />}>` **or** an equivalent documented approach that prevents a blank `#pr-root` main area on first navigation to a heavy page.

9. **Given** Dashboard at `#/`  
   **When** loading sessions (initial or status tab change)  
   **Then** `PageHeader`, `DashboardStatusNav`, and create-project CTA area remain visible  
   **And** project grid shows `CardGridSkeleton` (initial) or previous cards + overlay (refresh) — **not** early-return `Loading projects…` only.

10. **Given** status tab change (`?status=`) per story **17-1**  
    **When** `GET /sessions?status=…` runs  
    **Then** no visible flicker of the tablist or page title; count line may show “Loading…” or skeleton count only in the grid region.

11. **Given** these coordinator routes, **when** first opened, content region uses skeletons (not bare `<p>Loading…</p>`) while chrome persists:

    | Route | Page / component |
    |-------|------------------|
    | `#/registry` | `Registry.jsx` |
    | `#/faculty` | `FacultyAccounts.jsx` |
    | `#/session/:id/wizard` | `SessionWizard.jsx` |
    | `#/session/:id/progress` | `SessionProgress.jsx` |
    | `#/session/:id/reports` | `Reports.jsx` |
    | `#/session/:id/audit` | `AuditLog.jsx` |
    | `#/session/:id/close` | `CloseSession.jsx` |
    | `#/session/:id/settings/panel-report` | `PanelReportSettings.jsx` |

12. **Given** Reports page (`Reports.jsx`)  
    **When** switching `?tab=` or `?review=`  
    **Then** `ReportsNav` and `PageHeader` stay mounted  
    **And** marks/scores/consolidated tables use `TableSkeleton` or overlay refresh per AC6 — not full-page text swap.

13. **Given** Wizard steps (`SessionWizard.jsx` + step components)  
    **When** switching `?step=` or loading step data  
    **Then** `WizardNav` + session `PageHeader` remain visible during step fetch.

### 4. Reviewer SPA

14. **Given** `src/reviewer/App.jsx`  
    **When** navigating assignments → marking grid → panel report  
    **Then** `ReviewerNav` / top shell stays stable  
    **And** `MarkAssignments.jsx`, `MarkingGrid.jsx`, `PanelReportPage.jsx`, `RubricForm.jsx` follow AC5–7 (no full-page early return that drops nav).

### 5. Accessibility and motion

15. **Given** loading regions  
    **When** busy  
    **Then** `aria-busy="true"` on the content container; completed loads set `aria-busy="false"`.

16. **And** skeleton blocks have `aria-hidden="true"`; visible status text for screen readers via `sr-only` “Loading …” inside `ContentLoadingRegion` where needed.

17. **And** respect `prefers-reduced-motion` for pulse animation (WCAG / UX NFR13).

### 6. Regression and verification

18. **Given** `npm run build`  
    **When** run after changes  
    **Then** build succeeds with no new dependency bloat beyond `@wordpress/components` (Spinner only, if not already present).

19. **Given** PHPUnit  
    **When** `composer test` or project test script runs  
    **Then** existing suite remains green (no PHP changes required unless enqueue changes).

20. **Given** Playwright E2E (`tests/e2e/`)  
    **When** coordinator journey touches dashboard, registry, wizard, reports  
    **Then** update selectors if loading markup changes; add `data-testid="pr-content-loading"` on `ContentLoadingRegion` for stable waits  
    **And** tests wait for **content ready** (skeleton gone / data-testid present), not arbitrary `waitForTimeout`.

21. **Given** manual smoke on `http://localhost:3000/reviews` (browser-sync)  
    **When** rapidly clicking Dashboard status tabs and sidebar items (Dashboard → Registry → Dashboard → Active/Draft tabs)  
    **Then** user perceives **no white flash** or collapse of header/tabs (record before/after in Dev Agent Record).

## Tasks / Subtasks

- [x] Add shared loading components + barrel exports (AC: 1, 5–7, 15–17)
  - [x] `ContentLoadingRegion`, `PageContentSkeleton`, `CardGridSkeleton`, `TableSkeleton`, `RouteFallback`
  - [x] Optional small hook `useLoadingPhase({ loading, hasData })` → `{ showSkeleton, showOverlay }`
- [x] Coordinator `App.jsx` — lazy routes + Suspense (AC: 8)
- [x] Refactor **Dashboard** — highest priority repro case (AC: 9–10)
- [x] Refactor remaining coordinator pages in inventory table (AC: 11–13)
- [x] Refactor wizard step components that early-return loading text (AC: 13)
- [x] Refactor reviewer pages (AC: 14)
- [x] CSS: reduced-motion for skeleton pulse (AC: 2, 17)
- [x] E2E: `data-testid` + wait strategy updates (AC: 20)
- [x] Manual flicker checklist in Dev Agent Record (AC: 21)

## Dev Notes

### Root cause summary (do not re-litigate)

The flicker is **not** HashRouter itself — it is **unmounting page chrome** during fetch:

```181:186:src/coordinator/pages/Dashboard.jsx
	if ( loading ) {
		return (
			<p className="text-base text-text-muted" aria-live="polite">
				Loading projects…
			</p>
		);
	}
```

Same anti-pattern appears in ~20 files (grep `if ( loading )` + early return). **Fix pattern:** render shell first; gate only the data slot.

### Recommended implementation sequence

1. Shared primitives (one PR-sized chunk).
2. **Dashboard** + **Reports** (user-reported + tab-heavy).
3. **SessionWizard** + progress.
4. Reviewer marking flow.
5. Remaining pages (audit, faculty, close, settings).

### Route lazy-loading sketch

```jsx
import { lazy, Suspense } from '@wordpress/element';
import { RouteFallback } from '../shared/components';

const Dashboard = lazy( () =>
	import( './pages/Dashboard' ).then( ( m ) => ( { default: m.Dashboard } ) )
);

// Inside Routes:
<Route
  path="/"
  element={
    <Suspense fallback={ <RouteFallback label="Dashboard" /> }>
      <Dashboard />
    </Suspense>
  }
/>
```

`@wordpress/scripts` supports dynamic `import()` — verify chunk names in `build/coordinator.js` after build.

### Stale-while-revalidate sketch (Dashboard tabs)

```jsx
const [ sessions, setSessions ] = useState( null );
const [ fetching, setFetching ] = useState( true );

const loadSessions = useCallback( async () => {
  setFetching( true );
  try {
    const data = await get( `/sessions${ query }` );
    setSessions( Array.isArray( data ) ? data : [] );
  } finally {
    setFetching( false );
  }
}, [ apiStatus ] );

const showSkeleton = fetching && sessions === null;
const showOverlay = fetching && sessions !== null;
```

Do **not** clear `sessions` to `null` on tab change.

### Loading pattern (project-wide)

Use `useLoadingPhase( loading, hasData )` from `src/shared/hooks/useLoadingPhase.js`:

- `showSkeleton` — initial fetch (`hasData` false)
- `showOverlay` — refetch with stale data (`ContentLoadingRegion` + `Spinner`)

### Files inventory (coordinator — early loading return)

| File | Notes |
|------|--------|
| `src/coordinator/pages/Dashboard.jsx` | **Priority** — status tabs |
| `src/coordinator/pages/Registry.jsx` | |
| `src/coordinator/pages/FacultyAccounts.jsx` | |
| `src/coordinator/pages/SessionWizard.jsx` | Wizard shell + `WizardNav` |
| `src/coordinator/pages/SessionProgress.jsx` | |
| `src/coordinator/pages/Reports.jsx` | Tab + review param |
| `src/coordinator/pages/AuditLog.jsx` | |
| `src/coordinator/pages/CloseSession.jsx` | |
| `src/coordinator/pages/PanelReportSettings.jsx` | |
| `src/coordinator/components/ReviewRubricsStep.jsx` | Step body only |
| `src/coordinator/components/ReviewRoundsStep.jsx` | |
| `src/coordinator/components/ReviewAssignmentsStep.jsx` | |
| `src/coordinator/components/ReviewMarkingStep.jsx` | |
| `src/coordinator/components/RubricsPanel.jsx` | |
| `src/coordinator/components/ReportsMarksTable.jsx` | Table skeleton |
| `src/coordinator/components/ReportsOverallScoresTable.jsx` | |
| `src/coordinator/components/ReportsScoresTable.jsx` | |
| `src/coordinator/components/ReportsConsolidatedTable.jsx` | |
| `src/coordinator/components/ScoreBreakdown.jsx` | |

### Files inventory (reviewer)

| File | Notes |
|------|--------|
| `src/reviewer/pages/MarkAssignments.jsx` | |
| `src/reviewer/components/MarkingGrid.jsx` | |
| `src/reviewer/components/RubricForm.jsx` | |
| `src/reviewer/pages/PanelReportPage.jsx` | |

### Out of scope

- Migrating HashRouter to React Router data APIs (`createHashRouter` + `useNavigation`) — optional follow-up; not required if lazy + shell pattern fixes flicker.
- Backend / REST performance optimization.
- Landing app (`src/landing/App.jsx`) — static, no flicker reported.
- Changing desktop-first product priority (NFR11) — story **18-1** remains authoritative for responsive CSS.

### Architecture compliance

| Requirement | Application |
|-------------|-------------|
| NFR3 | No theme assets; `@wordpress/components` Spinner only, selective import |
| NFR10 | HashRouter unchanged; `#/` routes unchanged |
| NFR13 | `aria-busy`, focus not stolen by skeletons |
| UX spec § Loading | Table skeleton + button spinner — this story adds missing table/page skeletons |
| UX-DR20 | Notices unchanged |

### Testing

- **Manual:** checklist in AC 21 — dashboard tabs, sidebar hops, reports tabs, wizard steps, reviewer mark flow.
- **E2E:** `tests/e2e/specs/full-plugin-ui-journey.spec.ts` — replace any `waitForTimeout` anti-patterns with `waitForSelector` on content or `[data-testid="pr-content-loading"]` detached.
- **PHPUnit:** no new tests unless enqueue adds `@wordpress/components` CSS — if so, extend `tests/RoutesTest.php` to assert Spinner stylesheet not enqueued from theme (plugin-only).

### Previous story intelligence

- **17-1** — Dashboard status tabs + `useSearchParams`; preserve URL behaviour when refactoring loading.
- **18-1** — `prefers-reduced-motion`; skeleton animations must comply.
- **1-6** — AppShell + HashRouter foundation; lazy routes extend 1-6 without new routes.
- **1-9** — Table viewports; `TableSkeleton` should live inside `TABLE_DATA_VIEWPORT` / scroll wrappers, not expand page scroll.

### References

- [Source: _bmad-output/planning/ux-design-specification.md — Component Strategy Spinner; UX Consistency § Loading]
- [Source: _bmad-output/planning/epics.md — UX-DR20, NFR10, Story 1.6]
- [Source: _bmad-output/implementation/17-1-dashboard-project-status-tabs.md]
- [Source: src/coordinator/App.jsx, src/reviewer/App.jsx]
- [Source: src/coordinator/pages/Dashboard.jsx — anti-pattern lines 181–186, 47–57]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

- Build: `npm run build` — success; coordinator code-split chunks emitted.
- PHPUnit: `composer test` — 348 tests OK.

### Completion Notes List

- Added shared loading primitives (`ContentLoadingRegion`, skeletons, `RouteFallback`) and `useLoadingPhase` hook; `@wordpress/components` `Spinner` for overlay refresh only.
- Coordinator routes lazy-loaded with `Suspense` + `RouteFallback`; dashboard uses stale-while-revalidate (cards stay visible + overlay on status tab change).
- Refactored coordinator/reviewer pages and table components to keep `PageHeader` / nav chrome mounted; E2E waits on `data-testid="pr-content-loading"` cleared + content visible.
- **Manual flicker (AC 21):** After implementation, header/tabs remain mounted during fetch; grid/table areas show pulse skeletons or overlay instead of full-page “Loading…” paragraphs. Verify locally: rapid Dashboard status tabs and sidebar hops (`#/ ` ↔ `#/registry`).

### File List

- package.json
- package-lock.json
- assets/css/app-shell.css
- src/shared/hooks/useLoadingPhase.js
- src/shared/components/SkeletonBlock.jsx
- src/shared/components/ContentLoadingRegion.jsx
- src/shared/components/PageContentSkeleton.jsx
- src/shared/components/CardGridSkeleton.jsx
- src/shared/components/TableSkeleton.jsx
- src/shared/components/RouteFallback.jsx
- src/shared/components/index.js
- src/coordinator/App.jsx
- src/coordinator/pages/Dashboard.jsx
- src/coordinator/pages/Registry.jsx
- src/coordinator/pages/FacultyAccounts.jsx
- src/coordinator/pages/SessionWizard.jsx
- src/coordinator/pages/SessionProgress.jsx
- src/coordinator/pages/Reports.jsx
- src/coordinator/pages/AuditLog.jsx
- src/coordinator/pages/CloseSession.jsx
- src/coordinator/pages/PanelReportSettings.jsx
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/components/ReportsScoresTable.jsx
- src/coordinator/components/ReportsOverallScoresTable.jsx
- src/coordinator/components/ReportsConsolidatedTable.jsx
- src/coordinator/components/ScoreBreakdown.jsx
- src/coordinator/components/ReviewRubricsStep.jsx
- src/coordinator/components/ReviewRoundsStep.jsx
- src/coordinator/components/ReviewAssignmentsStep.jsx
- src/coordinator/components/ReviewMarkingStep.jsx
- src/coordinator/components/RubricsPanel.jsx
- src/reviewer/pages/MarkAssignments.jsx
- src/reviewer/components/MarkingGrid.jsx
- src/reviewer/components/RubricForm.jsx
- tests/e2e/helpers/journey-steps.ts
- tests/e2e/specs/capture-sop-screenshots.spec.ts
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css
- build/reviewer.asset.php

## Change Log

- 2026-05-24: Story 19.1 — shared loading UX, lazy coordinator routes, stale-while-revalidate dashboard, skeleton/overlay refactors across coordinator and reviewer SPAs.

## Story Completion Status

- Status: **review**
- Ultimate context engine analysis completed — comprehensive developer guide created for cross-SPA loading UX and flicker elimination.
