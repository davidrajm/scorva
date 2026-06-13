# STORY-25-1: Reviewer Credentials UX Enhancements

**Epic:** 25 — Reviewer Credentials UX
**Priority:** Should Have
**Story Points:** 5
**Status:** Not Started
**Assigned To:** Unassigned
**Created:** 2026-06-13
**Sprint:** Current

---

## User Story

As a coordinator
I want clear feedback when sending credentials and a way to view or regenerate them per reviewer
So that I can confidently manage reviewer access without second-guessing whether emails were sent

---

## Description

### Background

The Reviewers wizard step (`?step=reviewers`) lets coordinators provision and send credentials to reviewers. The current implementation has three gaps:

1. **No loading or success feedback per row.** Clicking "Send credentials" or "Resend credentials" on a reviewer row fires the API call silently — the button does not enter a loading state and no success/error notice appears inline to that row. The coordinator has to look at a global banner (if there is one) or refresh the page to know if it worked.

2. **No way to view current credentials.** Once credentials are generated, the coordinator cannot see the reviewer's token link or password in the UI. The data is stored (`pr_panel_reviewers.token`, `password_encrypted`) but never surfaced. Coordinators sometimes need to manually share or verify credentials without triggering a full re-send.

3. **Bulk credential actions lack polish.** "Email credentials to all" and "Resend to all" use a native `window.confirm()` for the resend confirmation. There is no "Regenerate all" button for when all reviewer passwords need rotation. Both bulk actions need the same loading-and-result feedback treatment.

### Scope

**In scope:**
- Per-row loading state on "Send credentials" and "Resend credentials" buttons
- Per-row inline success/error feedback (replaces relying solely on global notice)
- "View credentials" modal per reviewer: shows the portal URL, login token, and current password (decrypted from `password_encrypted`), plus a "Regenerate" action
- New REST `GET` endpoint to retrieve a reviewer's current decrypted credentials without regenerating them
- "Regenerate" inside the view-credentials modal: one click + confirm dialog, calls the existing `generate-credentials` POST, refreshes the modal with the new credentials
- "Email credentials to all" and "Resend to all" — keep existing behaviour but remove `window.confirm()` and replace with a proper React confirmation modal
- New "Regenerate all credentials" button — typed confirmation modal (user types a word to confirm), calls `POST /sessions/{id}/send-all-credentials` with `force: true`, shows summary (sent / skipped / failed)

**Out of scope:**
- Token rotation (token stays stable; only the password is regenerated, as per existing service logic)
- Per-reviewer "view credentials" history or audit log (use existing audit trail)
- Sending credentials from any other page (only the wizard Reviewers step)
- Batch selection of individual reviewers for partial bulk send

### User Flow — Per-row send

1. Coordinator clicks "Send credentials" on a reviewer row.
2. Button enters loading state (spinner); row is non-interactive during the request.
3. On success: button label becomes "Resend credentials"; an inline success chip or banner appears in/near the row ("Credentials emailed.").
4. On failure: inline error message near the row ("Could not send — check SMTP settings."). Button returns to its original state.

### User Flow — View credentials modal

1. Coordinator clicks "View credentials" on a reviewer with `has_credentials = true`.
2. Modal opens showing:
   - Reviewer name
   - Portal URL (full link, copyable)
   - Login token (copyable)
   - Password (shown in a read-only masked field, with a toggle to reveal, copyable)
   - "Regenerate" button
3. Coordinator clicks "Regenerate".
4. A confirmation dialog inside the modal asks: "Regenerate password? A new password will be generated and emailed to [name]."
5. On confirm: spinner in modal, calls `POST generate-credentials`. On success, modal refreshes with new credentials.
6. Coordinator can close the modal at any time.

### User Flow — Regenerate all credentials

1. Coordinator clicks "Regenerate all credentials" button (header area of the Reviewers step).
2. A modal opens explaining: "This will generate a new password for every reviewer and resend their credentials email. Reviewers currently logged in will need to use the new password."
3. A text input prompts: type **REGENERATE** to confirm.
4. Once the text matches, the confirm button becomes active.
5. On confirm: spinner, calls `POST /sessions/{id}/send-all-credentials` with `force: true`. On completion, shows summary: "X sent, Y skipped, Z failed."

