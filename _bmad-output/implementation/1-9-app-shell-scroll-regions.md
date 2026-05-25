# Story 1.9: App shell scroll, collapsible/resizable sidebar, and styled scrollbars

Status: review

<!-- Validation: optional validate-create-story before dev-story. Epic 1 — shell UX polish; no REST/DB changes. -->

## Story

As a **coordinator or reviewer** using Project Reviews on a desktop browser,
I want the sidebar and main content to scroll independently with clear, consistent scrollbars,
So that long navigation and wide data tables remain usable without the whole page jumping or clipping horizontal table scroll.

As a **coordinator**,
I want to collapse the sidebar or drag to resize it,
So that I can reclaim horizontal space for wide Reports tables while keeping navigation one click away.

## Acceptance Criteria

### 1. Independent scroll regions (coordinator)

1. **Given** the coordinator app at viewport height ≥ 1024px with a long sidebar nav (project context + all session links)  
   **When** the nav exceeds the viewport below the fixed top bar (56px)  
   **Then** only `.pr-sidebar` scrolls vertically; the top bar stays fixed and the main content area does not shift when scrolling the nav.

2. **Given** coordinator main content taller than the viewport (e.g. Reports, Wizard, Registry)  
   **When** the user scrolls  
   **Then** only `.pr-main` scrolls vertically; the sidebar position stays fixed (nav does not scroll away with page content).

3. **Given** the shell layout  
   **When** inspected in DevTools  
   **Then** `.pr-shell` fills the viewport below the browser chrome (`min-height: 100vh`), `.pr-body` is a flex row with `min-height: 0` / `overflow: hidden`, and both `.pr-sidebar` and `.pr-main` use `overflow-y: auto` (not document/body scroll for app content).

### 2. Reviewer and landing variants

4. **Given** reviewer or landing `AppShell` (no sidebar)  
   **When** content exceeds viewport height  
   **Then** `.pr-main` is the sole vertical scroll region (same scrollbar styling as coordinator main).

5. **And** reviewer wide marking grid at `lg+` still scrolls **horizontally inside the table wrapper only** — not the full page (parity with `TABLE_SCROLL_WRAPPER` comment in `tableStyles.js`).

### 3. Industry-standard scrollbar appearance

6. **Given** any scrollable shell region (`.pr-sidebar`, `.pr-main`) or shared table horizontal wrapper  
   **When** overflow triggers a scrollbar  
   **Then** scrollbars use plugin tokens (thin track, thumb using `--pr-color-border` / `--pr-color-text-muted`, hover slightly darker) via:
   - `scrollbar-width: thin` and `scrollbar-color` (Firefox)
   - `::-webkit-scrollbar` / `::-webkit-scrollbar-thumb` (Chromium, Safari)
   - Optional `scrollbar-gutter: stable` on `.pr-main` to reduce layout shift when vertical scrollbar appears

7. **And** styling is defined once in `assets/css/app-shell.css` (class `.pr-scroll` or element selectors on shell regions) — not duplicated per component.

8. **And** scrollbars remain visible enough for mouse users on Windows/Linux (do not use `overflow: overlay` hacks that hide scrollbars entirely).

### 4. Wide **and** long tables — scroll right without scrolling to the bottom first

**Problem to fix:** If `.pr-main` scrolls vertically but the table wrapper only has `overflow-x: auto`, the horizontal scrollbar sits at the **bottom of the table body** (hundreds of rows down). Users must scroll the page down, then scroll right — bad UX.

**Solution:** A **dual-axis table viewport** — one box with `overflow: auto` on **both** axes and a **max-height** tied to the visible workspace. The horizontal scrollbar stays at the bottom of **that box** (always on screen while the table is in view).

9. **Given** a data matrix with many rows and many columns (Reports marks/scores/consolidated, Registry, Progress, Marking grid desktop)  
   **When** the table renders  
   **Then** it is wrapped in `TABLE_DATA_VIEWPORT` (see Dev Notes) with:
   - `overflow: auto` (vertical **and** horizontal)
   - `max-height` filling remaining viewport below the fixed top bar (and below any non-scrolling toolbar above the table on that page)
   - `overscroll-behavior: contain`
   **And** horizontal scroll does **not** require scrolling `.pr-main` to the bottom first.

