# Story 6.5: Progress — student-grain counts, per-review tables, overall radial summary

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want marking progress measured by **students fully marked** (not individual rubric scores), with a **per-review detail table** and an **overall summary** showing each review’s student completion,
So that I can chase reviewers and see which review rounds are lagging before session close.

## Acceptance Criteria

1. **Student grain for reviewer rows (replaces rubric-score grain from 6.3)**
   - **Given** a confirmed review with rubric criteria and panel assignments
   - **When** `ScoreService` calculates progress for a panel × reviewer row
   - **Then** `total` = count of **students** assigned to that reviewer’s panel for that review (enrolled + panel assignment / review student panel)
   - **And** `completed` = count of those students where marking is **complete** for that reviewer on that review, using the **same rules as freeze** (`MarkService::student_marks_complete` semantics):
     - `attendance_status = absent` → counts as complete (no numeric scores required)
     - `attendance_status = present` → every criterion has a valid numeric score in mark rows (draft or submitted status for scores; see AC2 for “submitted” display)
   - **And** `percent` = `round((completed / total) * 100, 1)` when `total > 0`, else `0.0`
   - **And** fixture where reviewer A submitted both criteria for **one** student with **two** criteria shows `completed = 1`, `total = 1`, `percent = 100.0` (not `2/2`)

2. **Submitted vs complete (coordinator-facing label)**
   - **Given** reviewers may save drafts until they **Freeze scores** (story 5.6)
   - **When** progress is shown in the UI
   - **Then** column label is **Students complete** (or **Students marked**) with `completed / total` at student grain
   - **And** page copy states that a student counts complete when that reviewer has finished marking them (present: all criteria scored; absent: attendance recorded), matching freeze validation
   - **And** optional: expose `students_submitted` separately only if product needs frozen-only counts — **default: use “complete” (freeze-ready), not per-criterion submitted count**

3. **REST response grouped by review**
   - **Given** `GET /project-reviews/v1/sessions/{session_id}/progress`
   - **When** the session has multiple confirmed reviews
   - **Then** response shape is:
     ```json
     {
       "reviews": [
         {
           "review_id": 1,
           "review_label": "Review 1",
           "summary": {
             "students_completed": 0,
             "students_total": 0,
             "percent": 0.0
           },
           "rows": [
             {
               "panel_id": 1,
               "panel_name": "Panel A",
               "reviewer_user_id": 2,
               "reviewer_name": "Dr Smith",
               "completed": 0,
               "total": 0,
               "percent": 0.0
             }
           ]
         }
       ]
     }
     ```
   - **And** `summary` for each review = students who have **completed that review** (every assigned panel reviewer has `student_marks_complete` for that student on that review) / students enrolled and assigned to a panel for that review
   - **And** only **confirmed** reviews appear (unchanged from current service)
   - **And** maintain backward compatibility: either bump API version in path **or** accept that coordinator SPA is updated in same story (no external consumers yet)

4. **Overall summary — radial (donut) per review**
   - **Given** the coordinator opens `#/session/:id/progress`
   - **When** the page loads
   - **Then** an **Overall progress** section appears **above** per-review tables
   - **And** for each confirmed review, a compact **radial progress** indicator shows `summary.percent` with center label `completed / total` students (e.g. `12/40`)
   - **And** review label is visible under or beside each chart
   - **And** charts are implemented with **SVG or CSS** (no new chart npm dependency); `role="img"` + `aria-label` describing review name and percent
   - **And** animation respects `prefers-reduced-motion` (static arc when reduced motion)

5. **Per-review progress tables**
   - **Given** multiple reviews
   - **When** the progress page renders
   - **Then** each review has its own subsection: heading = review label, body = `ProgressTable` with that review’s `rows` only
   - **And** sticky table header, `tabular-nums`, existing `ProgressBar` column (UX-DR12)
   - **And** empty state when no rows: “No panel assignments with enrolled students for this review.”

6. **Exports and tests stay aligned**
   - **Given** `ReportQueryService::panel_progress` and `ScoreServiceTest`
   - **When** progress calculation changes
   - **Then** panel progress export columns remain Panel, Reviewer, Completed, Total, Percent but counts use **student grain**; add **Review** column if multiple reviews per export row (or one sheet section per review — prefer **Review** column for flat export)
   - **And** rename test `test_progress_percent_matches_rubric_score_grain` → `test_progress_percent_matches_student_grain` with expectations `1/1` for existing fixture
   - **And** add test for review `summary` when two reviewers must both complete one student
   - **And** PHPUnit green; `npm run build` after UI changes

