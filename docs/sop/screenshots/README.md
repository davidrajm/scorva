# SOP screenshots

PNG assets for the Typst SOP (`project-reviews-sop.typ`). Each capture run uses a **dated subfolder** so you can keep older sets and switch the PDF source in one place.

## Folder layout

```
screenshots/
  README.md
  _pending/              ← optional staging; not used by default
  2026-05-21_143022/     ← one run (date + local time)
    01-landing-login.png
    02-workspace-top-nav.png
    …
  2026-05-18_091500/     ← older run (kept for comparison)
```

Folder name format: `YYYY-MM-DD_HHmmss` (local time, 24-hour clock).

## Automated capture (Playwright)

From the plugin root (reads `tests/e2e/.env.local` automatically via `playwright.config.ts`):

```bash
npm run build
npm run sop:screenshots
# or: npm run sop:screenshots:headed
```

This creates a new dated folder under `screenshots/`, saves captured steps as `{id}.png`, and prints the `sop-screenshots-dir` value to paste into `lib/theme.typ`.

**Expect:** `manifest.json` with a non-empty `"captured"` array and matching `.png` files (currently **19** automated IDs; the rest are manual per `SOP_SCREENSHOT_IDS_MANUAL`).

### Folder has only `manifest.json` and `"captured": []`

The run started but **no test reached `shot()`** — usually:

1. **`tests/e2e/.env.local` missing or incomplete** — copy from `tests/e2e/env.example`.
2. **Old capture spec** — update plugin and re-run (spec must match the UI journey: registry form IDs, rubric max marks, **Send credentials**, **Confirm rubric** dialog, page reload before Panel assignments).
3. **Site not ready** — plugin active, permalinks flushed, `PR_E2E_BASE_URL` matches the browser host.

Re-run `npm run sop:screenshots` and open the **new** dated folder (not an older empty run).

Optional: set `PR_SOP_SCREENSHOTS_DIR=screenshots/2026-05-21_143022/` to **re-run** into an existing folder (must end with `/`).

## Wire screenshots into the PDF

1. Open `docs/sop/lib/theme.typ`.
2. Set `#let sop-screenshots-dir = "screenshots/2026-05-21_143022/"` to the folder you want (trailing slash required).
3. Set `#let use-live-screenshots = true`.
4. Recompile: `typst compile project-reviews-sop.typ`.

To use an **older** run, only change `sop-screenshots-dir` — the main `.typ` file does not need edits.

## Manual capture

Use the “Screenshot to capture” hint under each figure in the PDF placeholders. Save files as `{id}.png` inside your dated folder using the exact IDs from the SOP (e.g. `04-dashboard.png`).

See `tests/e2e/sop-screenshot-manifest.ts` for the full ID list and capture hints.