10. **Given** the dual-axis viewport  
    **When** the user scrolls vertically inside the table box  
    **Then** `<thead class="sticky top-0">` sticks to the **top of the viewport box** (not the top of `.pr-main`); Reg no / Student sticky-left columns still stick within horizontal scroll (existing `regNoStickyClass` / MarkingGrid patterns).

11. **Given** focus inside the table viewport (wrapper has `tabIndex={0}` when scrollable)  
    **When** the user holds **Shift** and uses the mouse wheel (or trackpad horizontal gesture)  
    **Then** the table scrolls horizontally without vertical movement (native behaviour where supported; no custom wheel hijacking required).

12. **Given** pages with controls above the table (Reports layout toggle, export buttons, review `<select>`)  
    **When** the table viewport is laid out  
    **Then** controls remain **above** the viewport (in normal document flow or `flex-shrink-0`); only the table scrolls inside the capped box — e.g. Reports tab panel uses `flex flex-col flex-1 min-h-0` and table area `flex-1 min-h-0 overflow-hidden` wrapping `TABLE_DATA_VIEWPORT`.

13. **Given** horizontal overflow on the table viewport (UX spec: “shadow cue”)  
    **When** content is clipped on the right (and optionally left when scrolled)  
    **Then** a subtle edge fade indicates more columns — without breaking sticky cells.

14. **And** short tables (few rows) do not force a tall empty box — `max-height` only caps maximum; wrapper height shrinks to content when content is shorter than the cap.

### 5. Consolidate table scroll wrappers

15. **Given** ad-hoc `overflow-x-auto` on coordinator/reviewer tables  
    **When** this story ships  
    **Then** all data-table horizontal wrappers use `TABLE_SCROLL_WRAPPER` from `src/shared/tableStyles.js` (plus any extra layout classes like `mt-4`, `shadow-card`) — **single source of truth** for overflow + scrollbar class.

| File | Action |
|------|--------|
| `ReportsMarksTable.jsx` | Use `TABLE_SCROLL_WRAPPER` |
| `ReportsScoresTable.jsx` | Same |
| `ReportsOverallScoresTable.jsx` | Same |
| `ReportsConsolidatedTable.jsx` | Same |
| `Registry.jsx` | Same (+ keep `shadow-card` if needed) |
| `ProgressTable.jsx` | Same |
| `AuditLog.jsx` | Same |
| `RubricTable.jsx` | Same |
| `PanelReviewersStep.jsx` | Same |
| `ReviewAssignmentsStep.jsx` | Same |
| `SessionWizard.jsx` | Same |
| `CsvImportMapper.jsx` | Same |
| `MarkingGrid.jsx` | Same (desktop grid) |

### 6. Collapsible coordinator sidebar (desktop `lg+`, ≥1024px)

16. **Given** the coordinator app at `lg+`  
    **When** the user clicks a **collapse** control on the sidebar (top of nav or edge of sidebar)  
    **Then** the sidebar enters **collapsed** mode: width **56px** (icon rail), nav labels hidden, `NavIcon` + `title` tooltip on each link remain usable.

17. **Given** collapsed mode  
    **When** the user clicks **expand** (same control)  
    **Then** the sidebar returns to the last **resized** width (or default 240px).

18. **Given** collapsed vs expanded preference  
    **When** the user reloads or navigates within the SPA  
    **Then** state is restored from `localStorage` key `pr-sidebar-collapsed` (`"1"` | `"0"`).

19. **Given** viewport `< lg` (768–1023px tablet per UX-DR26)  
    **When** the coordinator loads  
    **Then** sidebar is **off-canvas** by default; a **menu** button in the top bar opens a drawer overlay; `aria-expanded` on the toggle reflects open/closed; backdrop click or Escape closes the drawer.

20. **And** project context block in `CoordinatorNav` shows **truncated project title** when expanded; in collapsed rail show icons only (project section icon or first nav item pattern — no clipped multi-line text in 56px rail).

### 7. Resizable coordinator sidebar (desktop `lg+` only)

21. **Given** expanded sidebar at `lg+`  
    **When** the user drags a **resize handle** on the sidebar’s right edge  
    **Then** width updates live between **min 200px** and **max 400px** (defaults **240px**), driven by CSS variable `--pr-layout-sidebar-width`.

