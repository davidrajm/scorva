# Story 17.1: Dashboard — project status tabs (icons, URL sync, Active default)

Status: review

<!-- Ultimate context engine analysis completed — coordinator dashboard at `#/` replaces status chip buttons with Reports/Wizard-style tablist; default filter Active projects; `status` query param -->

## Story

As a **project coordinator**,
I want the Projects dashboard to use clear **status tabs** with icons and a shareable URL for each filter,
So that I can bookmark “active projects only”, refresh without losing my filter, and use browser Back/Forward — with **Active projects** selected by default when I open the dashboard.

## Background — current behaviour (do not guess)

| Area | Current behaviour | Gap |
|------|-------------------|-----|
| **Route** | `HashRouter` route `/` → `Dashboard` (`src/coordinator/App.jsx`) | URL is `http://localhost:3000/reviews/#/` (or production equivalent) |
| **Status filter** | Four **chip buttons** (`rounded-md px-3 py-1.5`, primary fill when selected) in `Dashboard.jsx` lines 296–312 | Not tablist semantics; no icons; looks unlike Reports/Wizard nav |
| **Default filter** | `useState('')` → **All** projects (`GET /sessions` with no `status`) | User wants **Active projects** as default |
| **URL** | Filter lives only in React state | Lost on refresh; not shareable |
| **REST** | `GET /pr/v1/sessions?status={draft\|active\|closed}` — invalid status → 400 (`Rest_Sessions::list_sessions`) | Front-end must only send valid values or omit param for “all” |
| **Reference tabs** | `ReportsNav` + `Reports.jsx` (`?tab=`, `?review=`); `WizardNav` + `SessionWizard.jsx` (`?step=`) | Reuse same `useSearchParams` patterns |

### User request (source)

- CSS work on coordinator dashboard (`/reviews/#/`).
- Tabs should look **enhanced**, with **proper icons**.
- **Active Projects** selected by default.
- **URL query vars** for each tab — refer to Reports/Wizard tab URL behaviour.

## Acceptance Criteria

### 1. Tab UI (visual + accessibility)

1. **Given** the coordinator opens the dashboard at `#/`  
   **When** the project list section renders  
   **Then** status filters appear as a **horizontal tablist** (same visual language as `ReportsNav` / `WizardNav`): bottom border on nav, `border-b-2` on active tab, `text-primary` / `border-primary` when selected, muted inactive tabs with hover.

2. **And** each tab shows a **leading icon** from `NavIcon` / `Icon` (`src/shared/components/NavIcon.jsx`) at `h-4 w-4`, with label text.

3. **And** tabs use `role="tablist"`, each control `role="tab"` with `aria-selected`, and `nav` has `aria-label` such as “Filter projects by status”.

4. **And** tab labels (exact copy):

   | URL `status` value | Label | Suggested icon key |
   |--------------------|-------|-------------------|
   | `all` | All projects | `dashboard` |
   | `active` | Active projects | `progress` |
   | `draft` | Draft projects | `pencil` |
   | `closed` | Closed projects | `close` |

5. **And** remove the old filled chip button styling for status filters (no regression to primary pill buttons on this control).

6. **And** project count line (`N project(s)`) and `SessionCard` grid remain below the tablist; layout spacing consistent with Reports page (`mb-8` on nav or equivalent).

### 2. Default tab — Active projects

7. **Given** the user opens `#/` with **no** `status` query param  
   **When** the dashboard loads  
   **Then** the **Active projects** tab is selected  
   **And** the list calls `GET /sessions?status=active` (same data as today’s “Active” chip).

8. **Given** the user opens `#/?status=active`  
   **When** the page loads  
   **Then** Active projects tab is selected and the same API call runs.

### 3. URL query param (`status`) — parity with Reports tabs

9. **Given** a URL with `?status={value}` where `{value}` is one of: `all` | `active` | `draft` | `closed`  
   **When** the page loads or the user refreshes  
   **Then** that tab is selected and the correct filtered list loads.

10. **Given** an unknown or empty `status` value (e.g. `?status=foo` or `?status=`)  
    **When** the page loads  
    **Then** fall back to **active** and `setSearchParams` **replaces** the invalid value with `status=active` (`replace: true`), same normalization pattern as invalid `tab` on Reports.

11. **Given** the user clicks a tab  
    **When** the filter changes  
    **Then** `setSearchParams` updates `status` in the URL (HashRouter: query after hash, e.g. `#/?status=draft`).

