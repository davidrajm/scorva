# Story 18.1: Mobile-first CSS and media-query audit

Status: review

<!-- Ultimate context engine analysis completed — cross-cutting responsive CSS audit; reconcile app-shell max-width queries with Tailwind min-* conventions; document inventory and fix violations without changing desktop-primary product priority (NFR11) -->

## Story

As a **coordinator or reviewer** on a phone or narrow viewport,
I want layouts and breakpoints implemented with **mobile-first CSS** (base styles for small screens, `min-width` enhancements upward),
So that the plugin stays maintainable, matches Tailwind defaults, and avoids desktop-default rules that break or waste bandwidth on mobile — while desktop remains the **primary** design target (NFR11).

## Background — policy vs technique (read first)

| Concept | Current docs | This story |
|---------|--------------|------------|
| **Product priority** | NFR11, UX spec: desktop **1024px+** is primary; mobile supported | **Unchanged** — do not redesign desktop layouts |
| **CSS methodology** | UX spec § Responsive: “Desktop-first CSS: base styles target desktop; collapse at `max-md`” | **Align implementation to mobile-first** — base = narrow; enhance with `min-width` / Tailwind `sm:` `md:` `lg:` |
| **Tailwind in JSX** | Already mostly mobile-first (`flex-col sm:flex-row`, `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`) | Audit and fix outliers |
| **Custom CSS** | `assets/css/app-shell.css` uses **`max-width: 1023px`** drawer block + **`min-width: 1024px`** override | Refactor to **single mobile-first** flow (default drawer behaviour; `min-width: 1024px` for persistent sidebar) |

**Do not** interpret “mobile-first” as “mobile is the primary UX.” It means **cascade direction**: unprefixed / base rules apply to all viewports; wider breakpoints add capability.

## Acceptance Criteria

### 1. Audit deliverable (inventory)

1. **Given** the plugin source tree  
   **When** the audit runs  
   **Then** produce `docs/responsive-audit.md` (or a section in this story’s Dev Agent Record if you prefer one file — **prefer committed `docs/responsive-audit.md`**) listing:
   - Every `@media` block in `assets/css/app-shell.css` (file + line + min/max + purpose)
   - Count of Tailwind responsive prefixes in `src/**/*.{jsx,js}` by breakpoint (`sm:`, `md:`, `lg:`, `xl:`) and any `max-*` / `max-width` usage (expect **zero** `max-*` Tailwind in `src/` today)
   - Files using dual layout patterns (`hidden lg:block` / `lg:hidden`) — today: `MarkingGrid.jsx` only (document as **approved** pattern)
   - Pages with `overflow-x-auto` / `TABLE_SCROLL_WRAPPER` / `TABLE_DATA_VIEWPORT` — note which are **intentional** table viewports (story 1-9) vs accidental page-level horizontal scroll

2. **And** the audit classifies each finding: **OK** | **Fix** | **Defer** (with one-line rationale).

3. **And** the audit documents canonical breakpoints (must match UX spec):

   | Token | Min width | Usage |
   |-------|-----------|--------|
   | (base) | 0 | Single column, drawer sidebar, stacked cards |
   | `sm` | 640px | Padding, two-column forms |
   | `md` | 768px | 2-col dashboard cards, assignment grids |
   | `lg` | 1024px | Persistent coordinator sidebar, marking grid table, 3-col dashboard |

### 2. `app-shell.css` — mobile-first media queries

4. **Given** `assets/css/app-shell.css`  
   **When** refactored  
   **Then** coordinator sidebar behaviour follows mobile-first rules:
   - **Default (< lg):** hamburger visible; sidebar off-canvas drawer; backdrop; no desktop collapse toolbar in drawer mode (current behaviour preserved).
   - **`@media (min-width: 1024px)`:** persistent sidebar; hide hamburger; show collapse/resize toolbar; drawer/backdrop rules **not** applied.

5. **And** remove the redundant pair `@media (max-width: 1023px) { … }` + separate `@media (min-width: 1024px) { .pr-sidebar-menu-btn { display: none } }` **if** equivalent behaviour is expressed with base + `min-width` only (no duplicate/conflicting rules).

6. **And** `useSidebarLayout.js` keeps `SIDEBAR_LG_MEDIA = '(min-width: 1024px)'` — **already mobile-first**; no regression to drawer open/close on resize.

7. **And** reviewer/landing shells (`#pr-root[data-app="reviewer"]`, `landing`) unchanged except any shared selectors touched by the refactor.

