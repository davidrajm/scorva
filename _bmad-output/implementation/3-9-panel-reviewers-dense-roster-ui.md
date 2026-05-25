# Story 3.9: Panel reviewers — unified add form and dense roster tables

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want a single “add reviewer” form (with panel choice) above CSV import, and a compact table of reviewers for each panel,
So that I can scan rosters quickly and add reviewers without repeating the same form on every panel card.

## Acceptance Criteria

1. **Given** the session wizard **Reviewers** step with at least one panel **When** the coordinator views the step **Then** section order is: (1) page title + intro copy, (2) **Add reviewer** form, (3) **Import from CSV** (`CsvImportMapper`), (4) one **panel roster block per panel** — not import-first and not a duplicate add form inside each panel block.

2. **Given** the **Add reviewer** form at the top **When** the coordinator fills name and/or email, weight, and selects **Panel** from a required dropdown **Then** **Add reviewer** posts to `POST /sessions/{id}/panels/{panel_id}/reviewers` for the selected panel **And** success/error feedback uses existing `Notice` patterns **And** the form resets on success **And** the matching panel’s table refreshes (optimistic update + optional panel reviewers GET, same as today).

3. **Given** no panel is selected or both name and email are empty **When** the coordinator submits **Then** client validation blocks submit with the same messages as today (“Select a panel.” / “Enter a reviewer name or email.”).

4. **Given** each panel block **When** the coordinator views reviewers for that panel **Then** rosters render as a **dense data table** (not card/list rows), visually aligned with `RubricTable`, `ProgressTable`, and Registry:
   - Wrapper: `overflow-x-auto rounded-md border border-border` (and `bg-surface-raised` / `shadow-card` where Registry uses it).
   - Table: `min-w-full text-sm`, header row with `px-4 py-3 font-medium text-text-muted` (or `py-2` for RubricTable-density — pick one and use consistently within this step).
   - Body cells: `px-4 py-3 text-text`; numeric weight column uses `tabular-nums`.
   - Panel heading above table: panel name, reviewer count, optional student count (preserve today’s metadata).

5. **Given** a reviewer row in the table **Then** columns include at minimum: **Name**, **Email**, **Weight**, **Account** (provisioned / linked / not provisioned chip or muted text), **Actions** **And** row actions preserve Story 3.5–3.6 behaviour: **Edit**, **Remove**, **Send credentials** (email, no `user_id`), **Resend credentials** (`user_id`), **Link WordPress user** (no email, no `user_id` — search UI may live in expanded row or full-width sub-row with `colSpan`).

6. **Given** **Edit** on a table row **When** the coordinator saves or cancels **Then** inline edit UI matches current fields (name, email, weight, panel move via dropdown) **And** `PUT /sessions/{id}/panels/{panel_id}/reviewers/{reviewer_id}` still supports `panel_id` change **And** row moves to the correct panel table after save.

7. **Given** CSV import completes **When** `onComplete` runs **Then** all panel tables reflect imported data (existing refresh path via `onRefreshReviewers` / `onReload` unchanged).

8. **Given** PHPUnit and front-end build **When** implementation is complete **Then** no new REST routes or schema changes are required **And** existing `RestReviewersTest` / provision tests still pass **And** `composer test` + `npm run build` pass **And** manual wizard checklist: add → import → edit → move panel → provision → resend → link → remove.

## Tasks / Subtasks

- [x] Refactor `PanelReviewersStep.jsx` layout: global `AddReviewerForm` (panel select + name + email + weight) above `CsvImportMapper` (AC: 1, 2, 3)
- [x] Remove per-panel duplicate add form from `PanelReviewerCard` (or rename to `PanelReviewerTable`) (AC: 1, 2)
- [x] Replace `ReviewerRow` list items with `PanelReviewersTable` / table row component using dense table tokens (AC: 4, 5, 6)
- [x] Keep empty state per panel: dashed border message referencing add form above or CSV import (AC: 4)
- [x] Verify CSV template download still prefills all panels/reviewers (AC: 7)
- [x] Run `composer test` and `npm run build` (AC: 8)

## Dev Notes

### Product intent (user request)

Coordinators find the current **Reviewers** step cluttered:

| Issue today | Target |
|-------------|--------|
| `CsvImportMapper` appears **before** any manual add | **Add reviewer** first, then import |
| Add form duplicated **inside every panel card** | **One** add form with **Panel** dropdown |
| Reviewers shown as **stacked cards** (`<ul>` + bordered `<li>`) | **Dense `<table>` per panel**, like rubric criteria and progress |

This is a **UI-only** refinement on Epic 3.5–3.6. Do not change provisioning, import mapping, or REST contracts.

### Current file map

| File | Role |
|------|------|
| `src/coordinator/components/PanelReviewersStep.jsx` | Entire step: import, `PanelReviewerCard` × N, `ReviewerRow` |
| `src/coordinator/components/CsvImportMapper.jsx` | `importType="panel-reviewers"` — leave API as-is |
| `src/coordinator/pages/SessionWizard.jsx` | Renders `PanelReviewersStep` on `?step=reviewers` |

### Target layout (top → bottom)