## Tasks / Subtasks

- [x] **ScoreService:** Refactor `calculate_session_progress()` → return structured `reviews[]` with `summary` + `rows`; student-level `completed`/`total` per panel×reviewer; review-level summary (AC: 1, 3)
- [x] **Shared completion logic:** Extract or delegate to same rules as `MarkService::student_marks_complete()` (avoid drift); document in method docblock (AC: 1)
- [x] **Rest_Progress:** Return new JSON shape; update any REST tests (AC: 3)
- [x] **UI — `ReviewProgressSummary.jsx`:** Radial/donut per review from `reviews[].summary` (AC: 4)
- [x] **UI — `SessionProgress.jsx`:** Overall section + map `reviews[]` to titled `ProgressTable` blocks (AC: 5)
- [x] **UI — `ProgressTable.jsx`:** Column “Students complete”; remove sr-only rubric-score caption (AC: 2)
- [x] **ReportQueryService::panel_progress:** Student grain + Review column (AC: 6)
- [x] **Tests:** `ScoreServiceTest`, export test if present (AC: 6)

## Dev Notes

### What’s wrong today (why this story exists)

| Area | Current behavior | Desired behavior |
|------|------------------|------------------|
| **Counting unit** | Each submitted **criterion mark** increments `completed` (`total = students × criteria`) | Each **student** counts once when that reviewer finished that student for the review |
| **UX copy** | “Scores submitted”; caption says rubric criterion scores | “Students complete”; student-oriented coordinator language |
| **Layout** | Single flat table; `review_id` / `review_label` exist on rows but UI ignores them | **Overall** radials per review + **one table per review** |
| **Mental model** | Matches export grain of raw marks | Matches **freeze** and chasing “how many students left?” |

Reference implementation today:

```326:340:includes/services/ScoreService.php
                $total_tasks = count($student_ids) * $required_per_student;
                $completed = 0;
                foreach ($student_ids as $student_id) {
                    $marks = $this->marks->list_for_student_review(
                        $session_id,
                        $review_id,
                        $student_id,
                        $user_id
                    );
                    foreach ($marks as $mark) {
                        if ((string) ($mark['status'] ?? '') === MarkRepository::STATUS_SUBMITTED) {
                            ++$completed;
                        }
                    }
                }
```

```21:27:src/coordinator/components/ProgressTable.jsx
						<th className="px-4 py-3 font-medium tabular-nums">
							Scores submitted
						</th>
```

Story **6.3** intentionally shipped rubric-score grain; this story **supersedes** that counting model for coordinator progress (FR19). Do not remove criterion-level data from `pr_marks` or rubric exports.

### Review-level summary algorithm

For review `R`:

- `students_total` = distinct enrolled students with `panel_id > 0` for `R` (same enrolment loop as progress rows).
- `students_completed` = students where **for every** `list_panel_reviewers(R)` row for that student’s panel, `student_marks_complete(session, R, student, reviewer, criteria)` is true.

Edge cases:

- Student with no panel → exclude from totals (same as today).
- Review with zero criteria → skip review (today: `continue`).
- Single reviewer on panel → summary equals that reviewer’s row percent only when all students complete.

### UI layout (desktop-first)

```
PageHeader — Marking progress
[Notice if error]

## Overall progress
┌─────────┐ ┌─────────┐ ┌─────────┐
│ donut   │ │ donut   │ │ donut   │   ← one per confirmed review
│ 12/40   │ │  8/40   │ │  0/40   │
│Review 1 │ │Review 2 │ │Review 3 │
└─────────┘ └─────────┘ └─────────┘

## Review 1 — {label}
ProgressTable …

## Review 2 — {label}
ProgressTable …

## Score breakdown (unchanged from 6.4)
…
```

Use existing tokens: `text-muted`, `border-border`, `bg-primary` for arc fill, `bg-border` for track.

### Radial component sketch (no new deps)

