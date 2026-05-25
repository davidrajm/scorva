# Story 6.6: Progress — review-mark summary %, review/panel accordions, expand controls

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want **overall and per-review progress percentages based on review marks** (each reviewer–student obligation, including reviewers not yet linked to a user account), with **complete / in progress / not started counts**, **collapsible review and panel sections**, and **global expand/collapse controls**,
So that the progress page reflects partial marking, provisioning gaps, and who has not started — not only fully completed marks.

## Acceptance Criteria

1. **Review-mark grain for summary percentages (fixes Overall progress %)**
   - **Given** a confirmed review with panels, enrolled students, and **all assigned panel reviewers** (session roster via `assigned_reviewers_for_panel`, including rows **not yet linked** to a WordPress user)
   - **When** `ScoreService` builds `summary` for a review (and panel `summary` per panel — see AC2)
   - **Then** a **review mark** = one **assigned** panel reviewer’s obligation to mark one student on that panel for that review (linked or unlinked)
   - **And** `marks_total` = Σ over panels `{ students_assigned_to_panel × assigned_reviewers_on_panel }` — **every** roster reviewer on the panel counts, not only `user_id > 0`
   - **And** each review mark has exactly one status: `complete` | `in_progress` | `not_started` (mutually exclusive; must sum to `marks_total`)
   - **And** status rules per `(student, panel_reviewer)`:
     - **Unlinked** (`linked === false` or `user_id` absent): always `not_started` (cannot be complete or in progress until provisioned)
     - **Linked** + `MarkService::is_student_marking_complete(...)`: `complete`
     - **Linked** + not complete + **activity** on that student (any mark row for that reviewer, or attendance recorded for that student on this review): `in_progress`
     - **Linked** + not complete + no activity: `not_started`
   - **And** `marks_completed`, `marks_in_progress`, `marks_not_started` = counts by status; `marks_completed + marks_in_progress + marks_not_started === marks_total`
   - **And** `percent` = `round((marks_completed / marks_total) * 100, 1)` when `marks_total > 0`, else `0.0`
   - **And** `students_total` / `students_completed` on `summary` **remain** at student grain; **`percent` and radial arc** use mark grain
   - **And** example: 1 student, 2 **assigned** reviewers (A complete, B not started) → `marks_completed = 1`, `marks_not_started = 1`, `marks_total = 2`, `percent = 50.0`
   - **And** example: 1 student, 2 assigned reviewers (A in progress, B unlinked) → `marks_in_progress = 1`, `marks_not_started = 1`, `marks_total = 2`, `percent = 0.0`
   - **And** panel × reviewer **table rows** stay at **student grain** (`completed` / `total` = students fully marked by that reviewer); do not change row math
   - **And** prefer a shared helper on `MarkService` (e.g. `review_mark_status(...)`) so summary logic does not drift from row `status` rules

2. **Panel-level summary on REST payload**
   - **Given** progress grouped by review with `panels[]`
   - **When** each panel object is returned
   - **Then** it includes `summary`:
     ```json
     {
       "marks_completed": 0,
       "marks_in_progress": 0,
       "marks_not_started": 0,
       "marks_total": 0,
       "percent": 0.0,
       "students_total": 0
     }
     ```
   - **And** panel `summary` uses the same review-mark formula scoped to that panel only
   - **And** review-level `summary` adds `marks_completed`, `marks_in_progress`, `marks_not_started`, `marks_total` alongside existing `students_completed`, `students_total`, with `percent` from `marks_completed / marks_total`

3. **Overall progress — radial + in progress / not started breakdown**
   - **Given** `ReviewProgressSummary` renders the Overall progress section from `reviews[].summary`
   - **When** displayed
   - **Then** each review’s donut: arc and `%` use `summary.percent` (mark grain); center shows **`marks_completed / marks_total`**
   - **And** **below each donut** (or directly under the Overall progress heading for the row), a compact **mark status line** with `tabular-nums`:
     - **Complete:** `marks_completed`
     - **In progress:** `marks_in_progress`
     - **Not started:** `marks_not_started`
   - **And** use text labels + counts (not color-only); optional small `StatusChip` variants may reinforce but counts are required
   - **And** `aria-label` on each radial includes all four numbers (e.g. “Review 1: 24 complete, 40 in progress, 16 not started, of 80 review marks; 30 percent complete”)
   - **And** **session rollup** above the donut row: sum `marks_*` across all reviews in the response, show the same three counts + total for **Overall** (so coordinators see session-wide in progress / not started at a glance)
   - **And** `prefers-reduced-motion` unchanged on arcs