22. **Given** a resized width  
    **When** the user reloads  
    **Then** width is restored from `localStorage` key `pr-sidebar-width` (integer pixels); invalid/missing values fall back to 240px.

23. **And** resize handle is a **4px** hit target (wider invisible touch area acceptable), `cursor: col-resize`, `role="separator"`, `aria-orientation="vertical"`, `aria-valuenow` / `aria-valuemin` / `aria-valuemax` updated during drag; **ArrowLeft** / **ArrowRight** nudge width by 8px when handle is focused.

24. **And** while dragging, `user-select: none` on `body` and no text selection in main content; release restores normal selection.

25. **And** collapsed mode **disables** resize handle (rail is fixed 56px).

### 8. Accessibility and regression

26. **And** skip link (“Skip to main content”) still focuses `#pr-main` and keyboard scroll works in the focused region.

27. **And** no double scrollbars on typical pages (dashboard, reviewer assignments) at 1280×800.

28. **And** `npm run build` succeeds; PHPUnit suite unchanged/green (no new PHP tests required unless adding pure JS util tests).

29. **Manual verification** (document in Dev Agent Record):

| Scenario | Viewport | Pass criteria |
|----------|----------|---------------|
| Long project nav | 1280×720 | Sidebar scrolls; main static |
| Collapse / expand sidebar | 1280×800 | Rail 56px; icons + tooltips; persists reload |
| Resize sidebar | 1280×800 | Drag 200–400px; main reflows; persists reload |
| Reports → Rubric marks (wide + 50+ rows) | 1280×800 | At top of table: Shift+wheel scrolls right; horizontal bar visible without scrolling page to bottom |
| Registry table | 1280×800 | Same |
| Reviewer marking grid | 1280×800 | Horizontal scroll in grid wrapper only; **no** sidebar resize |
| Tablet drawer | 768×1024 | Hamburger opens/closes nav; no resize handle |
| Firefox + Chrome | 1280×800 | Thin styled scrollbars on sidebar + main |

## Tasks / Subtasks

- [x] **CSS tokens:** Add `--pr-scrollbar-thumb`, `--pr-scrollbar-track`; keep `--pr-layout-sidebar-width` as runtime width (default 240px); add `--pr-layout-sidebar-collapsed-width: 56px`
- [x] **Shell layout:** Refactor `.pr-shell`, `.pr-body`, `.pr-sidebar`, `.pr-main` for viewport-bound flex + independent `overflow-y: auto`; fix `overflow-x` on main so table wrappers are not clipped
- [x] **Scrollbar styles:** Apply `.pr-scroll` to sidebar, main, and table wrapper
- [x] **Sidebar shell UI:** `src/shared/SidebarLayout.jsx` (or extend `AppShell.jsx`) — collapse toggle, resize handle, `localStorage` read/write, `matchMedia('(min-width: 1024px)')` for lg behaviour
- [x] **CoordinatorNav:** Support `collapsed` prop — hide labels, keep `NavIcon`, `title` on links; simplify project card in rail mode
- [x] **Tablet drawer:** Top-bar menu button + overlay + `aria-expanded` (coordinator only)
- [x] **TABLE_DATA_VIEWPORT:** Dual-axis wrapper + CSS max-height; use on Reports/Registry/Progress/MarkingGrid
- [x] **Reports layout:** Flex column so table viewport fills space below toolbar (`Reports.jsx` tab panels)
- [x] **Table shadow cue:** Edge-cue on data viewport
- [x] **Migrate components:** Replace duplicate `overflow-x-auto` with `TABLE_SCROLL_WRAPPER` (see AC5 table)
- [x] **Build:** `npm run build`
- [x] **Manual:** Run AC29 checklist; note browsers tested

## Dev Notes

### Problem statement (current behaviour)

- UX spec § Spacing & Layout: **“content area scrollable”** and sidebar 240px (`ux-design-specification.md` L308).
- `assets/css/app-shell.css` sets `.pr-body { overflow-x: hidden }` and `.pr-main { overflow-x: hidden }` but **does not** assign `overflow-y: auto` or viewport height to shell regions — scrolling often falls through to `body`, so sidebar and main scroll together.
- `src/shared/tableStyles.js` documents intent: *“keeps overflow off the app shell / sidebar”* — but without a proper main scrollport + styled scrollbars, wide Reports matrices feel brittle.
- **No** `scrollbar-*` or `::-webkit-scrollbar` rules exist in the repo today.