---

## Acceptance Criteria

- [ ] Clicking "Send credentials" or "Resend credentials" on a reviewer row shows a loading spinner on that button while the request is in flight; the button is disabled during this time
- [ ] On successful send: an inline success notice appears near the row ("Credentials emailed to [name]."); the Access column updates immediately without requiring a page refresh
- [ ] On failed send: an inline error notice near the row specifies the failure ("Could not send — check SMTP settings."); the button returns to its original state
- [ ] A "View credentials" button appears on every reviewer row that has `has_credentials = true`
- [ ] The view-credentials modal displays the portal URL, token, and password (masked by default, revealable); each has a one-click copy-to-clipboard button
- [ ] Clicking "Regenerate" inside the modal opens a confirmation dialog; on confirm, the credentials are regenerated (POST `generate-credentials`) and the modal refreshes with the new values
- [ ] A new REST `GET /sessions/{id}/reviewers/{reviewer_id}/credentials` endpoint returns `{ token, password, login_url }` for a coordinator-authenticated request; password is decrypted from `password_encrypted`
- [ ] The "Resend to all" button no longer uses `window.confirm()`; it opens a React modal with a clear explanation before proceeding
- [ ] A new "Regenerate all credentials" button is present in the header area of the Reviewers step
- [ ] Clicking "Regenerate all credentials" opens a modal with a typed-confirmation input (user must type **REGENERATE**); the confirm button is disabled until the text matches
- [ ] On bulk regenerate completion, a summary banner displays the count of sent / skipped / failed
- [ ] "Email credentials to all" retains its current one-click behaviour (no extra confirmation needed, as it skips already-sent reviewers)
- [ ] All new buttons and modals are keyboard accessible and have appropriate `aria-label` attributes
- [ ] The new GET credentials endpoint is covered by a PHPUnit test; the per-row loading and modal interactions are verifiable in the E2E journey test

---

## Technical Notes

### Backend — new GET credentials endpoint

**File:** `includes/rest/class-rest-reviewers.php`

Register:
```
GET /sessions/{id}/reviewers/{reviewer_id}/credentials
callback: RestReviewers::get_credentials()
permission_callback: coordinator cap check (same as existing reviewer endpoints)
```

Handler calls a new `ReviewerProvisionService::get_reviewer_credentials(int $session_id, int $reviewer_id): array|\WP_Error`.

Service method:
1. Fetches reviewer row from `PanelRepository`.
2. Validates `session_id` matches reviewer's panel session.
3. Returns `WP_Error` if reviewer has no credentials yet.
4. Calls `TokenService::decrypt_password($reviewer['password_encrypted'])` for the plain password.
5. Returns `[ 'token' => $token, 'password' => $plain, 'login_url' => PluginSettings::portal_url_with_token($token) ]`.

**Security note:** This endpoint exposes the plain password over the REST API. It MUST be protected by the coordinator capability check. The password is already stored encrypted in the DB; decryption happens server-side, returned over HTTPS.

### Frontend — per-row loading states

**File:** `src/coordinator/components/PanelReviewersStep.jsx`

`ReviewerTableRow` currently receives `onProvision` and `onResend` as void callbacks (called, not awaited internally). The parent (`PanelReviewerTable`) calls `onProvision` / `onResend` as `async` closures. To add per-row loading:

- Lift `sending` state into `ReviewerTableRow` (similar to existing `saving` / `deleting` states).
- Change the prop signature: pass `onProvision(setRowNotice, setSending)` and `onResend(setRowNotice, setSending)` — OR, more cleanly, change `onProvision` / `onResend` to be Promise-returning callbacks and let the row manage its `sending` state internally by wrapping the call.

Recommended: make `onProvision` / `onResend` return Promises; add `const [sending, setSending] = useState(false)` in `ReviewerTableRow`; wrap the call in `setSending(true)` / `setSending(false)`.

Add a `rowNotice` state (`{ variant, message } | null`) to `ReviewerTableRow` for inline feedback, rendered below the action buttons (dismissible `<Notice>`).

### Frontend — View credentials modal

New component `ReviewerCredentialsModal` (can live inline in `PanelReviewersStep.jsx` or extracted to a separate file if it grows).