4. **Review accordion with completion status**
   - **Given** the coordinator opens `#/session/:id/progress`
   - **When** per-review detail renders below Overall progress
   - **Then** each review is a **collapsible section** (accordion): header = review label + mark-grain `summary.percent` + `marks_completed/marks_total` + compact `in progress` / `not started` counts from `summary` + `StatusChip` from aggregate mark state (`marks_completed === marks_total && marks_total > 0` → Complete; `marks_completed === 0 && marks_in_progress === 0` → Not started; else In progress)
   - **And** body contains panel accordions (AC5) when `panels.length > 0`; else flat `ProgressTable` for `review.rows` as today
   - **And** accordion is **closed by default**

5. **Panel accordion under each review**
   - **Given** a review with `panels[]`
   - **When** the review section is expanded
   - **Then** each panel is its own **nested collapsible**: header = panel name + panel `summary` percent + complete/in progress/not started counts + `StatusChip` from panel `summary` mark buckets (same rules as review header)
   - **And** body = existing `ProgressTable` for `panel.rows` (`showPanelColumn={false}`)
   - **And** panel accordion is **closed by default** even when parent review is open

6. **Global expand / collapse controls**
   - **Given** at least one review on the progress page
   - **When** the coordinator uses the toolbar above the review list
   - **Then** buttons are available:
     - **Expand reviews** — opens all review-level accordions; panel accordions stay closed
     - **Expand all** — opens all review and panel accordions
     - **Collapse all** — closes every review and panel accordion
   - **And** on initial page load, **all** accordions are closed (including after refresh)
   - **And** controls use existing `Button` secondary/text variants; keyboard operable; `aria-expanded` on each trigger

7. **Accessibility and motion**
   - **Given** WCAG 2.1 AA (NFR13, UX-DR27)
   - **When** accordions are implemented
   - **Then** use `<button>` triggers (not div-only click targets), `aria-expanded`, `aria-controls` linking header to panel id
   - **And** prefer a small shared `ProgressAccordion` in `src/coordinator/components/` (or `src/shared/components/` if reused later) over a new npm package
   - **And** radial animation still respects `prefers-reduced-motion` (unchanged from 6.5)

8. **Tests and exports**
   - **Given** `ScoreServiceTest` and panel progress export
   - **When** summary math changes
   - **Then** add/update tests:
     - Mark-grain review `summary`: 1 student, 2 **assigned** reviewers, one complete → `50.0%`, `marks_completed=1`, `marks_not_started=1`, `marks_total=2`
     - Unlinked roster reviewer on panel increases `marks_total` and `marks_not_started` (no user_id)
     - Linked reviewer with partial marks on one student → `marks_in_progress=1`
     - Panel `summary` scoped correctly in multi-panel fixture; bucket counts sum to `marks_total`
     - Adjust `test_review_summary_requires_all_panel_reviewers` — **student** `students_completed` expectations stay; **mark** `percent` is `50.0` when only one of two reviewers finished the same student
   - **And** `ReportQueryService::panel_progress` row export unchanged (student grain per reviewer row)
   - **And** PHPUnit green; `npm run build` after UI changes

## Tasks / Subtasks

- [x] **MarkService:** Add `review_mark_status()` (or equivalent) per student×assigned reviewer; unlinked → `not_started` (AC: 1)
- [x] **ScoreService:** Add `compute_mark_summary()` over **all** assigned reviewers; populate `marks_completed|in_progress|not_started|total` on review + panel `summary` (AC: 1, 2)
- [x] **Rest_Progress / types:** Document new fields in return docblock; no route change (AC: 2)
- [x] **ReviewProgressSummary:** Radial + per-review status line + session rollup (AC: 3)
- [x] **ProgressAccordion component:** Reusable collapsible with `defaultOpen`, `title`, `statusPercent`, optional `StatusChip` (AC: 4, 5, 7)
- [x] **SessionProgress:** Replace flat review sections with accordions; toolbar Expand reviews / Expand all / Collapse all with lifted state (AC: 4, 5, 6)
- [x] **Tests:** `ScoreServiceTest` mark-grain summary cases; adjust dual-reviewer summary test (AC: 8)

