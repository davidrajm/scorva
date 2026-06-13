# Project Reviews — SOP (Typst)

Standard Operating Procedure for coordinators and reviewers.

## Compile

Requires [Typst](https://typst.app/) 0.12 or later:

```bash
cd docs/sop
typst compile project-reviews-sop.typ
```

Output: `project-reviews-sop.pdf`

Watch mode during editing:

```bash
typst watch project-reviews-sop.typ
```

## Screenshots

Each capture run is stored in a **dated subfolder** under `screenshots/` (e.g. `screenshots/2026-05-21_143022/`). The Typst theme reads from one global folder variable so you can switch between older runs without editing the main document.

### Automated capture (recommended)

From the plugin root (with E2E users and `PR_E2E_*` env vars — see `tests/README.md`):

```bash
npm run sop:screenshots
```

This creates a new dated folder, saves PNGs named `{id}.png`, writes `manifest.json`, and prints the `sop-screenshots-dir` line to paste into `lib/theme.typ`.

### Wire images into the PDF

1. Open `lib/theme.typ`.
2. Set `#let sop-screenshots-dir = "screenshots/2026-05-21_143022/"` (trailing slash; use the folder from your run).
3. Set `#let use-live-screenshots = true`.
4. `typst compile project-reviews-sop.typ`.

To use **older** screenshots, only change `sop-screenshots-dir`.

### Manual capture

1. Create a dated folder under `screenshots/` (same `YYYY-MM-DD_HHmmss` pattern).
2. Save each PNG as `{id}.png` (IDs match placeholders, e.g. `04-dashboard.png`).
3. Point `sop-screenshots-dir` at that folder and set `use-live-screenshots = true`.

See `screenshots/README.md` and `tests/e2e/sop-screenshot-manifest.ts` for the full ID list.

## Structure

| File | Purpose |
|------|---------|
| `project-reviews-sop.typ` | Main document |
| `lib/theme.typ` | Brand colours, callouts, screenshot helper, flow diagram helpers |
| `screenshots/` | Dated PNG run folders (`YYYY-MM-DD_HHmmss/`) |
| `screenshots/README.md` | Folder naming and Typst wiring |