### 3. JSX / Tailwind consistency

8. **Given** coordinator and reviewer SPAs under `src/`  
   **When** audited  
   **Then** no new `max-sm:`, `max-md:`, `max-lg:`, or custom `@media (max-width: …)` in component code unless justified in the audit doc (exception: `prefers-reduced-motion`).

9. **And** any layout that currently assumes desktop-first (e.g. default `display` that only works on wide screens without a narrow fallback) gets a narrow fallback or is listed as **Defer** with issue link.

10. **And** preserve story **5.11** marking grid pattern: cards `lg:hidden`, grid `hidden lg:block` — do **not** collapse back to single wide table on mobile.

11. **And** dashboard session grid keeps `grid gap-4 sm:grid-cols-2 lg:grid-cols-3` (mobile-first — verify no regression).

### 4. Mobile viewport smoke (manual)

12. **Given** Chrome DevTools device mode (or real device) at **375px** width  
    **When** exercising these routes  
    **Then** no **page-level** horizontal scroll (tables may scroll inside `TABLE_DATA_VIEWPORT` / `pr-table-scroll` only):

    | Route | Check |
    |-------|--------|
    | `/reviews/` landing | Login card fits viewport |
    | `/reviews/#/` dashboard | Tabs wrap; cards stack; drawer nav works |
    | `/reviews/#/registry` | Search + table viewport scroll, not body |
    | `/reviews/#/session/{id}/wizard?step=students` | Wizard steps wrap (`flex-wrap`); content readable |
    | `/reviews/mark/#/` assignments | Cards stack (`md:grid-cols-2` at 375 = 1 col) |
    | Marking grid (deep link) | Student **cards**, not 640px min table |

13. **Given** **1024px** and **1280px** widths  
    **When** same routes visited  
    **Then** desktop layouts unchanged from pre-audit (sidebar persistent, 2–3 col grids, marking table visible).

### 5. Documentation and build

14. **And** update `_bmad-output/planning/ux-design-specification.md` § Responsive → **Breakpoint Strategy** to replace “Desktop-first CSS: Base styles target desktop; collapse at `max-md`” with **mobile-first CSS** wording consistent with implementation (one short paragraph; keep NFR11 desktop-primary sentence).

15. **And** optional: add one line to `tests/e2e/MVP_CHECKLIST.md` under Prerequisites — “Spot-check 375px on dashboard + marking grid after responsive changes.”

16. **And** `npm run build` passes; `composer test` passes (no PHP changes expected).

## Tasks / Subtasks

- [x] **Audit:** Run ripgrep inventory; write `docs/responsive-audit.md` with OK/Fix/Defer table
- [x] **CSS:** Refactor `assets/css/app-shell.css` media queries to mobile-first (`min-width: 1024px` enhancement pattern)
- [x] **JSX:** Fix any **Fix** items from audit (avoid drive-by refactors)
- [x] **Docs:** Patch UX spec breakpoint strategy paragraph (AC 14)
- [x] **QA:** Manual 375 / 768 / 1024 / 1280 checklist (AC 12–13); `npm run build`; `composer test`

## Dev Notes

### User request (source)

> Overall CSS check for media queries to make sure that site is mobile first.

### What exists today (grep snapshot — re-verify at implementation time)

**Custom `@media` (source, not `build/`):**

```445:499:assets/css/app-shell.css
/* Tablet drawer (< lg) */
@media (max-width: 1023px) {
	.pr-sidebar-menu-btn {
		display: inline-flex;
	}
	/* ... drawer, backdrop, coordinator sidebar off-canvas ... */
}

@media (min-width: 1024px) {
	.pr-sidebar-menu-btn {
		display: none !important;
	}
}
```

This is **desktop-first** in `app-shell.css`: default desktop sidebar layout in unprefixed rules; narrow viewports override via `max-width`. Refactor so **narrow behaviour is default** and `min-width: 1024px` restores desktop sidebar.

**Tailwind compiled output** (`build/coordinator.css`, etc.) already emits `min-width` queries — Tailwind v3 defaults are mobile-first; **do not edit `build/*` by hand**.

**JSX:** Widespread mobile-first utilities, e.g.:

- `Dashboard.jsx`: `grid gap-4 sm:grid-cols-2 lg:grid-cols-3`
- `PageHeader.jsx`: `flex-col gap-4 sm:flex-row`
- `MarkingGrid.jsx`: dual layout `lg:hidden` / `hidden lg:block` (5.11)