## Dev Notes

### What’s wrong today (why this story exists)

| Area | Current (6.5) | Desired |
|------|----------------|---------|
| **Overall / review %** | `students_completed / students_total` — student counts only when **every** panel reviewer finished that student | `marks_completed / marks_total` — each reviewer×student pair counts separately |
| **UX layout** | All reviews and panels always expanded | Review + panel **accordions**, default **closed** |
| **Scanability** | Long page with many tables | Toolbar to expand reviews only or everything |

Student **counts** in copy (e.g. “2 students assigned”) remain correct; coordinators reported **percent** in Overall progress is misleading when one of several reviewers has finished.

### Review-mark algorithm (canonical)

For review `R`, panel `P`:

```
assigned_reviewers = assigned_reviewers_for_panel(R, P)   // ALL roster rows, linked or not
student_ids        = students on panel P for R
marks_total        = count(student_ids) × count(assigned_reviewers)

For each (student s, assigned reviewer r):
  status = review_mark_status(session, R, s, r, criteria)
  // unlinked → not_started
  // linked + is_student_marking_complete → complete
  // linked + activity on (s,r) but not complete → in_progress
  // linked + no activity → not_started

marks_completed    = count(status == complete)
marks_in_progress  = count(status == in_progress)
marks_not_started  = count(status == not_started)
percent            = round(100 * marks_completed / marks_total, 1)
```

Review-level `summary.marks_*` = sum of panel contributions. Use the same `assigned_reviewers_for_panel` source as `build_progress_row` (session `pr_panel_reviewers` + legacy per-review additions).

**Activity** on a student×reviewer pair (for `in_progress`): any row from `list_for_student_review` for that pair, **or** attendance set for that student on the review (same signals as `reviewer_panel_marking_status`, scoped to one student).

**Do not change** `build_progress_row()` student `completed`/`total` — coordinator table still answers “how many students has Dr Smith finished?”

### REST shape (additive)

```json
{
  "review_id": 1,
  "review_label": "Review 1",
  "summary": {
    "students_completed": 0,
    "students_total": 10,
    "marks_completed": 24,
    "marks_in_progress": 40,
    "marks_not_started": 16,
    "marks_total": 80,
    "percent": 30.0
  },
  "panels": [
    {
      "panel_id": 1,
      "panel_name": "Panel A",
      "students_total": 5,
      "summary": {
        "marks_completed": 12,
        "marks_in_progress": 2,
        "marks_not_started": 1,
        "marks_total": 15,
        "percent": 80.0,
        "students_total": 5
      },
      "rows": [ "... unchanged row shape ..." ]
    }
  ]
}
```

### UI layout (desktop-first)

```
PageHeader — Marking progress
[Toolbar: Expand reviews | Expand all | Collapse all]

## Overall progress
Session: 48 complete · 62 in progress · 30 not started (140 marks)
[donuts — % + center complete/total; under each: Complete / In progress / Not started counts]

▶ Review 1 — 30% · 24/80 · 40 in progress · 16 not started · [In progress]     ← closed default
▶ Review 2 — …

(when Review 1 expanded)
  ▶ Panel A — 80% · 12/15 marks · [In progress]
  ▶ Panel B — …
  (when Panel A expanded)
    ProgressTable …
```

Use `border-border`, `text-muted`, existing `StatusChip` variants. Section headers: `text-lg` review, `text-base` panel (match current hierarchy in `SessionProgress.jsx`).

### Accordion state model

Lift state in `SessionProgress.jsx`:

```javascript
// Example shape — implement idiomatically
const [ openReviews, setOpenReviews ] = useState( () => new Set() );
const [ openPanels, setOpenPanels ] = useState( () => new Set() ); // keys: `${reviewId}-${panelId}`

// Expand reviews: set openReviews to all review ids; clear openPanels
// Expand all: all review ids + all panel keys
// Collapse all: empty both sets
```

