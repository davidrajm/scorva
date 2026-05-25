# Demo project CSV samples (10 students, 2 panels)

Use these files when setting up a test project. The app accepts **CSV** only (open or edit in Excel, then **Save As → CSV UTF-8**).

## Upload order

| Order | File | Where in the app |
| ----- | ---- | ---------------- |
| 1 | `02-wizard-enrol-students.csv` | Project **Setup wizard** → **Students** → Import Students |
| 2 | `03-wizard-panel-reviewers.csv` | Setup wizard → **Reviewers** → Import from CSV |

**Panels (wizard step 2)** has no CSV import. `Panel A` and `Panel B` are created automatically when you import file **02** (the `panel` column).

Steps **Reviews & rubrics**, **Panel assignments**, and **Open reviews** are configured in the UI (no sample CSV).

### Optional: student directory bulk import

`01-all-students-registry.csv` is for the **Student directory** (`#/registry`) when you want to maintain identity records separately before any project. It is **not required** — wizard import creates directory entries automatically.

## Contents

- **10 students** (`25MDT1001`–`25MDT1010`) with name, program, and batch
- **5 students per panel** with a guide employee ID and name on each enrolment row
- **2 panels** (`Panel A`, `Panel B`), **2 reviewers each** (4 reviewer accounts total)

Reviewer emails are placeholders (`@example.com`). Change them before provisioning accounts if you need real mail.