Props: `{ reviewerId, reviewerName, sessionId, onClose }`

On mount: fetches `GET /sessions/{sessionId}/reviewers/{reviewerId}/credentials`. Shows skeleton while loading.

Fields:
- Portal URL: `<input readonly>` + copy button
- Token: `<input readonly>` + copy button
- Password: `<input type="password">` with show/hide toggle + copy button

Copy-to-clipboard: use `navigator.clipboard.writeText()`.

"Regenerate" flow: local `regenerating` state, calls `POST generate-credentials` via existing `post()` helper, on success re-fetches credentials and shows them.

### Frontend — Bulk confirmation modal

New component `ConfirmModal` (or reuse any existing modal primitive).

For "Resend to all": simple confirm + cancel (no typed confirmation needed — this is a reversible email action).

For "Regenerate all credentials": typed confirmation input. Use a controlled `<input>` checked against the string `"REGENERATE"`. Confirm button disabled until match.

### Components involved

| Layer | File(s) |
|---|---|
| REST handler | `includes/rest/class-rest-reviewers.php` |
| Service | `includes/services/ReviewerProvisionService.php` |
| UI | `src/coordinator/components/PanelReviewersStep.jsx` |
| Shared | `src/shared/components` (Button, Notice — already exist) |

### API summary

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/sessions/{id}/reviewers/{reviewer_id}/credentials` | Retrieve current token + decrypted password |
| `POST` | `/sessions/{id}/reviewers/{reviewer_id}/generate-credentials` | Regenerate password + resend email (existing) |
| `POST` | `/sessions/{id}/send-all-credentials` | Bulk send/resend (existing, `force` param) |

No new database columns or migrations required.

---

## Dependencies

**Prerequisite stories:** None — builds directly on existing credential infrastructure from Story 3-5 / 3-6.

**Blocked stories:** None.

**External dependencies:**
- SMTP must be configured for email sending (coordinator is warned in the existing SMTP-not-configured UX path)

---

## Definition of Done

- [ ] Code implemented on feature branch
- [ ] `GET /sessions/{id}/reviewers/{reviewer_id}/credentials` endpoint registered and working
- [ ] PHPUnit test covering the new GET endpoint (happy path + reviewer-not-found + no-credentials cases)
- [ ] Per-row loading and inline notice verified in the browser (golden path: send → spinner → success chip)
- [ ] View credentials modal opens, displays token and password, copy buttons work
- [ ] Regenerate inside modal confirmed working (new credentials appear in modal after POST)
- [ ] "Resend to all" confirmation uses React modal (no `window.confirm()`)
- [ ] "Regenerate all credentials" modal with typed confirmation working end-to-end
- [ ] No regressions on existing bulk send or per-row edit/delete flows
- [ ] All interactive elements keyboard accessible
- [ ] Build passes (`npm run build`)
- [ ] Acceptance criteria all checked

---

## Story Points Breakdown

- **Backend (new GET endpoint + service method + test):** 1 point
- **Frontend — per-row loading + inline notices:** 1 point
- **Frontend — View credentials modal:** 2 points
- **Frontend — Bulk confirmation modals + Regenerate all button:** 1 point
- **Total:** 5 points

**Rationale:** The backend change is small (one new endpoint, one service method). The frontend work is moderate: per-row state lift is mechanical, the credentials modal is the largest piece, and bulk modal work is straightforward given the existing `bulkSending` state.

---

## Additional Notes

- The existing per-row `onProvision` and `onResend` closures in `PanelReviewerTable` already call `onRefreshReviewers()` on success. Keep that — it re-fetches the reviewer list so the Access column updates correctly.
- `TokenService::decrypt_password()` exists at `includes/services/TokenService.php:59`. The service needs to be instantiated in the new `get_reviewer_credentials()` method.
- The portal URL pattern is `PluginSettings::portal_url_with_token($token)` — use this, not a hardcoded path.
- "View credentials" button should only appear if `has_credentials === true`. Reviewers with no credentials yet should only show "Send credentials".

---

## Progress Tracking

**Status History:**
- 2026-06-13: Created by David (Scrum Master workflow)

**Actual Effort:** TBD

---

**This story was created using BMAD Method v6 - Phase 4 (Implementation Planning)**