Do not persist open state to localStorage (default closed on every visit per AC6).

### Files to touch

| File | Change |
|------|--------|
| `includes/services/MarkService.php` | `review_mark_status()` per student×reviewer |
| `includes/services/ScoreService.php` | Mark summary buckets on review + panel; all assigned reviewers in total |
| `src/coordinator/components/ReviewProgressSummary.jsx` | Donuts + session rollup + per-review status line |
| `src/coordinator/components/ProgressAccordion.jsx` | **New** collapsible section |
| `src/coordinator/pages/SessionProgress.jsx` | Accordions + toolbar |
| `tests/ScoreServiceTest.php` | Mark-grain summary assertions |

**Out of scope:** Dashboard `SessionCard` progress placeholder; panel progress CSV column meanings; changing row-level student grain; new chart libraries.

### Previous story intelligence (6.5)

- Grouped REST `reviews[]` with `panels[]` already exists — extend `summary`, do not flatten.
- `ReviewProgressSummary` SVG donut + `prefers-reduced-motion` — keep component, change data binding only.
- `test_review_summary_requires_all_panel_reviewers` validates **student** `students_completed`; keep those assertions, add parallel **mark** percent assertions.
- `SessionProgress.jsx` already nests `ProgressTable` per panel — wrap existing markup in accordions, minimal table changes.

### Architecture compliance

- Server-side percentages only (FR19, NFR7); UI displays server `percent`.
- No new npm chart or accordion libraries (NFR3).
- `tabular-nums` on counts and percents (UX-DR24).
- Keyboard + `aria-expanded` on collapsibles (UX-DR27, NFR13).

### Testing

- **Unit:** mark summary 50% with one of two reviewers complete; panel-scoped totals in `test_progress_includes_all_panels_and_review_student_totals`.
- **Manual:** session with 2 reviews, multiple panels → all closed on load; Expand reviews opens review headers only; Expand all shows tables; Collapse all resets; Overall donuts match mark % not student-all-complete %.

### References

- [Source: _bmad-output/implementation/6-5-progress-student-grain-dual-views.md — student summary % superseded for `percent` only]
- [Source: includes/services/ScoreService.php — `calculate_session_progress()`, `build_progress_row()`]
- [Source: src/coordinator/pages/SessionProgress.jsx — current layout]
- [Source: src/coordinator/components/ReviewProgressSummary.jsx — radial overall]
- [Source: _bmad-output/planning/epics.md — FR19, Epic 6, UX-DR12]
- [Source: _bmad-output/planning/ux-design-specification.md — Progress “control room”, accordion-friendly density]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Added `MarkService::review_mark_status()` for per student×reviewer obligation status (unlinked → not_started; linked uses complete / in_progress / not_started rules).
- Added `ScoreService::compute_mark_summary()`; review and panel `summary` now expose mark buckets; `percent` is mark-grain while `students_*` unchanged.
- `ReviewProgressSummary` shows session rollup, mark-grain donuts, and per-review Complete / In progress / Not started lines with accessible `aria-label`.
- New `ProgressAccordion` + `SessionProgress` accordions (default closed) with Expand reviews / Expand all / Collapse all toolbar.
- Extended `ScoreServiceTest` with mark-grain cases; adjusted dual-reviewer summary test (`percent` 50.0 when one reviewer finished).

### File List

- includes/services/MarkService.php
- includes/services/ScoreService.php
- includes/rest/class-rest-progress.php
- src/coordinator/components/ProgressAccordion.jsx
- src/coordinator/components/ReviewProgressSummary.jsx
- src/coordinator/pages/SessionProgress.jsx
- tests/ScoreServiceTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css

## Change Log

- 2026-05-17: Story 6.6 created — mark-grain summary %, review/panel accordions, expand/collapse toolbar; fixes misleading Overall progress % while keeping student counts and row grain.
- 2026-05-17: Refinement — `marks_total` includes all assigned panel reviewers (unlinked count as not started); summary adds `marks_in_progress` / `marks_not_started`; Overall section shows session rollup + per-review breakdown.
- 2026-05-17: Implemented mark-grain progress summary, accordions, expand controls, and tests (status → review).
