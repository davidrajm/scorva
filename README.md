# Scorva

WordPress plugin for academic project review workflows: student registry, review projects, panels, rubrics, reviewer marking, progress, reports, and backups.

**Technical slug:** `scorva` (REST namespace: `scorva/v1`, plugin constant: `PR_PLUGIN_SLUG`). **Display name** defaults to *Scorva: The Review Management System* and is configurable in WP Admin.

> **Pending manual step:** The plugin folder is currently named `project-reviews/`. After committing the code changes in this branch, rename it to `scorva/` on the server and update the plugin path in WordPress. This step must be done outside the Claude Code session to avoid breaking the working directory.

---

## Requirements

| Requirement | Notes |
|-------------|--------|
| WordPress | 6.x recommended |
| PHP | **8.2+** |
| PHP `ext-zip` | Required for ZIP backups (`ZipArchive`) |
| MySQL/MariaDB | Standard WordPress database |
| Permalinks | Pretty permalinks enabled (not “Plain”) |

Composer and Node are **not** required on the server when you install a **release ZIP** built with `bin/release.sh` (includes `vendor/` and `build/`).

---

## Install from release ZIP

1. Build or download `scorva-x.y.z.zip` (see [Creating a release ZIP](#creating-a-release-zip) below).
2. In WP Admin: **Plugins → Add New → Upload Plugin** → choose the ZIP → **Install Now** → **Activate**.
   - Or unzip into `wp-content/plugins/scorva/`.
3. **Settings → Permalinks → Save Changes** (flush rewrite rules). Do this once per site.
4. Open **`https://your-site.example/reviews/`** — you should see the landing page and **Log in**.
5. Assign roles (`project_reviews_coordinator`, `project_reviews_reviewer`) or use an administrator with plugin capabilities.
6. Optional: **Settings → Scorva** (plugin settings) — branding, email, backup, uninstall policy.

**Reviewer app URL:** `https://your-site.example/reviews/mark/`

---

## First-time coordinator checklist

1. Create a project from the dashboard.
2. Wizard: students → panels → reviewers (**Send credentials** so reviewers are linked to WP users).
3. Reviews & rubrics: set criteria, **Save**, **Confirm rubric**.
4. Panel assignments → **Open for marking**.
5. Reviewers mark at `/reviews/mark/`; coordinators use Progress and Reports.

Manual walkthrough: [`tests/README.md`](tests/README.md#manual-check-step-by-step). Printable checklist: [`tests/e2e/MVP_CHECKLIST.md`](tests/e2e/MVP_CHECKLIST.md).

Demo CSV samples: [`assets/csv/samples/demo-project/README.md`](assets/csv/samples/demo-project/README.md).

---

## Creating a release ZIP

From the plugin directory:

```bash
composer install    # first time / after clone
npm install

./bin/release.sh
```

This runs `composer test`, `npm ci`, `npm run build`, `composer install --no-dev`, writes **`dist/scorva-{version}.zip`**, then restores dev dependencies.

Options:

| Flag | Effect |
|------|--------|
| `./bin/release.sh 1.2.0` | Zip filename uses `1.2.0` (warns if header version differs) |
| `--skip-tests` | Skip PHPUnit |
| `--skip-build` | Skip `npm ci` / build (requires existing `build/`) |
| `--dry-run` | Print plan only |
| `--no-restore-dev` | Leave `vendor/` without PHPUnit after run |

Composer shortcut:

```bash
composer release
```

Publish the file under **GitHub Releases** (or your download page). Tag git to match `Version` in `project-reviews.php` (the entry-point filename stays `project-reviews.php` until the folder rename is completed manually).

---

## Develop from source

```bash
composer install
npm install
npm run build          # after JS/CSS changes
composer test          # no WordPress required
```

Browser E2E (optional): see [`tests/README.md`](tests/README.md).

---

## Backup and uninstall

- **Backup:** Settings → full-site ZIP, or per-project backup on the close-project screen (SQL + Excel reports).
- **Deactivate:** Data and tables are kept.
- **Delete plugin:** Data is removed only if **“Remove all Project Reviews data when uninstalling the plugin”** was enabled in settings **before** delete.

---

## Documentation

| Doc | Purpose |
|-----|---------|
| [`tests/README.md`](tests/README.md) | Testing and QA |
| [`docs/sop/project-reviews-sop.pdf`](docs/sop/project-reviews-sop.pdf) | Operator SOP (PDF) |
| [`docs/sop/README.md`](docs/sop/README.md) | Rebuilding the SOP |

---

## License

Specify your license before public distribution (e.g. GPLv2+ if submitting to WordPress.org).