- `src/coordinator/components/ReviewProgressSummary.jsx` (or `src/shared/components/RadialProgress.jsx` if reused on dashboard later).
- Props: `label`, `completed`, `total`, `percent`.
- SVG: `<circle>` track + stroke-dasharray arc; `stroke-dashoffset` from percent; no transition when `prefers-reduced-motion: reduce`.

### Files to touch

| File | Change |
|------|--------|
| `includes/services/ScoreService.php` | Student grain + grouped return type |
| `includes/services/MarkService.php` | Optional: public `is_student_marking_complete()` used by ScoreService |
| `includes/rest/class-rest-progress.php` | New response envelope |
| `includes/services/ReportQueryService.php` | `panel_progress` columns/grain |
| `src/coordinator/pages/SessionProgress.jsx` | Load new shape; two sections |
| `src/coordinator/components/ProgressTable.jsx` | Labels |
| `src/coordinator/components/ReviewProgressSummary.jsx` | **New** |
| `tests/ScoreServiceTest.php` | Updated expectations + summary test |

### Prerequisites

- Stories 6.1–6.4 (ScoreService, progress route, ProgressTable shell, ScoreBreakdown section).
- Story 5.7 attendance rules for “complete” absent students.
- Story 5.6 freeze/submitted semantics understood; progress “complete” aligns with freeze validation, not only `status=submitted` per criterion.

### Previous story intelligence

- **6.3** shipped rubric-score grain and test `completed=2,total=2` — **must change** to `1/1` for same fixture.
- **6.4** `ScoreBreakdown` below progress on same page — do not regress student picker or scores fetch.
- **5.7** absent students count complete without scores — progress must not penalize panels with absences.

### Architecture compliance

- Server-side percentages only (FR19, NFR7); UI displays server numbers.
- No new npm chart libraries (NFR3 bundle discipline).
- `tabular-nums` on counts and percents (UX-DR24).
- `prefers-reduced-motion` on arc animations (NFR14, UX-DR12).

### Testing

- `ScoreServiceTest::test_progress_percent_matches_student_grain` — 1 student, 2 criteria, both submitted → `1/1` not `2/2`.
- New test: 2 students, 1 complete → `1/2`, `50.0`.
- New test: review summary needs 2 reviewers on same panel, 1 student — complete only when both reviewers complete.
- Manual: session with 2+ confirmed reviews → overall donuts + separate tables; verify copy.

### References

- [Source: _bmad-output/implementation/6-3-progress-table-ui.md — superseded counting model]
- [Source: _bmad-output/implementation/6-4-score-breakdown.md — same page layout]
- [Source: _bmad-output/implementation/5-6-reviewer-marking-grid-freeze.md — freeze / submitted]
- [Source: _bmad-output/implementation/5-7-student-attendance-marking.md — absent = complete]
- [Source: includes/services/MarkService.php — `student_marks_complete()`]
- [Source: _bmad-output/planning/epics.md — FR19, Epic 6]
- [Source: _bmad-output/planning/ux-design-specification.md — Progress “control room”, UX-DR12]

## Dev Agent Record

### Agent Model Used

Composer

### Debug Log References

### Completion Notes List

- Refactored `ScoreService::calculate_session_progress()` to student grain grouped by review with per-review `summary` and panel×reviewer `rows`.
- Added `MarkService::is_student_marking_complete()` delegating to freeze-ready `student_marks_complete()` rules.
- REST progress endpoint returns `{ reviews: [...] }`; coordinator SPA updated to match.
- New `ReviewProgressSummary` SVG radial charts with `prefers-reduced-motion` support; per-review `ProgressTable` sections on progress page.
- Panel progress export adds Review column and uses student-grain counts.
- Tests: renamed student-grain test, added two-student and review-summary coverage. PHPUnit 172 OK; `npm run build` OK.

### File List

- includes/services/ScoreService.php
- includes/services/MarkService.php
- includes/rest/class-rest-progress.php
- includes/services/ReportQueryService.php
- src/coordinator/components/ReviewProgressSummary.jsx
- src/coordinator/components/ProgressTable.jsx
- src/coordinator/pages/SessionProgress.jsx
- tests/ScoreServiceTest.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css

## Change Log

- 2026-05-17: Story 6.5 — student-grain progress, grouped REST, radial overall summary, per-review tables, export Review column.
