# Story 24.1: Panel report preview — letterhead inputs lose focus while typing

Status: draft

## Story

As a **coordinator configuring the panel report letterhead**,
I want **to type institution/department lines without the field dropping focus after the first character**,
So that I can enter letterhead text normally instead of clicking back into the field for every keystroke.

## Background — current behaviour (do not guess)

Root cause is confirmed in `src/coordinator/components/PanelReportSettingsPreview.jsx`:

- Empty letterhead text blocks render as labelled inputs **inside the logo-controls block** with key `lh-empty-${index}` (~lines 187–210).
- Non-empty blocks render as inline inputs **in the letterhead area** with key `lh-text-${index}` (~lines 212–240).
- The branch is chosen per render via `hasValue = (block.value || '').trim() !== ''`.

Typing the first character into an empty field flips `hasValue`, so React unmounts the `lh-empty` input and mounts a fresh `lh-text` input elsewhere in the tree — **focus is lost after one keystroke**. Deleting the last character flips it back the other way. Typing a leading space also keeps the block "empty" (the check trims), so the caret appears stuck.

This matches the user report "focus loss when typing department/program" — the letterhead lines are where department/institution names are typed. (The Program Name meta cell has a separate bug — story 24-2.)

## Acceptance Criteria

1. **Given** an empty letterhead text line
   **When** the coordinator types continuously (including spaces)
   **Then** the input keeps focus and caret position for the whole entry — no unmount/remount on the empty↔non-empty transition

2. **Given** a non-empty line whose text is fully deleted
   **Then** the input also keeps focus and simply shows its placeholder

3. **Given** the visual design intent (empty lines offered as "Add title/subtitle" affordances near the logo controls, filled lines shown inline in the letterhead)
   **Then** either (a) each block's input renders at one stable position with styling that changes instead of position, or (b) position still differs but focus is programmatically preserved across the move — option (a) is strongly preferred; document the choice

4. **Given** the preview fixture tests / Playwright coverage for the panel report settings page
   **Then** a regression test types a multi-character value into a previously-empty letterhead line and asserts the rendered value equals what was typed

## Tasks / Subtasks

- [ ] Refactor the two `textBlocks.map` passes into a single stable render path per block (stable key per block index, not per emptiness state)
- [ ] Keep the "Add subtitle (optional)" affordance via styling/placeholder rather than relocating the element
- [ ] Manual check: title, subtitle, and a third added line; typing, clearing, retyping
- [ ] Add Playwright (or testing-library) regression per AC4

## Dev Notes

### File structure (expected touch set)

- `src/coordinator/components/PanelReportSettingsPreview.jsx` (~lines 187–240)
- Rebuild `build/coordinator.*`

### Out of scope

- The trim-on-render bug in meta cells (story 24-2), PDF logo constraints (story 24-3)