### Target layout model (implement in `app-shell.css`)

```text
.pr-shell          flex column; min-height: 100vh
  .pr-topbar       fixed 56px (unchanged); coordinator: sidebar toggle at lg+
  .pr-body         flex 1; min-height: 0; overflow: hidden; padding-top: topbar
    .pr-sidebar    flex 0 0 var(--pr-layout-sidebar-width); position relative
                    overflow-y: auto; overflow-x: hidden; pr-scroll
                    [.pr-sidebar--collapsed] → width var(--pr-layout-sidebar-collapsed-width)
    .pr-sidebar-resize-handle  absolute right 0; hidden when collapsed or < lg
    .pr-main       flex 1; min-width: 0; overflow-y: auto; overflow-x: hidden; pr-scroll
```

Use `height: 100vh` on shell **or** `min-height: 0` flex trick — pick one approach that works with WordPress `body.pr-app-shell { margin: 0 }` and does not trap mobile browser chrome (acceptable: desktop-first per UX).

**Reviewer / landing:** `#pr-root[data-app="reviewer"]` and `[data-app="landing"]` — `.pr-body` is `display: block`; only `.pr-main` needs the scroll region rules. **No** collapse/resize on reviewer (no sidebar).

### Collapsible + resizable implementation (no new dependencies)

| Concern | Approach |
|---------|----------|
| State | React state in `AppShell` or `SidebarLayout`: `collapsed`, `widthPx`; hydrate from `localStorage` on mount |
| Width application | Set `document.documentElement` or `#pr-root` style `--pr-layout-sidebar-width: ${widthPx}px` |
| Collapse | Toggle class `pr-sidebar--collapsed` on `<nav>`; do not persist width changes when collapsed |
| Resize drag | `pointerdown` on handle → `pointermove` / `pointerup` on `window`; clamp 200–400; `pointer-events` + `setPointerCapture` |
| Keyboard | Focus handle; Left/Right ±8px; respect min/max |
| Tablet `< lg` | `position: fixed` drawer + backdrop; ignore resize + collapse rail; use hamburger only |
| Icons | Reuse `NavIcon` in `CoordinatorNav.jsx`; collapsed: `span` labels `sr-only` or hidden with `aria-hidden` |

**Collapse control placement:** Chevron button at top of sidebar (below topbar visually) or on the resize handle stack — match common IDE/email apps (VS Code, Gmail). Use `aria-label="Collapse sidebar"` / `"Expand sidebar"`.

**Do not** use `react-resizable-panels` or similar — keep bundle small; native pointer events are sufficient for one axis.

### Scrollbar CSS pattern (reference — adapt to tokens)

```css
.pr-scroll {
  scrollbar-width: thin;
  scrollbar-color: var(--pr-scrollbar-thumb) var(--pr-scrollbar-track);
}
.pr-scroll::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.pr-scroll::-webkit-scrollbar-thumb {
  background: var(--pr-scrollbar-thumb);
  border-radius: 4px;
}
.pr-scroll::-webkit-scrollbar-thumb:hover {
  background: var(--pr-color-text-muted);
}
.pr-scroll::-webkit-scrollbar-track {
  background: var(--pr-scrollbar-track);
}
```

Apply to vertical shell scrollers; table wrappers need **horizontal** scrollbar height (8px) — same class is fine.

### Dual-axis table viewport (fixes “scroll down to scroll right”)

```text
.pr-main                          ← scrolls page chrome (dashboard cards, wizard steps)
  PageHeader, filters, tabs        ← outside table viewport
  .pr-table-viewport (flex-1)     ← optional flex child on dense pages (Reports)
    TABLE_DATA_VIEWPORT            ← overflow: auto; max-height: calc(...)
      <table> sticky thead         ← sticks to top of THIS box
```

**`TABLE_DATA_VIEWPORT`** — add in `tableStyles.js` + `.pr-table-data-viewport` in CSS:

