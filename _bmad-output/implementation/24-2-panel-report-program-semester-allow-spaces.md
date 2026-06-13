# Story 24.2: Panel report preview — Program Name / Semester fields swallow spaces

Status: draft

## Story

As a **coordinator filling in the report header**,
I want **to type multi-word program names (e.g. "MSc Data Science")**,
So that the report shows the real program name instead of a concatenated one.

## Background — current behaviour (do not guess)

Confirmed root cause in `src/coordinator/components/PanelReportSettingsPreview.jsx:68-69`:

```js
const program = ( report.program_name || '' ).trim();
const semester = ( report.semester || '' ).trim();
```

These **trimmed** values feed the controlled `<input value={cell.value}>` in `MetaCell`. When the user types a trailing space to start the next word, the next render trims it away, so the space never sticks — multi-word values can only be produced by pasting or typing the space mid-string. The earlier review guessed the cause was `class-rest-students.php:542`; that is wrong — the REST trim runs once on save and preserves internal spaces. The bug is purely the render-time trim on the controlled value.

## Acceptance Criteria

1. **Given** the Program Name or Semester field in the panel report preview
   **When** the coordinator types `MSc Data Science` character by character
   **Then** every character including spaces appears, and the saved settings contain the value with internal spaces intact

2. **Given** save (`handleSave` / freeze)
   **Then** leading/trailing whitespace is trimmed **once at save time** (client or server), not on every render

3. **Given** any other controlled input in the preview fed from a derived/transformed value
   **Then** a quick audit confirms no other field trims/transforms its controlled value on render (the letterhead and table-header inputs pass raw values today — keep it that way)

4. **Given** regression coverage
   **Then** a test types a two-word program name and asserts both the input display and the persisted payload

## Tasks / Subtasks

- [ ] Pass raw `report.program_name` / `report.semester` into the meta cells (drop the `.trim()` at lines 68–69; keep trim only where the value is *compared*, e.g. empty checks)
- [ ] Trim on save in `PanelReportSettings.jsx` `handleSave`/freeze payload, or in the REST sanitizer for `panel-report-settings`
- [ ] Audit other derived controlled values in the preview component
- [ ] Add regression test; rebuild assets

## Dev Notes

### File structure (expected touch set)

- `src/coordinator/components/PanelReportSettingsPreview.jsx:68-69`
- `src/coordinator/pages/PanelReportSettings.jsx` (save payload trim)
- Rebuild `build/coordinator.*`

### Out of scope

- Student registry `program` field (separate concern; spaces already work there — see story 25-1 for normalization)