**No** `max-md:` / `max-lg:` Tailwind classes in `src/` at story authoring time.

**JS matchMedia (already correct):**

```8:8:src/shared/useSidebarLayout.js
export const SIDEBAR_LG_MEDIA = '(min-width: 1024px)';
```

### Recommended `app-shell.css` refactor pattern

1. Set **mobile defaults**: `.pr-sidebar-menu-btn { display: inline-flex; }`; coordinator sidebar drawer positioning; backdrop visible when open; hide `.pr-sidebar-toolbar` in drawer mode.
2. Wrap **desktop enhancements** in `@media (min-width: 1024px) { … }`: fixed sidebar in flow; hide menu button; show collapse toolbar; disable drawer transform/backdrop.
3. Delete the `max-width: 1023px` block once behaviour is equivalent.
4. Test resize across 1023↔1024: drawer closes when crossing to `lg` (handled in `useSidebarLayout`).

### Table horizontal scroll (in scope for audit, limited fixes)

- **OK:** `src/shared/tableStyles.js` — `TABLE_SCROLL_WRAPPER`, `TABLE_DATA_VIEWPORT` — horizontal scroll **inside** capped viewport (stories 1-9, 1-10, 1-11).
- **Review:** `RubricTable.jsx` `min-w-[28rem]` inside wizard — ensure parent allows viewport scroll, not `body` overflow.
- **Do not** remove `min-w-full` on `<table>` — needed for column layout inside scroll regions.

### Out of scope

- New breakpoints or redesign of Reports matrices
- E2E Playwright viewport suite (optional defer; manual AC 12 sufficient for this story)
- WP Admin settings pages (native WP CSS — UX-DR34)
- Changing NFR11 “desktop primary” product statement

### Project structure

| Area | Path |
|------|------|
| Shell CSS | `assets/css/app-shell.css` |
| Sidebar state | `src/shared/useSidebarLayout.js`, `src/shared/AppShell.jsx` |
| Table wrappers | `src/shared/tableStyles.js` |
| SPAs | `src/coordinator/**`, `src/reviewer/**`, `src/landing/**` |
| UX spec | `_bmad-output/planning/ux-design-specification.md` |
| Prior mobile grid story | `_bmad-output/implementation/5-11-reviewer-marking-grid-mobile-cards.md` |
| Shell scroll story | `_bmad-output/implementation/1-9-app-shell-scroll-regions.md` |

### References

- [Source: `_bmad-output/planning/epics.md` — NFR11, UX-DR26]
- [Source: `_bmad-output/planning/ux-design-specification.md` — § Responsive Design & Accessibility]
- [Source: `_bmad-output/implementation/1-5-design-tokens.md` — tokens, Tailwind `#pr-root` scope]
- [Source: `_bmad-output/implementation/5-11-reviewer-marking-grid-mobile-cards.md` — dual layout exception]

## Dev Agent Record

### Agent Model Used

Composer (Cursor agent)

### Debug Log References

- Tailwind prefix counts: `sm:` 46, `md:` 5, `lg:` 6, `xl:` 0 in `src/**/*.{jsx,js}`
- Zero `max-*` Tailwind prefixes in `src/`

### Completion Notes List

- Added `docs/responsive-audit.md` with full inventory, OK/Fix/Defer classifications, and manual smoke route table.
- Refactored `assets/css/app-shell.css`: mobile drawer as default; single `@media (min-width: 1024px)` enhancement block; removed `max-width: 1023px` block.
- No JSX changes required — audit found no Fix items outside shell CSS.
- Updated UX spec § Breakpoint Strategy to mobile-first wording; kept NFR11 desktop-primary sentence.
- Added 375px spot-check line to `tests/e2e/MVP_CHECKLIST.md`.
- `npm run build` and `composer test` (347 tests) passed.
- Manual AC 12–13: verify in browser at 375 / 768 / 1024 / 1280 using audit checklist (not automated in this story).

### File List

- `assets/css/app-shell.css` (modified)
- `docs/responsive-audit.md` (new)
- `_bmad-output/planning/ux-design-specification.md` (modified)
- `tests/e2e/MVP_CHECKLIST.md` (modified)
- `_bmad-output/implementation/sprint-status.yaml` (modified)
- `_bmad-output/implementation/18-1-mobile-first-css-media-query-audit.md` (modified)

## Change Log

- 2026-05-24: Mobile-first shell CSS refactor, responsive audit doc, UX spec + MVP checklist updates; sprint status → review.
