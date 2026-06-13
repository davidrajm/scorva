# Story 24.3: Panel report PDF — constrain logo to the letterhead area

Status: draft

## Story

As a **coordinator uploading an institution logo**,
I want **the logo to always fit the letterhead in the generated PDF**,
So that an oversized or oddly-proportioned image cannot push the report content off the page.

## Background — current behaviour (do not guess)

- The **preview** (`PanelReportSettingsPreview.jsx` ~line 148) renders the logo with `width: ${widthIn}in; maxWidth: 100%; height: auto` — reasonably safe.
- The **PDF** (`includes/services/PanelReportPdfService.php:364-386`, `render_letterhead`) emits only `style="width: %.2fin;"` from the raw stored `width_in`. There is **no max-width, no height constraint, and no server-side clamp**: the UI number input claims min 0.5 / max 8, but the REST payload is stored as-is, so any float reaches the PDF. A tall portrait logo at 8in wide can fill most of an A4 page.
- `PanelReportPdfContextBuilder::resolve_logo_data_uri` embeds the original file as a data URI — full-resolution images also bloat the PDF size.

## Acceptance Criteria

1. **Given** a stored `width_in` outside [0.5, 8]
   **When** panel report settings are saved or the PDF is rendered
   **Then** the value is clamped server-side (sanitize on save in the panel-report-settings REST controller; defensive clamp in `render_letterhead`)

2. **Given** any logo aspect ratio
   **When** the PDF renders
   **Then** the image carries both a width and a `max-height` constraint (pick a letterhead budget, e.g. 1.5–2in, preserving aspect ratio) so the letterhead block cannot exceed its band

3. **Given** the on-screen preview
   **Then** the same effective constraints apply so preview and PDF agree (no surprise between freeze and download)

4. **Given** a large source image (e.g. 4000px PNG)
   **Then** the embedded data URI uses a resized intermediate (WordPress `large`/`medium_large` size when available) rather than the original file, keeping PDF size reasonable

## Tasks / Subtasks

- [ ] Clamp `width_in` in the panel-report-settings sanitizer + defensively in `render_letterhead`
- [ ] Add `max-height` (and `max-width` vs page width) to the PDF `<img>` style; verify dompdf honors it with both landscape and portrait logos
- [ ] Mirror the height cap in the preview component styles
- [ ] Prefer a sized attachment file in `attachment_to_data_uri` (fall back to original)
- [ ] PHPUnit: sanitizer clamps; rendered letterhead HTML contains constraints

## Dev Notes

### File structure (expected touch set)

- `includes/services/PanelReportPdfService.php:364-386`
- `includes/services/PanelReportPdfContextBuilder.php:297-330`
- `includes/rest/class-rest-session-panel-report-settings.php` (sanitize)
- `src/coordinator/components/PanelReportSettingsPreview.jsx` (preview parity)