```
## Reviewers
(intro paragraph)

[ Add reviewer form ]
  Panel (select) | Name | Email | Weight | [Add reviewer]

[ CsvImportMapper — template + import ]

### Panel 1: Alpha
| Name | Email | Weight | Account | Actions |
...

### Panel 2: Beta
...
```

### Add reviewer form — implementation sketch

- Lift submit handler from `PanelReviewerCard.handleSubmit` into `AddReviewerForm` in `PanelReviewersStep`.
- State: `selectedPanelId` (default: first panel id or empty), controlled inputs for name/email/weight.
- On success: merge into `reviewers` state exactly as `PanelReviewerCard` does today (`setReviewers`, optional `GET .../panels/{id}/reviewers`).
- Default panel: when only one panel exists, pre-select it; when multiple, require explicit selection (no silent wrong-panel adds).

### Dense table — reuse existing patterns

**Reference implementations (copy class names, not new design tokens):**

| Component | Path | Reuse |
|-----------|------|--------|
| `RubricTable` | `src/coordinator/components/RubricTable.jsx` | Compact `text-sm` table, bordered rows, action column |
| `ProgressTable` | `src/coordinator/components/ProgressTable.jsx` | `overflow-x-auto`, sticky header optional for long rosters |
| Registry table | `src/coordinator/pages/Registry.jsx` | `px-4 py-3`, actions column with `Button variant="ghost" size="sm"` |

**Suggested columns**

| Column | Content |
|--------|---------|
| Name | `reviewer.name` or “Unnamed reviewer” |
| Email | email or “—” / helper for link flow |
| Weight | `tabular-nums`, display `reviewer.weight ?? 1` |
| Account | StatusChip or small badge: linked / not provisioned (match existing chip classes in `ReviewerRow`) |
| Actions | Edit, Remove, Send/Resend credentials, Link user affordances |

**Edit mode:** Prefer expanding the `<tr>` to a second row with `colSpan={5}` and the existing edit grid (name, email, weight, panel) — avoids modal scope creep. On save, collapse back to read-only row.

**Link user search:** Second row under the reviewer when linking, or inline below email in edit mode — keep debounced `GET /users/search?q=` from current `ReviewerRow`.

### What NOT to change

- REST endpoints in `includes/rest/class-rest-reviewers.php`
- `ReviewerProvisionService`, invite email, audit on resend
- `CsvImportMapper` column mapping for `panel-reviewers`
- `buildReviewersTemplateCsv` / download template behaviour
- Wizard gating in `SessionWizard` / `wizard-state` (unless copy-only)

### Regression checklist (manual)

1. Two panels → add reviewer to panel B via top form → appears only in panel B table.
2. Import CSV with mixed panels → both tables update.
3. Edit reviewer → change panel → row disappears from old table, appears in new.
4. Reviewer without email → Link user search works in table context.
5. Send credentials / Resend still call same POST routes and show parent `onNotice`.

### Previous story intelligence (3.5, 3.6)

- `mergePanelReviewers(panel, rows)` normalizes `panel_id` / `panel_name` — keep when refreshing a single panel after add/import.
- `handleReviewerSaved` must continue to handle cross-panel moves (filter by `id`, replace row with new `panel_id`).
- Faculty bridge deferred; WP user search only.

### Project structure

- Prefer extracting `PanelReviewersTable.jsx` only if `PanelReviewersStep.jsx` exceeds ~400 lines; otherwise keep colocated subcomponents in the same file (match `ReviewerRow` today).
- No new shared export required unless a second screen needs the table later.

### References

- [Source: _bmad-output/implementation/3-5-wizard-reviewers-provisioning.md]
- [Source: _bmad-output/implementation/3-6-resend-credentials-linking.md]
- [Source: src/coordinator/components/PanelReviewersStep.jsx]
- [Source: src/coordinator/components/RubricTable.jsx — table markup]
- [Source: src/coordinator/components/ProgressTable.jsx — bordered table shell]
- [Source: src/coordinator/pages/Registry.jsx — actions column pattern]
- [Source: _bmad-output/planning/ux-design-specification.md — dense progress tables, wizard funnel]

## Dev Agent Record

### Agent Model Used

Composer (Cursor)

### Debug Log References

### Completion Notes List

- Refactored `PanelReviewersStep.jsx`: `AddReviewerForm` (panel dropdown + name/email/weight) placed above `CsvImportMapper`; per-panel `PanelReviewerTable` with dense Registry-style tables.
- Renamed `ReviewerRow` → `ReviewerTableRow` with read row + expandable edit/link sub-rows (`colSpan={5}`).
- Preserved add/merge/refresh, cross-panel move, provision/resend/link handlers; CSV template download unchanged (`buildReviewersTemplateCsv`).
- `./vendor/bin/phpunit`: 118 tests OK; `npm run build`: success.

### File List

- `src/coordinator/components/PanelReviewersStep.jsx`
- `build/coordinator.js`
- `build/coordinator.css`
- `build/coordinator-rtl.css`
- `build/coordinator.asset.php`

## Change Log

- 2026-05-17: Story 3.9 — unified add-reviewer form above CSV import; dense per-panel reviewer tables (UI-only).
