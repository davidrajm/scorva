# Story 5.4: RubricForm with draft save and submit

Status: review

<!-- Validation: optional validate-create-story before dev-story. -->

## Story

As a **reviewer**,
I want to enter criterion scores and save draft or submit,
So that I can complete marking at my own pace.

## Acceptance Criteria

1. **Given** a selected student and confirmed rubric **When** the reviewer enters scores in `RubricForm` **Then** each field shows “Score (0–{max})” and validates on blur/submit **When** they click Save draft **Then** marks persist with draft status and `aria-live` announces success **When** they click Submit **Then** marks persist with submitted status and row appears distinct in student list **And** form layout is max-width ~640px centered (UX-DR5)

## Tasks / Subtasks

- [x] Implement acceptance criteria
- [x] Add/update PHPUnit tests (`tests/` — extend bootstrap stubs as needed)
- [x] Register REST routes in `includes/rest/class-rest-bootstrap.php` (if applicable)
- [x] Add React UI in `src/coordinator/` or `src/reviewer/` (if applicable)
- [x] Run `composer test` or vendor PHPUnit + `npm run build` when front-end changes

## Dev Notes

### Prerequisites
- Epic 4 confirmed rubrics + active sessions.

### Files / patterns
- `MarkService` guards before REST writes; table `pr_marks`.
- Reviewer funnel: assignments → student list → `RubricForm` on `/reviews/mark/`.
- Map API codes to UX strings (UX-DR20).
### Previous story
Continue from `_bmad-output/implementation/5-3-reviewer-assignments-ui.md` patterns.


**Covers:** FR15; UX-DR15, UX-DR21, UX-DR27

### References

- [Source: _bmad-output/planning/epics.md — Story 5.4]
- [Source: david-sas/docs/superpowers/specs/2026-05-16-project-reviews-plugin-design.md]
- [Source: _bmad-output/planning/ux-design-specification.md]

## Dev Agent Record

### Agent Model Used

### Debug Log References

### Completion Notes List

- Submit validates every criterion has a score (client `validateMarksForSave` + server `MarkService`).
- Blur/range validation via `validateCriterionScore`; draft allows partial scores.
- Student list refreshes after save so Submitted/Draft chips update on back navigation.

### File List

- src/shared/markValidation.js
- src/reviewer/components/RubricForm.jsx
- src/reviewer/App.jsx
- src/reviewer/pages/MarkAssignments.jsx
- tests/MarkServiceTest.php
