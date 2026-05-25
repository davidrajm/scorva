# Story 5.13: Assignment card — co-reviewer chips

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer** browsing my assignments,
I want to see who else is marking on the same panel,
So that I know my co-reviewers before opening the student list.

## Acceptance Criteria

1. **Co-reviewer chips on assignment cards**
   - **Given** the assignments home (`MarkAssignments.jsx`) with markable assignments
   - **When** an `AssignmentCard` renders and the API returns one or more **other** reviewers on that panel for that review round
   - **Then** directly **below** the panel name row (panel icon + `panelName`, lines ~25–28 in `AssignmentCard.jsx`) a row shows those reviewers as **chips** (flex-wrap, `gap-1.5`)
   - **And** each chip shows the reviewer **display name** from the session panel roster (`pr_panel_reviewers.name`, resolved the same way as coordinator assignment payloads)
   - **And** the **signed-in reviewer is excluded** from the chip list (they already know they are on this panel)
   - **And** chips use neutral pill styling consistent with Direction 1 — e.g. `inline-flex items-center rounded-md border border-border bg-surface px-2 py-0.5 text-xs text-text-muted` (do **not** reuse status semantic variants like `draft` / `flagged` on `StatusChip`)
   - **And** long names truncate with `max-w-[10rem] truncate` and `title` tooltip for full name
   - **And** when there are **no** other reviewers on the panel (solo assignment), the chip row is **omitted** — no empty label, no “None”

2. **REST payload — scoped co-reviewers per assignment**
   - **Given** `GET /project-reviews/v1/reviewer/assignments`
   - **When** the response builds each assignment object
   - **Then** each assignment includes `co_reviewers`: array of `{ name: string }` (optional `user_id` for tests/debug; not required in UI)
   - **And** names come from **per-review** panel reviewer rows (`pr_review_panel_reviewers` via `ReviewAssignmentRepository::list_panel_reviewers`) filtered to `panel_id`, excluding `user_id === current_user_id`
   - **And** display names resolve via `PanelRepository::list_reviewers($panel_id)` matched on `user_id` (mirror `Rest_Review_Assignments::get_assignments` reviewer name resolution — see `includes/rest/class-rest-review-assignments.php` ~384–401)
   - **And** reviewers with missing roster name fall back to empty string filtered out (do not emit chips with blank labels)
   - **And** sort `co_reviewers` alphabetically by `name` (case-insensitive) for stable UI
   - **And** no extra REST round-trip from the assignments page (extend existing list endpoint only)

3. **Security and scope**
   - **Given** assignment list is already scoped to panels where the current user is on `pr_review_panel_reviewers` for that `review_id`
   - **When** co-reviewers are loaded
   - **Then** only reviewers on **that** `review_id` + `panel_id` are returned — never reviewers from other panels or reviews
   - **And** capability remains `PR_CAP_ENTER_MARKS` on the existing route
   - **And** blocked / unavailable assignments in the Notice list are **out of scope** (they do not use `AssignmentCard`)

4. **Accessibility and layout**
   - **Given** co-reviewer chips render
   - **When** assistive tech reads the card
   - **Then** the chip group has an accessible name — e.g. wrapper with `aria-label="Co-reviewers on this panel"` or visible muted label “Co-reviewers” (`text-xs text-text-muted`) before the chip list
   - **And** chips are not interactive (no buttons/links); the card remains one `Link`
   - **And** chip row wraps on narrow cards without horizontal overflow

5. **Tests and build**
   - **And** `RestReviewerAssignmentsTest` adds `test_list_assignments_includes_co_reviewers_excluding_self`:
     - Two reviewers on same panel + review (`upsert_panel_reviewer` / `panels->add_reviewer` + sync or direct upsert)
     - Caller is reviewer A → `co_reviewers` contains B’s name only
   - **And** solo reviewer → `co_reviewers` is `[]`
   - **And** run `composer test` and `npm run build`

## Tasks / Subtasks

