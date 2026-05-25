# Story 3.10: Wizard Panels — edit and delete with student-assignment guard

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **coordinator**,
I want to rename and remove panels from the wizard Panels step,
So that I can fix setup mistakes without leaving the session wizard, while enrolled students stay protected from accidental panel deletion.

## Acceptance Criteria

1. **Given** the session wizard **Panels** step with at least one panel **When** the coordinator views the step **Then** a **panel list** appears above the student assignment list **And** each row shows panel name, enrolled student count, and actions: **Rename** (inline edit, same pattern as `ReviewRoundsStep`) and **Remove** **And** the existing **Add panel** form remains at the top.

2. **Given** a panel with `student_count === 0` **When** the coordinator clicks **Remove** **Then** a destructive `ConfirmDialog` asks to confirm **And** on confirm `DELETE /sessions/{id}/panels/{panel_id}` succeeds **And** the panel disappears from the list and student panel dropdowns refresh **And** any reviewers on that panel are removed (existing `PanelRepository::delete` cascade).

3. **Given** a panel with one or more enrolled students assigned (`student_count > 0`) **When** the coordinator views that panel row **Then** **Remove** is hidden or disabled **And** helper copy explains: “Reassign or unassign students before removing this panel.” **And** `DELETE` for that panel returns `409` with code `pr_panel_has_students` if called anyway.

4. **Given** a panel name **When** the coordinator renames via inline edit and blurs or presses Enter **Then** `PUT /sessions/{id}/panels/{panel_id}` with `{ name }` persists **And** duplicate names in the same session return `409` `pr_duplicate_panel` (match create behaviour) **And** empty name is rejected client-side and server-side (`400` `pr_invalid_panel`).

5. **Given** `GET /sessions/{id}/panels` **When** panels are listed **Then** each item includes `student_count` (already computed) **And** `deletable: student_count === 0` for UI gating **And** `PUT`/`DELETE` responses return the same shaped panel object with accurate `student_count` (fix today’s `update_panel` returning `student_count: 0`).

6. **Given** PHPUnit and front-end build **When** implementation is complete **Then** `RestSessionsTest` covers: delete empty panel succeeds; delete panel with assigned student returns `409`; rename panel succeeds; duplicate rename returns `409` **And** `composer test` + `npm run build` pass.

## Tasks / Subtasks

- [x] Add `SessionRepository::count_students_for_panel(int $session_id, int $panel_id): int` (or equivalent) and use in `delete_panel` guard (AC: 3, 5)
- [x] Extend `format_panel()` with `deletable`; fix `update_panel` / `delete_panel` responses to pass real `student_count` (AC: 5)
- [x] Add duplicate-name check to `update_panel` when `name` changes (AC: 4)
- [x] Extract `PanelsStep.jsx` from `SessionWizard.jsx` (mirror `ReviewRoundsStep`): panel list + inline rename + `ConfirmDialog` delete (AC: 1, 2, 3)
- [x] Wire `parseApiErrorMessage` for delete/rename failures (AC: 2, 4)
- [x] Extend `tests/RestSessionsTest.php` (AC: 6)
- [x] Run `composer test` and `npm run build`

## Dev Notes

### Product intent (user request)

Story 3.4 delivered panel **create** and per-student **assignment** but no way to manage panels after creation. Coordinators need:

| Capability | Backend today | UI today |
|------------|---------------|----------|
| Rename panel | `PUT .../panels/{id}` | **Missing** |
| Delete panel | `DELETE .../panels/{id}` — **no student guard** | **Missing** |
| Block delete when students assigned | **Not enforced** | N/A |

This story adds wizard UI and **server enforcement** so assigned students cannot be orphaned by panel deletion.

### Delete guard (server — required)

`Rest_Sessions::delete_panel` currently deletes unconditionally:

```php
(new PanelRepository())->delete($panel_id);
return ['deleted' => true];
```

**Required** before delete (mirror `Rest_Reviews::delete_review`):

```php
$student_count = (new SessionRepository())->count_students_for_panel($session_id, $panel_id);
if ($student_count > 0) {
    return new \WP_Error(
        'pr_panel_has_students',
        __('Cannot remove a panel while students are assigned. Reassign students first.', 'project-reviews'),
        ['status' => 409]
    );
}
```

Do **not** clear `panel_id` on enrolments automatically — coordinators must reassign via the existing student dropdown (or unassign to “Unassigned”).

**Reviewers on panel:** User requirement is **students only**. Panels with reviewers but zero students **may** be deleted; `PanelRepository::delete` already removes `pr_panel_reviewers` rows. No marks guard needed (marks are not keyed by `panel_id`).

### `format_panel` and `deletable`

`list_panels` already counts students per panel. Add:

```php
'deletable' => $student_count === 0,
```

Use the same count helper in `delete_panel`, `update_panel`, and `create_panel` responses so the UI never shows `deletable: true` while server would reject.

### Rename / duplicate names

`create_panel` checks `find_by_name` → `409 pr_duplicate_panel`. **`update_panel` does not** — add the same check when `name` is present and differs from current row (exclude self by id).

Client: inline edit like `ReviewRoundsStep` (`editingId`, `editName`, blur/Enter to save, Escape to cancel).

### UI structure (`PanelsStep.jsx`)

Recommended layout (top → bottom):