12. **And** browser **Back/Forward** changes the active tab and reloads the matching list (derive filter from `useSearchParams` — **do not** keep status only in `useState`).

13. **And** deep links work, e.g.  
    - `#/?status=draft`  
    - `#/?status=closed`  
    - `#/?status=all` → `GET /sessions` with no `status` query (all projects).

14. **And** when navigating away and back via router, preserve `status` in URL if still present (do not strip params on unrelated dashboard actions like create-project form toggle).

### 4. API mapping (no backend change required)

15. **Given** `status=all` in the URL  
    **When** fetching sessions  
    **Then** call `GET /sessions` **without** `status` query param (not `status=all` on REST — API has no `all` enum).

16. **Given** `status` is `draft`, `active`, or `closed`  
    **When** fetching sessions  
    **Then** call `GET /sessions?status={value}` exactly as today’s chip filter.

### 5. Regressions & build

17. **And** create project, `PanelUnfreezeRequests`, success notice from `location.state`, and empty state behaviour unchanged except default list is **active** not all.

18. **And** `npm run build` passes.

19. **Optional but valuable:** `data-testid="pr-dashboard-status-{all|active|draft|closed}"` on each tab for E2E; update `tests/e2e/MVP_CHECKLIST.md` manual step if coordinators document default view change.

## Tasks / Subtasks

- [x] **JS:** Add `src/coordinator/components/DashboardStatusNav.jsx` — export `DASHBOARD_STATUS_TABS` + presentational nav (mirror `ReportsNav.jsx` structure)
- [x] **JS:** `Dashboard.jsx` — `useSearchParams`; derive `statusFilter` from URL; default `active`; normalize invalid params; `setSearchParams` on tab click; remove `useState` for status filter
- [x] **JS:** Wire `loadSessions` to derived status (map `all` → no REST param)
- [x] **Docs/tests:** Optional E2E testid + MVP checklist note; `npm run build`
- [x] **Manual:** URL checklist in Dev Agent Record (refresh, Back/Forward, invalid `status`)

## Dev Notes

### URL pattern (HashRouter)

Coordinator app uses `HashRouter` (`src/coordinator/App.jsx`). Examples:

```
#/
#/?status=active
#/?status=draft
#/?status=closed
#/?status=all
```

Query string lives **after** the hash path. Use React Router `useSearchParams` — same as Reports:

```52:56:src/coordinator/pages/Reports.jsx
	const [ searchParams, setSearchParams ] = useSearchParams();
	const tabParam = searchParams.get( 'tab' );
	const tab = TAB_IDS.includes( tabParam )
		? tabParam
		: LEGACY_TAB_ALIASES[ tabParam ] ?? 'marks';
```

**Implementation sketch for Dashboard:**

```javascript
const STATUS_TAB_IDS = [ 'all', 'active', 'draft', 'closed' ];
const [ searchParams, setSearchParams ] = useSearchParams();
const statusParam = searchParams.get( 'status' );
const statusFilter = STATUS_TAB_IDS.includes( statusParam )
	? statusParam
	: 'active';

const apiStatus =
	statusFilter === 'all' ? '' : statusFilter;

// loadSessions: apiStatus ? `?status=${encodeURIComponent(apiStatus)}` : ''

const setStatusFilter = ( next ) => {
	setSearchParams( ( prev ) => {
		const nextParams = new URLSearchParams( prev );
		nextParams.set( 'status', next );
		return nextParams;
	} );
};

useEffect( () => {
	if ( statusParam && ! STATUS_TAB_IDS.includes( statusParam ) ) {
		setSearchParams(
			( prev ) => {
				const next = new URLSearchParams( prev );
				next.set( 'status', 'active' );
				return next;
			},
			{ replace: true }
		);
	}
}, [ statusParam, setSearchParams ] );
```

**Default without param:** Treat missing `status` as `active` in derived state; optionally **do not** auto-write `?status=active` on first paint (Reports leaves default tab implicit until user clicks — either approach OK if AC 7–8 pass; prefer **no** forced URL write on first visit unless invalid param).

### Current chip implementation (replace)

```15:20:src/coordinator/pages/Dashboard.jsx
const STATUS_FILTERS = [
	{ value: '', label: 'All' },
	{ value: 'draft', label: 'Draft' },
	{ value: 'active', label: 'Active' },
	{ value: 'closed', label: 'Closed' },
];
```

```296:312:src/coordinator/pages/Dashboard.jsx
			<div className="mt-6 flex flex-wrap gap-2" role="group" aria-label="Filter by status">
				{ STATUS_FILTERS.map( ( filter ) => (
					<button
						...
```

