# Story 26.1: SMTP readiness guard before sending reviewer credential emails

Status: draft

## Story

As a **coordinator about to email reviewer credentials**,
I want **a clear warning when SMTP is not configured (and visible per-recipient failures when sending)**,
So that credential emails don't silently vanish into a broken default `wp_mail()` transport.

## Background — current behaviour (do not guess)

- `SmtpService::is_configured()` exists (auth-rebuild Story 1, done 2026-06-12) and `GET` settings already expose `is_configured`.
- `SmtpService::send_mail()` (`includes/services/SmtpService.php:159-172`) **silently falls back to plain `wp_mail()`** when SMTP is not configured — it only hooks `phpmailer_init` when configured.
- `ReviewerProvisionService::generate_reviewer_credentials / resend / send_all` and the REST routes `generate-credentials` / `send-all-credentials` send credentials without any pre-flight SMTP check; `PanelReviewersStep.jsx` gives no transport status.
- The original review asked for a check "before allowing credential send" — partially stale (SMTP settings now exist) but the guard itself is still missing.

## Acceptance Criteria

1. **Given** SMTP is not configured
   **When** the coordinator opens the panel reviewers step (or wherever credential send buttons live)
   **Then** a visible notice states that emails will use the server's default mail transport and links to **Settings → SMTP**, with a "send anyway" path (do **not** hard-block — default `wp_mail` may work on managed hosts)

2. **Given** the REST credential-send endpoints
   **When** called while SMTP is unconfigured
   **Then** the response includes a machine-readable `smtp_configured: false` flag (UI decides presentation); sends still execute unless the client passed a `require_smtp` style guard

3. **Given** any credential send (single or send-all)
   **When** `send_mail` returns false for a recipient
   **Then** the REST response reports per-recipient failures (count + emails) instead of a blanket success, and the UI surfaces them; `credentials_sent_at` is only stamped on successful sends

4. **Given** the SMTP settings page
   **Then** the existing test-email endpoint (`POST /settings/smtp/test`) is linked from the warning notice so the coordinator can verify before bulk sending

## Tasks / Subtasks

- [ ] Expose `smtp_configured` in the reviewers/panel bootstrap payload (or fetch from existing SMTP settings endpoint with a coordinator-readable, secret-free shape)
- [ ] Track per-recipient send results in `ReviewerProvisionService::send_all` / `resend`; only set `credentials_sent_at` on success
- [ ] Notice + failure list in `PanelReviewersStep.jsx`; link to settings page and test send
- [ ] PHPUnit: REST response shapes for unconfigured SMTP and partial failure; update `ReviewerCredentialsTest`
- [ ] Rebuild assets

## Dev Notes

### Architecture compliance

- Keep the guard advisory, not blocking — aligns with `send_mail`'s existing graceful fallback.
- Secret-free exposure only: never return SMTP password/host details to the coordinator app, just the boolean.

### References

- `includes/services/SmtpService.php:106-172`, `includes/services/ReviewerProvisionService.php`, `includes/rest/class-rest-reviewers.php`, `src/coordinator/components/PanelReviewersStep.jsx`
- Reviewer auth rebuild plan (memory): stories 1–5 done, 6 pending