1. Title + intro (existing copy)
2. **Add panel** form (move from `SessionWizard` unchanged)
3. **Panel list** — one row per panel from `panels` state:
   - Name (button → inline input) or count badge: “N students”
   - **Remove** when `panel.deletable` (or `student_count === 0`)
   - Disabled/hidden + `title` tooltip when not deletable
4. **Student assignments** — existing enrolled list with panel `<select>` (unchanged behaviour)

**Delete flow** (copy `ReviewRoundsStep` / Story 4.7):

```javascript
<ConfirmDialog
  open={ pendingDelete !== null }
  title={ `Remove panel “${ pendingDelete?.name }”?` }
  consequences={ [
    'Reviewers on this panel will be removed.',
    'This cannot be undone.',
  ] }
  confirmLabel="Remove panel"
  confirmVariant="primary" // or destructive if Button supports it
  onConfirm={ () => confirmDeletePanel() }
  onCancel={ () => setPendingDelete( null ) }
/>
await del( `/sessions/${ sessionId }/panels/${ panel.id }` );
await onReload?.();
```

Refresh `panels` and enrolled students after rename/delete so dropdown labels and counts stay in sync (`loadAll` / parent callback).

### Files to touch

| File | Change |
|------|--------|
| `includes/repositories/SessionRepository.php` | `count_students_for_panel()` |
| `includes/rest/class-rest-sessions.php` | delete guard, `deletable`, duplicate name on update, accurate `student_count` on PUT |
| `src/coordinator/components/PanelsStep.jsx` | **New** — panel list, rename, delete confirm |
| `src/coordinator/pages/SessionWizard.jsx` | Replace inline panels section with `<PanelsStep … />` |
| `tests/RestSessionsTest.php` | Panel delete/rename/guard tests |

### Do not

- Add a new REST route — use existing `PUT` / `DELETE` on `/sessions/{id}/panels/{panel_id}`.
- Delete panels with assigned students even when the session is `draft` (rule is assignment-based, not session status).
- Move student assignment UI to a separate page — stay on wizard Panels step only.
- Block delete because the panel has reviewers but no students (out of scope per user).

### UX (UX-DR9, UX-DR32)

- **Govern before speed:** disabled Remove + explicit copy when students are assigned.
- **Confirm destructive actions:** `ConfirmDialog` before delete.
- **Accessible labels:** `aria-label={`Rename panel ${panel.name}`}`, `aria-label={`Remove panel ${panel.name}`}`.

### Testing

**PHPUnit (`RestSessionsTest`):**

1. Create session, panel, enrol student, assign to panel → `DELETE` panel → `WP_Error` code `pr_panel_has_students`, status 409.
2. Same setup → unassign student (`assign_panel` with `null`) → `DELETE` → `deleted: true`.
3. `PUT` rename → name updated; second panel renamed to first’s name → 409.

**Manual:**

- Panels step: create two panels, assign student to A → cannot remove A, can remove B.
- Reassign student off A → remove A succeeds.
- Rename panel → student dropdown shows new name.

### Previous story intelligence

- **3.4:** Panels REST + assignment gates; extend UI only, do not regress `unassigned_count` / `WizardNav` blockers.
- **3.7 / 4.7:** Inline rename + `ConfirmDialog` delete patterns in `ReviewRoundsStep.jsx`.
- **3.9:** Reviewers step is separate; deleting a panel removes its reviewers — mention in confirm dialog consequences.

### References

- [Source: _bmad-output/implementation/3-4-wizard-panels.md]
- [Source: _bmad-output/implementation/4-7-delete-rubric.md — ConfirmDialog + server guard pattern]
- [Source: src/coordinator/components/ReviewRoundsStep.jsx — inline edit, Remove]
- [Source: includes/rest/class-rest-sessions.php — `list_panels`, `create_panel`, `update_panel`, `delete_panel`]
- [Source: includes/repositories/PanelRepository.php — `delete` cascades reviewers]
- [Source: _bmad-output/planning/epics.md — Story 3.4, FR5]

## Dev Agent Record

### Agent Model Used

(create-story workflow)

### Debug Log References

- FakeWpdb lacked `COUNT … AND panel_id` pattern; added for `count_students_for_panel`.
- `update_enrolled_student` ignored `panel_id: null` (unassign); fixed with `array_key_exists` guard.

### Completion Notes List

- Added `SessionRepository::count_students_for_panel()` and panel delete guard (`pr_panel_has_students` / 409).
- Extended `format_panel()` with `deletable`; `update_panel` validates name, duplicate names, and returns accurate counts.
- New `PanelsStep.jsx`: panel list with inline rename, destructive delete confirm, student assignments unchanged.
- `SessionWizard` delegates panels step to `PanelsStep`.
- Fixed student unassign via `PUT` with `panel_id: null`.
- `RestSessionsTest`: delete guard, unassign-then-delete, rename/duplicate, list `deletable` flags.
- All 122 PHPUnit tests pass; `npm run build` succeeds.

### File List

- includes/repositories/SessionRepository.php
- includes/rest/class-rest-sessions.php
- src/coordinator/components/PanelsStep.jsx
- src/coordinator/pages/SessionWizard.jsx
- tests/RestSessionsTest.php
- tests/FakeWpdb.php
- build/coordinator.js
- build/coordinator.css
- build/coordinator-rtl.css
- build/coordinator.asset.php

### Change Log

- 2026-05-17: Story created from user request — wizard panel edit/delete; block delete when students assigned.
- 2026-05-17: Implemented panel rename/delete UI, server guards, tests, and unassign fix.