- [x] **REST:** In `Rest_Reviewer_Assignments::list_assignments`, for each assignment row compute `co_reviewers` using `list_panel_reviewers` + `PanelRepository::list_reviewers` name lookup; exclude current user (AC: 2, 3)
- [x] **Optional helper:** Extract private `resolve_co_reviewers(int $review_id, int $panel_id, int $exclude_user_id): list<array{name: string}>` on REST class or small method on `ReviewAssignmentRepository` if it keeps `list_assignments` readable (AC: 2)
- [x] **UI:** Extend `AssignmentCard` with `coReviewers` prop; render chip row below panel line (AC: 1, 4)
- [x] **Wire:** Pass `a.co_reviewers` from `MarkAssignments.jsx` (AC: 1)
- [x] **Tests:** `RestReviewerAssignmentsTest` coverage (AC: 5)
- [x] Run `composer test` and `npm run build`

## Dev Notes

### User request (source)

> Below the panel name row in `AssignmentCard.jsx` (lines 25–28), add the list of **co-reviewers for the same panel**, as **chips**.

### What exists today

**Assignment card** — panel line only, no roster context:

```25:28:src/reviewer/components/AssignmentCard.jsx
						<p className="mt-2 flex items-center gap-1.5 text-sm text-text-muted">
							<Icon name="panel" className="h-4 w-4 shrink-0" />
							<span className="truncate">{panelName}</span>
						</p>
```

**Assignments API** — no co-reviewer field:

```113:124:includes/rest/class-rest-reviewer-assignments.php
                foreach ($panel_rows as $panel) {
                    $assignments[] = [
                        'session_id' => $session_id,
                        'session_title' => (string) ($session['title'] ?? ''),
                        'review_id' => $review_id,
                        'review_label' => (string) ($review['label'] ?? ''),
                        'panel_id' => (int) ($panel['id'] ?? 0),
                        'panel_name' => (string) ($panel['name'] ?? ''),
                        'markable' => $markable,
                        'blocked_reason' => $blocked_reason,
                    ];
                }
```

**Authoritative reviewer roster per review** — `ReviewAssignmentRepository::list_panel_reviewers($review_id)` (table `pr_review_panel_reviewers`). Session template `pr_panel_reviewers` supplies human-readable `name` via `PanelRepository::list_reviewers($panel_id)`.

**Prior art for name resolution** — coordinator assignments GET already maps `user_id` → roster `name`:

```384:401:includes/rest/class-rest-review-assignments.php
        foreach ($assignments->list_panel_reviewers($review_id) as $row) {
            $panel_id = (int) ($row['panel_id'] ?? 0);
            $user_id = (int) ($row['user_id'] ?? 0);
            $name = '';
            foreach ($panels->list_reviewers($panel_id) as $session_reviewer) {
                if ((int) ($session_reviewer['user_id'] ?? 0) === $user_id) {
                    $name = (string) ($session_reviewer['name'] ?? '');
                    break;
                }
            }
            $reviewer_rows[] = [
                'panel_id' => $panel_id,
                ...
                'name' => $name,
```

Reuse this pattern — do **not** invent a new name source or query WordPress users directly (keeps parity with wizard roster names).

### Implementation sketch (REST)

Inside the `foreach ($panel_rows as $panel)` block, after you have `$review_id`, `$panel_id`, and `$user_id`:

```php
'co_reviewers' => self::co_reviewers_for_panel(
    $assignments_repo,
    $panels,
    $review_id,
    (int) ($panel['id'] ?? 0),
    $user_id
),
```

Pseudo-logic:

```
foreach list_panel_reviewers(review_id) where panel_id = X:
  if user_id == current_user: continue
  name = lookup from panel list_reviewers
  if name !== '': append { name }
sort by name
```

Performance: `list_assignments` already loops sessions × reviews × panels; co-reviewer lookup is O(reviewers on panel) per row — acceptable. If needed, memoize `list_panel_reviewers($review_id)` once per review iteration (not per panel) to avoid repeated SQL.

### UI sketch

```jsx
{ coReviewers?.length > 0 ? (
  <div className="mt-2" aria-label="Co-reviewers on this panel">
    <div className="flex flex-wrap gap-1.5">
      { coReviewers.map( ( r ) => (
        <span
          key={ r.user_id ?? r.name }
          className="inline-flex max-w-[10rem] truncate rounded-md border border-border bg-surface px-2 py-0.5 text-xs text-text-muted"
          title={ r.name }
        >
          { r.name }
        </span>
      ) ) }
    </div>
  </div>
) : null }
```

