<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SmtpService;

final class Admin_Email_Settings
{
    private const NONCE_ACTION = 'scorva_email_save';
    private const NONCE_FIELD  = '_scorva_email_nonce';

    private const TTL_OPTIONS = [1, 3, 7, 14, 30];
    private const TTL_LABELS  = [1 => '1 day', 3 => '3 days', 7 => '7 days', 14 => '14 days', 30 => '30 days'];

    public static function render_page(): void
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'scorva'));
        }

        $saved    = false;
        $save_err = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[self::NONCE_FIELD])) {
            if (!check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD)) {
                $save_err = __('Security check failed.', 'scorva');
            } else {
                // SMTP settings — SmtpService::sanitize handles password keep-if-empty + encryption
                $smtp_input = [
                    'host'       => $_POST['smtp_host'] ?? '',
                    'port'       => $_POST['smtp_port'] ?? 587,
                    'encryption' => $_POST['smtp_encryption'] ?? 'tls',
                    'username'   => $_POST['smtp_username'] ?? '',
                    'password'   => $_POST['smtp_password'] ?? '',
                    'from_email' => $_POST['smtp_from_email'] ?? '',
                ];
                update_option(SmtpService::OPTION_KEY, SmtpService::sanitize($smtp_input));

                // Partial merge into pr_plugin_settings — only email-owned keys
                $current                              = get_option(PluginSettings::OPTION_KEY, []);
                $current                              = is_array($current) ? $current : [];
                $current['from_name']                 = sanitize_text_field((string) ($_POST['from_name'] ?? ''));
                $current['reply_to']                  = sanitize_email((string) ($_POST['reply_to'] ?? ''));
                $current['login_url']                 = esc_url_raw((string) ($_POST['login_url'] ?? ''));
                $current['notify_rubric_open']        = !empty($_POST['notify_rubric_open']);
                $current['notify_session_closed']     = !empty($_POST['notify_session_closed']);
                $current['reviewer_session_ttl_days'] = self::sanitize_ttl((int) ($_POST['reviewer_session_ttl_days'] ?? 7));
                update_option(PluginSettings::OPTION_KEY, $current);

                $saved = true;
            }
        }

        $settings = PluginSettings::get();
        $smtp_svc = new SmtpService();
        $smtp     = $smtp_svc->get_settings();
        $cur_ttl  = self::sanitize_ttl((int) ($settings['reviewer_session_ttl_days'] ?? 7));
        ?>
        <div class="scorva-admin-page">
            <div class="scorva-admin-page__accent-bar"></div>

            <form method="post" id="scorva-email-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <?php self::render_page_header($smtp_svc); ?>
                <?php self::render_smtp_card($smtp); ?>
                <?php self::render_sender_identity_card($settings); ?>
                <?php self::render_portal_url_card($settings); ?>
                <?php self::render_notifications_card($settings); ?>
                <?php self::render_session_duration_card($cur_ttl); ?>
                <?php self::render_save_bar($saved, $save_err); ?>
            </form>
        </div>

        <?php self::render_app_password_modal(); ?>
        <div id="scorva-toast" class="scorva-toast" aria-live="polite"></div>

        <script>
        (function () {
            /* ── app password help dialog ───────────────────────── */
            var helpBtn    = document.getElementById('scorva-app-pwd-help');
            var helpDialog = document.getElementById('scorva-app-pwd-dialog');
            var helpClose  = document.getElementById('scorva-app-pwd-close');

            function closeHelpDialog() {
                if (helpDialog && helpDialog.open) helpDialog.close();
            }

            if (helpBtn && helpDialog) {
                helpBtn.addEventListener('click', function () { helpDialog.showModal(); });

                // Close button — stopPropagation prevents WP admin delegated handlers
                if (helpClose) {
                    helpClose.addEventListener('click', function (e) {
                        e.stopPropagation();
                        closeHelpDialog();
                    });
                }

                // Backdrop click (click lands on <dialog> itself, not its children)
                helpDialog.addEventListener('click', function (e) {
                    if (e.target === helpDialog) closeHelpDialog();
                });

                // ESC — capture phase beats any jQuery stopPropagation in WP admin
                document.addEventListener('keydown', function (e) {
                    if (!helpDialog.open) return;
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        e.stopPropagation();
                        closeHelpDialog();
                    }
                }, true /* capture */);

                // Fallback: native cancel event (fired by browser on ESC for <dialog>)
                helpDialog.addEventListener('cancel', function (e) {
                    e.preventDefault(); // we handle close ourselves
                    closeHelpDialog();
                });
            }

            /* ── segmented controls ─────────────────────────────── */
            document.querySelectorAll('.scorva-segment').forEach(function (group) {
                group.querySelectorAll('.scorva-segment__btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        group.querySelectorAll('.scorva-segment__btn').forEach(function (b) {
                            b.classList.remove('scorva-segment__btn--active');
                        });
                        btn.classList.add('scorva-segment__btn--active');
                        var target = group.dataset.target;
                        if (target) {
                            var el = document.getElementById(target);
                            if (el) el.value = btn.dataset.value;
                        }
                    });
                });
            });

            /* ── password eye toggle ────────────────────────────── */
            var eyeBtn   = document.getElementById('scorva-smtp-eye');
            var pwdInput = document.getElementById('scorva-smtp-password');
            if (eyeBtn && pwdInput) {
                eyeBtn.addEventListener('click', function () {
                    var visible = pwdInput.type === 'text';
                    pwdInput.type = visible ? 'password' : 'text';
                    var open   = eyeBtn.querySelector('.scorva-eye-open');
                    var closed = eyeBtn.querySelector('.scorva-eye-closed');
                    if (open)   open.style.display   = visible ? '' : 'none';
                    if (closed) closed.style.display = visible ? 'none' : '';
                });
            }

            /* ── test email ─────────────────────────────────────── */
            var testBtn    = document.getElementById('scorva-smtp-test-btn');
            var testInput  = document.getElementById('scorva-smtp-test-to');
            var testResult = document.getElementById('scorva-smtp-test-result');
            if (testBtn && testInput) {
                testBtn.addEventListener('click', function () {
                    var to = testInput.value.trim();
                    testBtn.disabled = true;
                    testBtn.textContent = <?php echo wp_json_encode(__('Sending…', 'scorva')); ?>;
                    if (testResult) { testResult.textContent = ''; testResult.className = 'scorva-test-result'; }
                    fetch(<?php echo wp_json_encode(rest_url('scorva/v1/settings/smtp/test')); ?>, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>,
                        },
                        body: JSON.stringify({ to: to }),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        testBtn.disabled = false;
                        testBtn.textContent = <?php echo wp_json_encode(__('Send →', 'scorva')); ?>;
                        if (!testResult) return;
                        if (data.sent) {
                            testResult.textContent = '✓ ' + <?php echo wp_json_encode(__('Test email sent to', 'scorva')); ?> + ' ' + (data.to || to);
                            testResult.className = 'scorva-test-result scorva-test-result--success';
                            setTimeout(function () { testResult.textContent = ''; testResult.className = 'scorva-test-result'; }, 5000);
                        } else {
                            testResult.textContent = '✕ ' + (data.message || <?php echo wp_json_encode(__('Send failed', 'scorva')); ?>);
                            testResult.className = 'scorva-test-result scorva-test-result--error';
                        }
                    })
                    .catch(function () {
                        testBtn.disabled = false;
                        testBtn.textContent = <?php echo wp_json_encode(__('Send →', 'scorva')); ?>;
                        if (testResult) {
                            testResult.textContent = '✕ ' + <?php echo wp_json_encode(__('Network error', 'scorva')); ?>;
                            testResult.className = 'scorva-test-result scorva-test-result--error';
                        }
                    });
                });
            }

            /* ── save bar status ────────────────────────────────── */
            var saveStatus = document.getElementById('scorva-save-status');
            <?php if ($saved): ?>
            if (saveStatus) {
                saveStatus.textContent = <?php echo wp_json_encode(__('Settings saved.', 'scorva')); ?>;
                saveStatus.style.color = 'var(--pr-color-success)';
                setTimeout(function () { saveStatus.textContent = ''; }, 3000);
            }
            <?php elseif ($save_err !== ''): ?>
            if (saveStatus) {
                saveStatus.textContent = <?php echo wp_json_encode($save_err); ?>;
                saveStatus.style.color = 'var(--pr-color-danger)';
            }
            <?php endif; ?>
        }());
        </script>
        <?php
    }

    private static function render_page_header(SmtpService $smtp_svc): void
    {
        $is_configured = $smtp_svc->is_configured();
        $host          = trim((string) ($smtp_svc->get_settings()['host'] ?? ''));
        ?>
        <div class="scorva-page-header">
            <div class="scorva-page-header__title-group">
                <span class="scorva-page-header__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                </span>
                <div>
                    <h1 class="scorva-page-header__title">
                        <?php esc_html_e('Email Settings', 'scorva'); ?>
                    </h1>
                    <p class="scorva-page-header__subtitle">
                        <?php esc_html_e('Configure SMTP delivery, sender identity, and notifications.', 'scorva'); ?>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($is_configured): ?>
        <div class="scorva-smtp-status scorva-smtp-status--ok">
            <span>●</span>
            <?php echo esc_html(
                sprintf(
                    /* translators: %s: SMTP host */
                    __('SMTP configured — mail delivered via %s', 'scorva'),
                    $host
                )
            ); ?>
        </div>
        <?php else: ?>
        <div class="scorva-smtp-status scorva-smtp-status--warn">
            <span>⚠</span>
            <?php esc_html_e('No SMTP host configured — Scorva will use WordPress default PHP mail, which is often blocked or flagged as spam.', 'scorva'); ?>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * @param array<string, mixed> $smtp
     */
    private static function render_smtp_card(array $smtp): void
    {
        $has_password = trim((string) ($smtp['password'] ?? '')) !== '';
        $enc          = (string) ($smtp['encryption'] ?? 'tls');
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="2" width="20" height="8" rx="2"/>
                    <rect x="2" y="14" width="20" height="8" rx="2"/>
                    <line x1="6" y1="6" x2="6.01" y2="6"/>
                    <line x1="6" y1="18" x2="6.01" y2="18"/>
                </svg>
                <?php esc_html_e('SMTP Delivery', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <div class="scorva-form-grid">
                    <div class="scorva-form-group scorva-form-group--full">
                        <label class="scorva-label" for="scorva-smtp-host">
                            <?php esc_html_e('SMTP Host', 'scorva'); ?>
                        </label>
                        <input type="text" id="scorva-smtp-host" name="smtp_host"
                               class="scorva-input"
                               value="<?php echo esc_attr((string) ($smtp['host'] ?? '')); ?>"
                               placeholder="smtp.gmail.com">
                    </div>

                    <div class="scorva-form-group">
                        <label class="scorva-label" for="scorva-smtp-port">
                            <?php esc_html_e('Port', 'scorva'); ?>
                        </label>
                        <input type="number" id="scorva-smtp-port" name="smtp_port"
                               class="scorva-input scorva-input--port"
                               value="<?php echo esc_attr((string) ((int) ($smtp['port'] ?? 587))); ?>"
                               min="1" max="65535">
                    </div>

                    <div class="scorva-form-group">
                        <div class="scorva-label" id="scorva-enc-label">
                            <?php esc_html_e('Encryption', 'scorva'); ?>
                        </div>
                        <input type="hidden" name="smtp_encryption" id="scorva-smtp-encryption"
                               value="<?php echo esc_attr($enc); ?>">
                        <div class="scorva-segment" data-target="scorva-smtp-encryption"
                             role="group" aria-labelledby="scorva-enc-label">
                            <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $val => $label): ?>
                                <button type="button"
                                        class="scorva-segment__btn<?php echo $enc === $val ? ' scorva-segment__btn--active' : ''; ?>"
                                        data-value="<?php echo esc_attr($val); ?>">
                                    <?php echo esc_html($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="scorva-form-group scorva-form-group--full">
                        <label class="scorva-label" for="scorva-smtp-username">
                            <?php esc_html_e('Username', 'scorva'); ?>
                        </label>
                        <input type="text" id="scorva-smtp-username" name="smtp_username"
                               class="scorva-input" autocomplete="username"
                               value="<?php echo esc_attr((string) ($smtp['username'] ?? '')); ?>">
                    </div>

                    <div class="scorva-form-group scorva-form-group--full">
                        <label class="scorva-label scorva-label--with-help" for="scorva-smtp-password">
                            <?php esc_html_e('Password', 'scorva'); ?>
                            <button type="button" id="scorva-app-pwd-help" class="scorva-help-btn"
                                    aria-label="<?php esc_attr_e('How to get an app password', 'scorva'); ?>"
                                    aria-haspopup="dialog">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </button>
                        </label>
                        <div class="scorva-input-wrap">
                            <input type="password" id="scorva-smtp-password" name="smtp_password"
                                   class="scorva-input" autocomplete="current-password"
                                   placeholder="<?php echo $has_password ? esc_attr('••••••••') : ''; ?>">
                            <button type="button" id="scorva-smtp-eye" class="scorva-eye-btn"
                                    aria-label="<?php esc_attr_e('Show password', 'scorva'); ?>">
                                <svg class="scorva-eye-open" width="16" height="16" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="scorva-eye-closed" width="16" height="16" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round"
                                     aria-hidden="true" style="display:none">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                        <p class="scorva-field-hint">
                            <?php esc_html_e('Use an app password, not your regular email password. Leave blank to keep the existing password.', 'scorva'); ?>
                        </p>
                    </div>

                    <div class="scorva-form-group scorva-form-group--full">
                        <label class="scorva-label" for="scorva-smtp-from-email">
                            <?php esc_html_e('From Email', 'scorva'); ?>
                        </label>
                        <input type="email" id="scorva-smtp-from-email" name="smtp_from_email"
                               class="scorva-input"
                               value="<?php echo esc_attr((string) ($smtp['from_email'] ?? '')); ?>">
                        <p class="scorva-field-hint">
                            <?php esc_html_e('Should match the SMTP account to avoid spam filtering.', 'scorva'); ?>
                        </p>
                    </div>
                </div>

                <div class="scorva-test-section">
                    <div class="scorva-test-section__header">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                        <strong><?php esc_html_e('Send test email', 'scorva'); ?></strong>
                    </div>
                    <p class="scorva-test-section__hint">
                        <?php esc_html_e('Sends to your WordPress admin email if left blank.', 'scorva'); ?>
                    </p>
                    <div class="scorva-test-row">
                        <input type="email" id="scorva-smtp-test-to" class="scorva-input"
                               placeholder="<?php echo esc_attr(wp_get_current_user()->user_email ?: 'your@email.com'); ?>"
                               aria-label="<?php esc_attr_e('Test recipient address (optional)', 'scorva'); ?>">
                        <button type="button" id="scorva-smtp-test-btn" class="scorva-btn scorva-btn--secondary">
                            <?php esc_html_e('Send →', 'scorva'); ?>
                        </button>
                    </div>
                    <p id="scorva-smtp-test-result" class="scorva-test-result" aria-live="polite"></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function render_sender_identity_card(array $settings): void
    {
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <?php esc_html_e('Sender Identity', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <div class="scorva-form-grid">
                    <div class="scorva-form-group">
                        <label class="scorva-label" for="scorva-from-name">
                            <?php esc_html_e('From Name', 'scorva'); ?>
                        </label>
                        <input type="text" id="scorva-from-name" name="from_name"
                               class="scorva-input"
                               value="<?php echo esc_attr((string) ($settings['from_name'] ?? '')); ?>"
                               placeholder="<?php echo esc_attr(PluginSettings::app_short_name()); ?>">
                        <p class="scorva-field-hint">
                            <?php esc_html_e('Displayed as sender name in email clients.', 'scorva'); ?>
                        </p>
                    </div>
                    <div class="scorva-form-group">
                        <label class="scorva-label" for="scorva-reply-to">
                            <?php esc_html_e('Reply-To Address', 'scorva'); ?>
                        </label>
                        <input type="email" id="scorva-reply-to" name="reply_to"
                               class="scorva-input"
                               value="<?php echo esc_attr((string) ($settings['reply_to'] ?? '')); ?>">
                        <p class="scorva-field-hint">
                            <?php esc_html_e('Optional. If set, replies go here instead of From Email.', 'scorva'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function render_portal_url_card(array $settings): void
    {
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
                <?php esc_html_e('Reviewer Portal URL', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <div class="scorva-form-group">
                    <label class="scorva-label" for="scorva-login-url">
                        <?php esc_html_e('Portal Base URL', 'scorva'); ?>
                    </label>
                    <input type="url" id="scorva-login-url" name="login_url"
                           class="scorva-input"
                           value="<?php echo esc_attr((string) ($settings['login_url'] ?? '')); ?>"
                           placeholder="<?php echo esc_attr(PluginSettings::portal_url()); ?>">
                    <p class="scorva-field-hint">
                        <?php esc_html_e('This URL is embedded in reviewer invitation emails. Change it only if the reviewer portal is served from a custom domain or subdirectory.', 'scorva'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function render_notifications_card(array $settings): void
    {
        $rows = [
            [
                'key'   => 'notify_rubric_open',
                'label' => __('Notify reviewers when marking opens', 'scorva'),
                'desc'  => __('Sends RubricOpenEmail to all assigned reviewers when the rubric is confirmed.', 'scorva'),
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6.002 6.002 0 0 0-4-5.659V5a2 2 0 1 0-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>',
            ],
            [
                'key'   => 'notify_session_closed',
                'label' => __('Notify coordinator when session closes', 'scorva'),
                'desc'  => __('Sends SessionClosedEmail to the coordinator when a project session is closed.', 'scorva'),
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>',
            ],
        ];
        $last = count($rows) - 1;
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6.002 6.002 0 0 0-4-5.659V5a2 2 0 1 0-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
                </svg>
                <?php esc_html_e('Notification Triggers', 'scorva'); ?>
            </div>
            <div class="scorva-card__body scorva-card__body--no-pad">
                <?php foreach ($rows as $i => $row):
                    $checked = !empty($settings[$row['key']]);
                    $is_last = ($i === $last);
                    ?>
                    <div class="scorva-notification-row<?php echo $is_last ? ' scorva-notification-row--last' : ''; ?>">
                        <div class="scorva-notification-row__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" aria-hidden="true">
                                <?php echo $row['icon']; // phpcs:ignore -- SVG path data, no user input ?>
                            </svg>
                        </div>
                        <div class="scorva-notification-row__text">
                            <span class="scorva-notification-row__label">
                                <?php echo esc_html($row['label']); ?>
                            </span>
                            <span class="scorva-notification-row__desc">
                                <?php echo esc_html($row['desc']); ?>
                            </span>
                        </div>
                        <label class="scorva-toggle scorva-notification-row__toggle">
                            <input type="checkbox"
                                   name="<?php echo esc_attr($row['key']); ?>"
                                   value="1"
                                   <?php checked($checked); ?>>
                            <span class="scorva-toggle__track"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function render_session_duration_card(int $cur_ttl): void
    {
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <?php esc_html_e('Reviewer Session Duration', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <p class="scorva-label" style="margin-bottom:10px !important">
                    <?php esc_html_e('How long a reviewer stays logged in after token authentication', 'scorva'); ?>
                </p>
                <input type="hidden" name="reviewer_session_ttl_days" id="scorva-ttl-days"
                       value="<?php echo esc_attr((string) $cur_ttl); ?>">
                <div class="scorva-segment" data-target="scorva-ttl-days"
                     role="group" aria-label="<?php esc_attr_e('Reviewer session duration', 'scorva'); ?>">
                    <?php foreach (self::TTL_OPTIONS as $days): ?>
                        <button type="button"
                                class="scorva-segment__btn<?php echo $cur_ttl === $days ? ' scorva-segment__btn--active' : ''; ?>"
                                data-value="<?php echo esc_attr((string) $days); ?>">
                            <?php echo esc_html(self::TTL_LABELS[$days] ?? "{$days}d"); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="scorva-field-hint" style="margin-top:10px !important">
                    <?php esc_html_e('Applies to new logins only. Reviewers who are already logged in keep their current session.', 'scorva'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    private static function render_app_password_modal(): void
    {
        ?>
        <dialog id="scorva-app-pwd-dialog" class="scorva-help-dialog"
                aria-labelledby="scorva-app-pwd-title">
            <div class="scorva-help-dialog__panel">
                <div class="scorva-help-dialog__header">
                    <h2 id="scorva-app-pwd-title" class="scorva-help-dialog__title">
                        <?php esc_html_e('How to get an App Password', 'scorva'); ?>
                    </h2>
                    <button type="button" id="scorva-app-pwd-close"
                            class="scorva-help-dialog__close"
                            aria-label="<?php esc_attr_e('Close', 'scorva'); ?>"
                            autofocus>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>

                <div class="scorva-help-dialog__body">

                    <!-- Gmail -->
                    <div class="scorva-help-section">
                        <h3 class="scorva-help-section__title">Gmail</h3>
                        <p class="scorva-help-section__prereq">
                            <?php esc_html_e('Prerequisites: Google account with 2-Step Verification enabled.', 'scorva'); ?>
                        </p>
                        <ol class="scorva-help-steps">
                            <li><?php esc_html_e('Go to myaccount.google.com and sign in.', 'scorva'); ?></li>
                            <li><?php esc_html_e('In the search bar at the top of the page, type "App passwords" and select it from the results.', 'scorva'); ?></li>
                            <li><?php esc_html_e('In the "App name" field, enter a label (e.g. "Scorva WordPress") and click Create.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Copy the 16-character password shown — use this as your SMTP password.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Click Done.', 'scorva'); ?></li>
                        </ol>
                        <p class="scorva-help-settings-note">
                            <?php esc_html_e('SMTP settings: Host: smtp.gmail.com · Port: 587 · Encryption: TLS · Username: your Gmail address', 'scorva'); ?>
                        </p>
                    </div>

                    <div class="scorva-help-divider"></div>

                    <!-- Microsoft -->
                    <div class="scorva-help-section">
                        <h3 class="scorva-help-section__title">
                            <?php esc_html_e('Microsoft (Outlook / Microsoft 365)', 'scorva'); ?>
                        </h3>
                        <p class="scorva-help-section__sub-heading">
                            <?php esc_html_e('Personal Outlook / Hotmail accounts:', 'scorva'); ?>
                        </p>
                        <ol class="scorva-help-steps">
                            <li><?php esc_html_e('Go to account.microsoft.com and sign in.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Click Security → Advanced security options.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Under "App passwords", click Create a new app password.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Copy the generated password and use it as your SMTP password.', 'scorva'); ?></li>
                        </ol>
                        <p class="scorva-help-section__sub-heading" style="margin-top:10px">
                            <?php esc_html_e('Microsoft 365 / work accounts:', 'scorva'); ?>
                        </p>
                        <ol class="scorva-help-steps">
                            <li><?php esc_html_e('Sign in at myaccount.microsoft.com.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Go to Security info → Add sign-in method.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Choose App password, click Add.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Give it a name (e.g. "Scorva") and click Next.', 'scorva'); ?></li>
                            <li><?php esc_html_e('Copy the generated password.', 'scorva'); ?></li>
                        </ol>
                        <p class="scorva-help-settings-note" style="margin-top:8px">
                            <?php esc_html_e('Note: Microsoft 365 accounts managed by an organisation may have app passwords disabled. Contact your IT department if the option is not available.', 'scorva'); ?>
                        </p>
                        <p class="scorva-help-settings-note">
                            <?php esc_html_e('SMTP settings: Host: smtp.office365.com · Port: 587 · Encryption: TLS · Username: your full email address', 'scorva'); ?>
                        </p>
                    </div>

                    <div class="scorva-help-divider"></div>

                    <!-- Other Providers -->
                    <div class="scorva-help-section">
                        <h3 class="scorva-help-section__title">
                            <?php esc_html_e('Other Providers', 'scorva'); ?>
                        </h3>
                        <p class="scorva-help-settings-note">
                            <?php esc_html_e('For other providers (e.g. Yahoo, Zoho, iCloud), search for "app password" or "SMTP password" in your provider\'s help centre. Many providers require 2-factor authentication to be enabled before app passwords are available.', 'scorva'); ?>
                        </p>
                    </div>

                </div>
            </div>
        </dialog>
        <?php
    }

    private static function render_save_bar(bool $saved, string $save_err): void
    {
        ?>
        <div class="scorva-save-bar">
            <span id="scorva-save-status" class="scorva-save-bar__status">
                <?php if ($saved): ?>
                    <span style="color:var(--pr-color-success)"><?php esc_html_e('Settings saved.', 'scorva'); ?></span>
                <?php elseif ($save_err !== ''): ?>
                    <span style="color:var(--pr-color-danger)"><?php echo esc_html($save_err); ?></span>
                <?php endif; ?>
            </span>
            <button type="submit" class="scorva-btn scorva-btn--primary">
                <?php esc_html_e('Save Email Settings', 'scorva'); ?>
            </button>
        </div>
        <?php
    }

    private static function sanitize_ttl(int $days): int
    {
        return in_array($days, self::TTL_OPTIONS, true) ? $days : 7;
    }
}