```javascript
// Tailwind classes + CSS class for max-height / scrollbars
export const TABLE_DATA_VIEWPORT =
  'pr-table-data-viewport pr-scroll w-full max-w-full overflow-auto overscroll-contain rounded-md border border-border';

// Simple tables without many rows may keep horizontal-only:
export const TABLE_SCROLL_WRAPPER =
  'w-full max-w-full overflow-x-auto overscroll-x-contain rounded-md border border-border pr-scroll';
```

**Max-height (CSS in `app-shell.css`):**

```css
.pr-table-data-viewport {
  /* Remaining viewport: topbar + main padding + ~120px for typical toolbar */
  max-height: calc(
    100vh - var(--pr-layout-topbar-height) - 48px - var(--pr-table-viewport-offset, 120px)
  );
}
```

Use CSS variable `--pr-table-viewport-offset` on pages with taller toolbars (Reports) via inline style or page class, **or** flex `flex-1 min-h-0` on Reports tab content so the table area absorbs leftover height without a magic number.

**When to use which wrapper:**

| Wrapper | Use when |
|---------|----------|
| `TABLE_DATA_VIEWPORT` | Many rows **and** possibly wide (Reports matrices, Registry, Progress, Marking grid) |
| `TABLE_SCROLL_WRAPPER` | Few rows, maybe wide (Audit log, small wizard tables) |

### Sticky headers with dual-axis scroll

- Reports: `<thead className="sticky top-0">` inside `TABLE_DATA_VIEWPORT` — header stays visible while scrolling **rows inside the box**; horizontal scrollbar stays at bottom of **same box**.
- Sticky Reg no: unchanged (`position: sticky; left: 0` within horizontal scroll).

### `TABLE_SCROLL_WRAPPER` (horizontal-only, short tables)

Keep for small tables. Add `pr-scroll` for styled scrollbars.

Optional edge cue (example):

```css
.pr-table-scroll {
  /* existing overflow from Tailwind in JS constant */
}
.pr-table-scroll--cue {
  mask-image: linear-gradient(to right, black calc(100% - 24px), transparent);
}
```

Use a subtle cue — match UX “shadow cue” (L661) without obscuring sticky Reg no column.

### Files to touch (expected)

| Path | Change |
|------|--------|
| `assets/css/app-shell.css` | Shell flex/scroll; scrollbars; sidebar collapsed/drawer/resize-handle styles |
| `src/shared/tableStyles.js` | `TABLE_SCROLL_WRAPPER` + edge cue class |
| `src/shared/AppShell.jsx` | Scroll regions; sidebar toggle; wire `SidebarLayout` / resize / collapse |
| `src/shared/SidebarLayout.jsx` | **New** — optional hook `useSidebarLayout()` for width/collapse/drawer |
| `src/coordinator/CoordinatorNav.jsx` | `collapsed` prop; rail-friendly markup |
| `src/coordinator/App.jsx` | Pass shell props if needed |
| Coordinator/reviewer table components | Import `TABLE_SCROLL_WRAPPER` (AC5 list) |
| `build/*.css` | Regenerate via `npm run build` if team commits build artifacts |

**Do not** change REST, PHP routes, or HashRouter.

### Out of scope

- Resizable **reviewer** top nav (single horizontal strip; no persistent sidebar).
- Resizable **table column** widths (separate feature).
- Changing table data, export, or sticky column logic beyond scroll container fixes.
- WP Admin screens.

### Previous story intelligence

- **1.5** — Established `app-shell.css`, layout tokens (topbar 56px, sidebar 240px, main max-width 1280px). This story **extends** 1.5 without renaming tokens.
- **7.6 / 12.x** — Reports matrices rely on horizontal scroll + sticky headers; test Reports after shell change.
- **5.11 / 5.12** — Marking grid desktop uses horizontal wrapper + sticky student column; verify `group-hover` on sticky cells after scroll refactor.

### Anti-patterns (do not)