Use `user_id` in React `key` when present; fallback to `name` if solo edge cases.

Prop naming: `coReviewers` in JSX (camelCase); API `co_reviewers` (snake_case) — `api.js` returns JSON as-is.

### Architecture compliance

| Rule | Action |
|------|--------|
| NFR5 assignment scoping | Co-reviewers only for panel rows already returned to the user |
| No new routes | Extend `GET /reviewer/assignments` only |
| Per-review assignments (3-11) | Use `pr_review_panel_reviewers`, not session-only `panels_for_user` without review scope |
| UX Direction 1 | Neutral chips; card hover/focus from story 5-9 unchanged |
| Terminology (10-1) | User-facing copy may say “project” in descriptions; this story has no new session→project strings required |

### Critical files (touch list)

| File | Change |
|------|--------|
| `includes/rest/class-rest-reviewer-assignments.php` | Add `co_reviewers` to each assignment; helper for name resolution |
| `src/reviewer/components/AssignmentCard.jsx` | Chip row below panel |
| `src/reviewer/pages/MarkAssignments.jsx` | Pass `coReviewers={ a.co_reviewers ?? [] }` |
| `tests/RestReviewerAssignmentsTest.php` | `list_assignments` co-reviewer tests |

**Do not** change marking grid, freeze, or student list endpoints unless tests require fixture tweaks.

### Previous story intelligence (5.9, 5.11, 5.12)

- **5.9** introduced `AssignmentCard` structure (eyebrow, review title, panel line, chevron). This story **extends** that component only — do not regress card link, hover shadow, or grid layout (`md:grid-cols-2`).
- **5.11** mobile marking cards — unrelated; no change to `MarkingGrid`.
- **5.12** table hover tokens — chip row does not need row-hover helpers.

### Testing notes

`RestReviewerAssignmentsTest` setUp creates one reviewer on panel. For co-reviewer test:

1. Add second user id (e.g. `802`) with `panels->add_reviewer` on same `$panel_id`.
2. Ensure both appear in `pr_review_panel_reviewers` for `$review_id` (review create may auto-sync from session — verify with `ReviewAssignmentRepository::list_panel_reviewers` or call `sync_panel_reviewers_from_session` if test DB requires it).
3. Call `Rest_Reviewer_Assignments::list_assignments(new WP_REST_Request())` as user `801` → expect one co-reviewer name.

### References

- [Source: _bmad-output/implementation/5-9-reviewer-page-ui-polish.md — AssignmentCard]
- [Source: _bmad-output/implementation/3-11-per-review-assignments-marking-active.md — pr_review_panel_reviewers]
- [Source: includes/rest/class-rest-review-assignments.php — reviewer name resolution]
- [Source: includes/rest/class-rest-reviewer-assignments.php — list_assignments]
- [Source: src/reviewer/components/AssignmentCard.jsx]
- [Source: src/reviewer/pages/MarkAssignments.jsx]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Added `co_reviewers` to `GET /reviewer/assignments` via `co_reviewers_for_panel()` helper; memoizes `list_panel_reviewers` per review; excludes current user; sorts names case-insensitively.
- `AssignmentCard` renders neutral co-reviewer chips below panel name with `aria-label`; row omitted when empty.
- `MarkAssignments` passes `coReviewers={ a.co_reviewers ?? [] }`.
- `test_list_assignments_includes_co_reviewers_excluding_self` covers solo (`[]`) and two-reviewer panel.
- PHPUnit 183 tests OK; `npm run build` OK.

### File List

- includes/rest/class-rest-reviewer-assignments.php
- src/reviewer/components/AssignmentCard.jsx
- src/reviewer/pages/MarkAssignments.jsx
- tests/RestReviewerAssignmentsTest.php
- build/reviewer.js
- build/reviewer.css
- build/reviewer-rtl.css
- build/reviewer.asset.php

### Change Log

- 2026-05-17: Story 5.13 — co-reviewer chips on assignment cards (REST + UI + tests).
