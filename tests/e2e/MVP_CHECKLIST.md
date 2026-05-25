# MVP UI checklist (manual + Playwright)

**Full testing guide:** [`tests/README.md`](../README.md) — start with **[Manual check (step by step)](../README.md#manual-check-step-by-step)**.

Automated spec: `tests/e2e/specs/full-plugin-ui-journey.spec.ts`  
Run: `source tests/e2e/load-env.sh && npm run test:e2e` (expect **6 passed**).

**Slow walkthrough (headed, pauses between steps):**

| Role | Command |
|------|---------|
| Coordinator setup | `npm run walkthrough:coordinator` |
| Reviewer marking | `npm run walkthrough:reviewer` (after coordinator) |
| Coordinator progress/reports | `npm run walkthrough:coordinator:finish` (after reviewer) |
| All three in order | `npm run walkthrough:all` |

Optional: `PR_E2E_WALKTHROUGH_PAUSE_MS=5000` (hold each step, default 3500), `PR_E2E_WALKTHROUGH_SLOW_MO=1000` (delay each click, default 800).

**Site URL:** use your Local host (e.g. `http://sastt.local` in `tests/e2e/.env.local`).

## Prerequisites

- [ ] Plugin active, `npm run build` done
- [ ] Users seeded: `php …/seed-e2e-users.php` from WordPress `public` root
- [ ] Permalinks flushed — `/reviews/` shows **Scorva: The Review Management System** (or configured display name) + **Log in**
- [ ] Logins: `pr_e2e_coordinator` / `pr_e2e_reviewer`, password `pr-e2e-change-me`
- [ ] Wizard reviewer email: `pr_e2e_reviewer@example.test`
- [ ] After responsive CSS changes: spot-check **375px** width on dashboard + marking grid (no page-level horizontal scroll)

## Stuck on a page?

| What you see | What to do |
|--------------|------------|
| **Scorva** wordmark / display name + **Log in** only (no sidebar) | Guest — click **Log in** |
| **wp-admin** after login | Open `/reviews/` |
| Blank `/reviews/` | `npm run build`; plugin active |
| Theme / timetable, not review app | Activate plugin; **Settings → Permalinks → Save** |
| Registry: reg no filled, stuck | Use **Add student** form (**Name** * required), not **Search students** |
| Reviewer: **No assignments yet** | **Send credentials** → **Account linked** on reviewer row |
| **Panel assignments** tab disabled | Max marks on rubric, **Confirm rubric** dialog, then refresh page |

After coordinator login: **Projects** dashboard with sidebar. URL: `/reviews/#/` (default tab: **Active projects**; use **All projects** or `#/?status=all` to see every status).

**Dashboard status tabs:** `#/?status=active|draft|closed|all` — refresh and browser Back/Forward keep the filter; invalid `?status=` values normalize to `active`.

## Journey checklist

| # | Step | URL (example) | Done |
|---|------|---------------|------|
| 1 | Guest landing — **Log in** | `/reviews/` | [ ] |
| 2 | Wizard **Students** — add 2 students (form: reg no + name) | `…/wizard?step=students` | [ ] |
| 3 | **Dashboard** — create project (or continue from step 2) | `/reviews/#/` | [ ] |
| 4 | Wizard **Students** — roster OK, continue | `…/wizard?step=students` | [ ] |
| 5 | Wizard **Panels** — panel + assign students | `…/wizard?step=panels` | [ ] |
| 6 | Wizard **Reviewers** — email + **Send credentials** → **Account linked** | `…/wizard?step=reviewers` | [ ] |
| 7 | Wizard **Reviews & rubrics** — max marks, Save, **Confirm rubric** | `…/wizard?step=reviews` | [ ] |
| 8 | Refresh page if **Panel assignments** tab blocked | (reload) | [ ] |
| 9 | **Panel assignments** → **Open reviews** → open + start marking | wizard tabs | [ ] |
| 10 | Reviewer — assignment → **Update score** → score in grid | `/reviews/mark/#/` | [ ] |
| 11 | Coordinator **Progress** — student in dropdown | `…/session/{id}/progress` | [ ] |
| 12 | Coordinator **Reports → Downloads** — file downloads | `…/session/{id}/reports` | [ ] |
| 13 | **End project → Close project** — close summary, **Close project…**, success notice | `…/session/{id}/close` | [ ] |
| 14 | Same page — **Delete project…** (type exact title if scores exist), dashboard success notice | `…/session/{id}/close` → `#/` | [ ] |

## Teardown (when finished)

```bash
composer test:teardown -- --confirm
```

Removes `pr_*` rows and users tagged `pr_test_fixture` — only when you explicitly confirm.
