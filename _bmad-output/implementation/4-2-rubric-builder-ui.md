# Story 4.2: Rubric builder UI with RubricTable

Status: review

## Story

As a **coordinator**,
I want to edit rubric criteria in the session wizard Rubrics step,
So that review rounds are defined before confirmation.

## Acceptance Criteria

1. **Given** wizard step Rubrics with `RubricTable` **When** review is draft or unlocked **Then** criteria rows are editable (label, max_marks, weight) with `inputmode="decimal"` **When** review is confirmed **Then** table is read-only with Confirmed StatusChip **And** Save persists criteria via REST **And** one primary Confirm action is visible per screen (UX-DR19)

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Agent Record

### Completion Notes List

- `RubricTable` component with editable/read-only states, Save + single Confirm primary per review.
- Wired into session wizard Rubrics step via `RubricsPanel` and standalone `#/session/:id/rubrics` route.

### File List

- src/coordinator/components/RubricTable.jsx
- src/coordinator/components/RubricsPanel.jsx
- src/coordinator/pages/Rubrics.jsx
- src/coordinator/pages/SessionWizard.jsx (rubrics step only)
- src/coordinator/App.jsx
