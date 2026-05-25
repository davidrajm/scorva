# Story 4.3: Confirm, unlock, and re-confirm with consequence dialog

Status: review

## Story

As a **coordinator**,
I want explicit confirm dialogs for rubric lifecycle actions,
So that I understand consequences before marking is opened, paused, or reset.

## Acceptance Criteria

1. **Given** a draft rubric with valid criteria **When** the coordinator clicks Confirm **Then** rubric status shows `confirmed` chip and reviewers can mark (when session active) **When** the coordinator clicks Unlock **Then** `ConfirmDialog` explains marking is paused **When** they re-confirm after edits **Then** dialog offers **Keep and flag** (default) vs **Clear marks** with bullet consequences **And** dialog traps focus, uses `role="dialog"` and `aria-modal="true"`

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- Shared `ConfirmDialog` with focus trap, Escape dismiss, `role="dialog"` / `aria-modal="true"`.
- Variants for first confirm, unlock (destructive), and re-confirm with radio `keep_flag` | `clear`.

### File List

- src/shared/components/ConfirmDialog.jsx
- src/shared/components/index.js
- src/coordinator/components/RubricsPanel.jsx
