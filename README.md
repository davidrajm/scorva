# Scorva — Academic Project Review Management

A WordPress plugin that runs end-to-end project review workflows: student registry, review sessions, panel assignments, rubric-based scoring, reviewer marking, progress tracking, reports, and data backups.

Built for academic institutions that need structured peer or faculty review of student projects — with separate, token-authenticated interfaces for coordinators and reviewers.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [First-time Setup](#first-time-setup)
- [User Roles](#user-roles)
- [Architecture Overview](#architecture-overview)
- [Development Setup](#development-setup)
- [Running Tests](#running-tests)
- [Building a Release](#building-a-release)
- [Contributing](#contributing)
- [License](#license)

---

## Features

### Coordinator dashboard
- **Session wizard** — multi-step workflow to create a review session, import students (CSV), build panels, assign reviewers, configure rubrics, and open marking
- **Student registry** — bulk CSV import with column mapping, per-student editing, program assignment
- **Programs catalog** — define academic programs; students are normalized against the catalog
- **Rubric builder** — configurable criteria with per-criterion weights; lock rubric when marking opens
- **Panel management** — group students into panels, assign one or more reviewers per panel, designate a panel head
- **Faculty accounts** — provision WordPress reviewer accounts directly from the coordinator UI (no separate WP-admin step)
- **Session progress** — live view of marking completion per panel and per reviewer
- **Reports** — marks matrix, scores matrix, consolidated results table; exportable to Excel
- **Panel report PDFs** — configurable per-session PDF reports generated with DomPDF
- **Audit log** — timestamped record of all significant coordinator and reviewer actions
- **Backup & export** — full-site ZIP archive or per-session SQL + Excel backup on the close-session screen
- **Unfreeze requests** — reviewers can request to reopen a frozen panel; panel heads and coordinators can approve

### Reviewer portal
- **Token-based login** — reviewers authenticate with a one-time link (no WordPress account login required on the marking UI)
- **Assignment cards** — clear list of assigned panels and review rounds
- **Marking grid** — spreadsheet-style grid for scoring multiple students in a panel
- **Score entry modal** — rubric form with per-criterion scoring and automatic total calculation
- **Panel head controls** — panel heads can submit unfreeze requests for their own panels

### Landing page
- Public entry point at `/reviews/` that routes authenticated users to their correct interface

---

## Requirements

| Requirement       | Notes                                                    |
|-------------------|----------------------------------------------------------|
| WordPress         | 6.x recommended                                          |
| PHP               | **8.2+**                                                 |
| PHP `ext-zip`     | Required for ZIP backups (`ZipArchive`)                  |
| MySQL / MariaDB   | Standard WordPress database                              |
| Permalinks        | "Pretty permalinks" enabled (not "Plain")                |

`composer` and `node` are **not** required on the production server when you install from a pre-built release ZIP.

---

## Installation

### Option A — Release ZIP (recommended for production)

1. Download `scorva-x.y.z.zip` from the [Releases](../../releases) page, or build it yourself (see [Building a Release](#building-a-release)).
2. In WP Admin: **Plugins → Add New → Upload Plugin** → select the ZIP → **Install Now** → **Activate**.
   - Alternatively, unzip it into `wp-content/plugins/scorva/`.
3. After activation, go to **Settings → Permalinks → Save Changes** to flush rewrite rules. Do this once per site.
4. Visit `https://your-site.example/reviews/` — the landing page should appear.

### Option B — Clone from source

```bash
cd wp-content/plugins/
git clone https://github.com/davidrajm/scorva.git scorva
cd scorva
composer install
npm install && npm run build
```

Then activate the plugin from WP Admin and flush permalinks as above.

---

## First-time Setup

After activating the plugin:

1. **Assign roles** — Give yourself (or the review coordinator) the `Coordinator` role (see [User Roles](#user-roles)), or use an administrator account.
2. **Configure settings** — Go to **Settings → Scorva** to set:
   - Branding name displayed in emails and UI
   - SMTP settings for outgoing reviewer credential emails
   - Backup retention policy
   - Uninstall data-removal preference
3. **Create your first session** — From the coordinator dashboard at `/reviews/`, click **New Session** and follow the wizard:
   1. Name the session and set the academic period
   2. Import students via CSV (download the sample template from **Assets → CSV Samples**)
   3. Build panels (automatic or manual grouping)
   4. Add reviewers — use **Faculty Accounts** to provision WP users if needed
   5. Assign reviewers to panels
   6. Define the rubric criteria and weights, then **Confirm Rubric**
   7. **Open for Marking** — this emails reviewers their login links
4. **Monitor progress** — Use **Session Progress** to see real-time marking completion.
5. **Generate reports** — Once marking is complete, view or export from **Reports**.
6. **Close the session** — Download the per-session backup before closing.

For a printable step-by-step checklist see [`tests/e2e/MVP_CHECKLIST.md`](tests/e2e/MVP_CHECKLIST.md).
Demo CSV samples are in [`assets/csv/samples/demo-project/`](assets/csv/samples/demo-project/).

---

## User Roles

| Role slug                        | Capability set                                                                                  |
|----------------------------------|-------------------------------------------------------------------------------------------------|
| `project_reviews_coordinator`    | Full access to the coordinator dashboard: sessions, registry, rubrics, reports, backup, settings |
| `project_reviews_reviewer`       | Access to the reviewer marking portal only; token-authenticated (no WP admin access)            |
| Administrator                    | Inherits all plugin capabilities automatically                                                  |

Roles are assigned from **WP Admin → Users** or provisioned directly from the **Faculty Accounts** page inside the coordinator dashboard.

---

## Architecture Overview

```
scorva/
├── project-reviews.php          # Plugin entry point (autoload, hooks)
├── includes/
│   ├── class-plugin.php         # Bootstraps all subsystems on `init`
│   ├── install.php              # DB table creation + schema migrations
│   ├── capabilities.php         # Custom role and capability definitions
│   ├── routes.php               # WordPress rewrite rules (/reviews/*)
│   ├── rest/                    # REST API controllers (namespace: scorva/v1)
│   ├── services/                # Business logic layer
│   ├── repositories/            # Database query layer (wpdb)
│   ├── emails/                  # Transactional email classes
│   └── admin/                   # WP Admin settings page
├── src/
│   ├── coordinator/             # React SPA — coordinator dashboard
│   ├── reviewer/                # React SPA — reviewer marking portal
│   └── landing/                 # React SPA — public landing / auth routing
├── build/                       # Compiled JS/CSS (webpack output; committed for releases)
├── templates/                   # PHP HTML shells that mount the React apps
├── assets/                      # Static assets, CSV sample files
├── tests/                       # PHPUnit unit + integration + journey tests
└── bin/
    └── release.sh               # Build + package release ZIP
```

**Key design decisions:**

- **Three separate React SPAs** loaded on distinct URL slugs (`/reviews/`, `/reviews/mark/`, coordinator admin). Each is a standalone Webpack entry built with `@wordpress/scripts`.
- **REST API** is the only data layer the front-end touches. All endpoints are under `scorva/v1` and require appropriate WordPress nonces or reviewer tokens.
- **Token-based reviewer auth** — reviewers receive a signed token via email. The token is exchanged for a short-lived session cookie without requiring a WP password, so reviewers never need a WordPress login screen.
- **Custom DB tables** are created on activation and migrated via sequential schema patches in `install.php`. No CPTs or post-meta for structured review data.
- **DomPDF** renders panel report PDFs server-side from configurable PHP templates.
- **PHPSpreadsheet** handles Excel export for reports and session backups.

---

## Development Setup

### Prerequisites

- PHP 8.2+, Composer
- Node 18+ (LTS), npm
- A local WordPress installation (e.g. [LocalWP](https://localwp.com/), DDEV, or Lando)

### Install dependencies

```bash
composer install
npm install
```

### Build assets (one-off)

```bash
npm run build
```

### Watch mode (JS/CSS auto-rebuild)

```bash
npm run start
```

### Full dev server with live reload

```bash
npm run dev    # starts both `wp-scripts start` and BrowserSync
```

BrowserSync is configured in `browser-sync.config.js` — set your local site URL there.

### Environment variables

No `.env` file is required for PHP unit tests. For Playwright E2E tests, configure the site URL in `playwright.config.ts` (see `tests/README.md`).

---

## Running Tests

### PHP unit tests (no WordPress required)

```bash
composer test
```

The test bootstrap stubs the WordPress globals (`wpdb`, `get_option`, etc.) so the full suite runs without a live WordPress instance.

### PHP journey tests only

```bash
composer test:journey
```

### Playwright browser E2E tests

Requires a running WordPress site with the plugin activated and the database seeded.

```bash
npm run test:e2e              # headless
npm run test:e2e:headed       # headed (visible browser)
npm run test:e2e:ui           # Playwright interactive UI
```

Walkthrough tests (step-by-step interactive):

```bash
npm run walkthrough:coordinator
npm run walkthrough:reviewer
npm run walkthrough:coordinator:finish
```

Full Playwright setup is documented in [`tests/README.md`](tests/README.md).

---

## Building a Release

From the plugin root:

```bash
./bin/release.sh
```

This will:

1. Run `composer test` (PHPUnit)
2. Run `npm ci` and `npm run build`
3. Install Composer production deps only (`--no-dev`)
4. Write `dist/scorva-{version}.zip`
5. Restore dev dependencies

Available flags:

| Flag | Effect |
|------|--------|
| `./bin/release.sh 1.2.0` | Override version in ZIP filename |
| `--skip-tests` | Skip PHPUnit |
| `--skip-build` | Skip npm build (requires existing `build/`) |
| `--dry-run` | Print plan, make no changes |
| `--no-restore-dev` | Leave `vendor/` without PHPUnit after run |

Composer shortcut:

```bash
composer release
```

Publish the resulting ZIP as a GitHub Release asset and tag the commit to match the `Version` field in `project-reviews.php`.

---

## Contributing

Contributions are welcome. Here is how to get started:

1. **Fork** the repository and create a feature branch from `main`.
2. **Install** dependencies: `composer install && npm install`.
3. **Write tests** for new behaviour — PHP unit tests live in `tests/`, E2E tests in `tests/e2e/specs/`.
4. **Run the full test suite** before opening a PR: `composer test`.
5. **Open a pull request** against `main` with a clear description of what changed and why.

### Code style

- PHP: strict types (`declare(strict_types=1)` at the top of every file), PSR-4 autoloading.
- JavaScript: functional React components, hooks only (no class components).
- CSS: Tailwind utility classes; avoid custom CSS unless strictly necessary.

### Reporting issues

Please open a [GitHub Issue](../../issues) with:
- WordPress version, PHP version
- Steps to reproduce
- Observed vs. expected behaviour
- Any relevant PHP error log entries

---

## License

This plugin is released under the **GNU General Public License v2.0 or later (GPL-2.0+)**, consistent with the WordPress ecosystem licensing requirements.

See [LICENSE](LICENSE) for the full text, or visit https://www.gnu.org/licenses/gpl-2.0.html.

Third-party libraries bundled in release ZIPs carry their own licenses:

| Library | License |
|---------|---------|
| [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) | MIT |
| [ZipStream-PHP](https://github.com/maennchen/ZipStream-PHP) | MIT |
| [Dompdf](https://github.com/dompdf/dompdf) | LGPL-2.1 |
| [React](https://react.dev/) | MIT |
| [React Router](https://reactrouter.com/) | MIT |
| [@wordpress/components](https://github.com/WordPress/gutenberg) | GPL-2.0+ |