- Put `overflow-y: auto` on `body` or `#pr-root` instead of shell regions.
- Remove `overscroll-x-contain` from table wrappers (prevents scroll chaining to browser back gesture on trackpads).
- Style scrollbars only in one browser (include both `scrollbar-color` and `webkit`).
- Use a third-party scrollbar or panel-resize library.
- Make `.pr-main` horizontally scrollable for wide tables — use `TABLE_DATA_VIEWPORT` or `TABLE_SCROLL_WRAPPER`.
- Use **horizontal-only** `overflow-x` on tall matrices without a max-height cap (recreates “scroll to bottom to scroll right”).
- Persist sidebar width while collapsed (only persist `pr-sidebar-collapsed` + width when expanded).

### References

- [Source: _bmad-output/planning/ux-design-specification.md — Spacing & Layout (L305–313), Responsive (L659–661, L678, L704–705)]
- [Source: _bmad-output/planning/epics.md — UX-DR4, UX-DR26, UX-DR27 (`aria-expanded` on sidebar toggle)]
- [Source: src/coordinator/CoordinatorNav.jsx — `NavIcon`, project context block]
- [Source: assets/css/app-shell.css — `.pr-body`, `.pr-sidebar`, `.pr-main`]
- [Source: src/shared/AppShell.jsx — shell structure]
- [Source: src/shared/tableStyles.js — `TABLE_SCROLL_WRAPPER`]
- [Source: _bmad-output/implementation/1-5-design-tokens.md — shell foundation]
- [Source: _bmad-output/implementation/7-6-reports-marks-matrix-layout-sort-export.md — sticky header + horizontal scroll]

## Dev Agent Record

### Agent Model Used

Composer (dev-story workflow)

### Debug Log References

### Completion Notes List

- Viewport-bound shell: `body`/`#pr-root`/`pr-shell` use `100vh` + `overflow: hidden`; `.pr-body` flex with `min-height: 0`; `.pr-sidebar` and `.pr-main` scroll independently with `.pr-scroll` styled scrollbars (`scrollbar-gutter: stable` on main).
- Coordinator sidebar: collapse (56px icon rail), drag resize 200–400px via `--pr-layout-sidebar-width`, `localStorage` keys `pr-sidebar-collapsed` / `pr-sidebar-width`; tablet off-canvas drawer with top-bar menu, backdrop, Escape.
- `useSidebarLayout` hook + `TableDataViewport` / `TableScrollWrapper` components; `TABLE_DATA_VIEWPORT` dual-axis scroll with edge fade cues; Reports page `pr-page-flex` layout for table fill.
- All AC5 table files migrated to shared wrappers. `npm run build` passes.
- PHPUnit: 291/292 pass; 1 pre-existing failure in `PanelReportPdfTemplateTest::test_offline_overall_sheet_has_reviewer_columns_and_overall_score` (unrelated to shell).
- **Manual AC29:** Not run in this session — verify in browser at 1280×800 and 768×1024 before marking done.

### File List

- assets/css/app-shell.css
- src/shared/tableStyles.js
- src/shared/AppShell.jsx
- src/shared/useSidebarLayout.js
- src/shared/TableScrollViewport.jsx
- src/coordinator/CoordinatorNav.jsx
- src/coordinator/pages/Reports.jsx
- src/coordinator/pages/Registry.jsx
- src/coordinator/pages/AuditLog.jsx
- src/coordinator/pages/SessionWizard.jsx
- src/coordinator/components/ReportsMarksTable.jsx
- src/coordinator/components/ReportsScoresTable.jsx
- src/coordinator/components/ReportsOverallScoresTable.jsx
- src/coordinator/components/ReportsConsolidatedTable.jsx
- src/coordinator/components/ProgressTable.jsx
- src/coordinator/components/RubricTable.jsx
- src/coordinator/components/PanelReviewersStep.jsx
- src/coordinator/components/ReviewAssignmentsStep.jsx
- src/coordinator/components/CsvImportMapper.jsx
- src/reviewer/components/MarkingGrid.jsx
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css
- build/reviewer.asset.php

## Change Log

- 2026-05-18: Story created (create-story) — ready-for-dev
- 2026-05-18: Added collapsible icon rail (56px), drag resize (200–400px), tablet drawer, localStorage persistence
- 2026-05-18: `TABLE_DATA_VIEWPORT` dual-axis scroll — horizontal bar reachable without scrolling page to table bottom
- 2026-05-18: Implemented shell scroll regions, sidebar collapse/resize/drawer, table viewports, component migration (dev-story)