Replace this block with `<DashboardStatusNav currentStatus={ statusFilter } onStatusClick={ setStatusFilter } />`.

### Reference component — ReportsNav

```1:46:src/coordinator/components/ReportsNav.jsx
export const REPORTS_TABS = [
	{ key: 'marks', label: 'Rubric marks', icon: 'rubrics' },
	...
];
export function ReportsNav( { currentTab, onTabClick } ) {
	return (
		<nav aria-label="Report sections" className="mb-8 border-b border-border">
			<ol className="flex flex-wrap gap-0" role="tablist">
				...
```

Copy class names for tab buttons (`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors -mb-px`, etc.).

### REST contract (unchanged)

```157:172:includes/rest/class-rest-sessions.php
        $status = $request->get_param('status');
        ...
        if ($status !== null && !in_array($status, SessionRepository::VALID_STATUSES, true)) {
            return new WP_Error(
                'pr_invalid_status',
                ...
        $sessions = $repository->list_all($status);
```

Valid API values: `draft`, `active`, `closed` only. Front-end maps `all` → omit param.

### Anti-patterns

- Do **not** use `useState` for status while also reading URL — single source of truth: `searchParams` (derived `statusFilter`).
- Do **not** send `status=all` to REST.
- Do **not** introduce a new icon set or external icon library — use existing `Icon` from `NavIcon.jsx`.
- Do **not** refactor `ReportsNav` / `WizardNav` into a generic abstraction in this story (YAGNI).
- Do **not** change session card routes or create-project flow.

### Behaviour change note (intentional)

Story **3.2** shipped with default **All** projects. This story changes default to **Active projects** per product request. Coordinators who relied on seeing drafts + closed on first load must click **All projects** or use `#/?status=all`.

### Files to touch

| Area | File |
|------|------|
| Status tab nav (new) | `src/coordinator/components/DashboardStatusNav.jsx` |
| Dashboard page | `src/coordinator/pages/Dashboard.jsx` |
| Optional E2E/docs | `tests/e2e/MVP_CHECKLIST.md` |

No PHP changes expected (`RestSessionsTest` already covers status filter).

### Regression scope

- Registry, wizard, reports, faculty pages — unchanged.
- `PanelUnfreezeRequests` on dashboard — unchanged placement above tabs.
- Post-close/delete redirect to `#/` with notice — still works; user lands on **active** list unless URL includes `status`.

### References

- [Source: src/coordinator/pages/Dashboard.jsx]
- [Source: src/coordinator/components/ReportsNav.jsx]
- [Source: src/shared/components/WizardNav.jsx]
- [Source: src/coordinator/pages/Reports.jsx — URL tab sync]
- [Source: _bmad-output/implementation/12-5-reports-downloads-tab-restructure.md — URL patterns]
- [Source: _bmad-output/implementation/3-2-sessions-rest-dashboard.md — UX-DR29 status filter]
- [Source: _bmad-output/planning/ux-design-specification.md — Direction 1 dashboard session cards]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

_(none)_

### Completion Notes List

- Added `DashboardStatusNav` mirroring `ReportsNav` (tablist, icons, `data-testid="pr-dashboard-status-{key}"`).
- `Dashboard` derives filter from `?status=` via `useSearchParams`; missing/invalid → `active` with `replace` URL fix for invalid only; `all` omits REST `status` param.
- Success-notice navigation preserves `location.search` so `status` is not stripped.
- Default view is **Active projects** (`GET /sessions?status=active`) when `#/` has no query.
- `npm run build` OK; `composer test` 347 passed.
- **Manual URL checklist:** `#/` → Active tab, active API; `#/?status=draft|closed|all` → correct tab + API; refresh keeps filter; tab clicks update hash query; Back/Forward syncs tab; `#/?status=foo` and `#/?status=` → replace to `active`; no `?status=` written on first visit without param.

### File List

- `src/coordinator/components/DashboardStatusNav.jsx` (new)
- `src/coordinator/pages/Dashboard.jsx`
- `tests/e2e/MVP_CHECKLIST.md`
- `build/coordinator.js` (generated)
- `build/coordinator.css` (generated)
- `build/coordinator-rtl.css` (generated)
- `build/coordinator.asset.php` (generated)

### Change Log

- 2026-05-24: Story created (Epic 17) — dashboard status tabs, icons, URL `status` param, Active default.
- 2026-05-24: Implemented status tab nav, URL sync, Active default; MVP checklist note; build verified.
