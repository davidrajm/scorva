<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\ThemeNavBootstrap;

final class Admin_General_Settings
{
    private const NONCE_ACTION   = 'scorva_general_save';
    private const NONCE_FIELD    = '_scorva_general_nonce';

    /** Built-in fallback strings, shown in field-level error when admin clears a label. */
    private const LABEL_FALLBACKS = [
        'default_label_sr_no'             => 'Sr. No.',
        'default_label_reg_no'            => 'Reg No',
        'default_label_student'           => 'Student',
        'default_label_guide'             => 'Guide',
        'default_label_final_marks'       => 'Final Marks',
        'default_label_panel_coordinator' => 'Panel coordinator',
        'default_label_hod'               => 'Head of the Department',
        'default_label_reviewer_pattern'  => 'Reviewer {n}',
    ];

    public static function render_page(): void
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'scorva'));
        }

        $saved       = false;
        $save_err    = '';
        $empty_keys  = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[self::NONCE_FIELD])) {
            if (!check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD)) {
                $save_err = __('Security check failed.', 'scorva');
            } else {
                [$saved, $empty_keys] = self::handle_save();
            }
        }

        // Flash message from re-run bootstrap redirect
        $bootstrap_rerun = isset($_GET['bootstrap']) && $_GET['bootstrap'] === 'rerun'; // phpcs:ignore WordPress.Security.NonceVerification

        $settings = PluginSettings::get();
        ?>
        <div class="scorva-admin-page">
            <div class="scorva-admin-page__accent-bar"></div>

            <form method="post" id="scorva-general-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <?php self::render_page_header(); ?>

                <?php if ($bootstrap_rerun): ?>
                <div class="scorva-inline-notice scorva-inline-notice--info" style="margin-bottom:16px">
                    <?php esc_html_e('Nav bootstrap re-run. Check the Bootstrap Status row below for the result.', 'scorva'); ?>
                </div>
                <?php endif; ?>

                <?php self::render_identity_card($settings, $save_err); ?>
                <?php self::render_theme_nav_card($settings); ?>
                <?php self::render_labels_card($settings, $empty_keys); ?>
                <?php self::render_save_bar($saved, $save_err); ?>
            </form>
        </div>

        <div id="scorva-toast" class="scorva-toast" aria-live="polite"></div>

        <script>
        (function () {
            /* ── conditional menu-label field ────────────────────── */
            var autoToggle  = document.getElementById('scorva-theme-nav-auto');
            var labelWrap   = document.getElementById('scorva-menu-label-wrap');

            function syncMenuLabelVisibility() {
                if (!autoToggle || !labelWrap) return;
                labelWrap.classList.toggle('scorva-conditional-field--visible', autoToggle.checked);
            }

            if (autoToggle) {
                autoToggle.addEventListener('change', syncMenuLabelVisibility);
                syncMenuLabelVisibility();
            }

            /* ── label preview (live update) ─────────────────────── */
            var previewRow  = document.getElementById('scorva-label-preview-row');
            var labelInputs = {};
            var labelKeys   = <?php echo wp_json_encode(array_keys(self::LABEL_FALLBACKS)); ?>;

            labelKeys.forEach(function (k) {
                var slug = k.replace('default_label_', '').replace(/_/g, '-');
                var el   = document.getElementById('scorva-label-' + slug);
                if (el) {
                    labelInputs[k] = el;
                    el.addEventListener('input', updatePreview);
                }
            });

            function getVal(k) {
                var el = labelInputs[k];
                return el ? (el.value.trim() || el.dataset.fallback || '') : '';
            }

            function updatePreview() {
                if (!previewRow) return;
                var srNo   = getVal('default_label_sr_no');
                var regNo  = getVal('default_label_reg_no');
                var stu    = getVal('default_label_student');
                var guide  = getVal('default_label_guide');
                var marks  = getVal('default_label_final_marks');
                var coord  = getVal('default_label_panel_coordinator');
                var hod    = getVal('default_label_hod');
                var revPat = getVal('default_label_reviewer_pattern');
                var rev1   = revPat.replace('{n}', '1');
                var rev2   = revPat.replace('{n}', '2');

                var th = function (t) { return '<th style="border:1px solid #999;padding:4px 8px;background:#f6f7f7;font-weight:600;font-size:12px;white-space:nowrap">' + t + '</th>'; };
                var td = function (t) { return '<td style="border:1px solid #999;padding:4px 8px;font-size:12px">' + t + '</td>'; };

                var table = '<table style="border-collapse:collapse;width:100%;margin-bottom:12px">' +
                    '<thead><tr>' + th(srNo) + th(regNo) + th(stu) + th(guide) + th(marks) + '</tr></thead>' +
                    '<tbody><tr>' + td('1') + td('22CS001') + td('[Preview]') + td('Prof. X') + td('—') + '</tr></tbody>' +
                    '</table>';

                var sig = '<div style="font-size:12px;color:#555;display:grid;grid-template-columns:1fr 1fr;gap:6px 24px">' +
                    '<div>' + coord + ': _______________</div>' +
                    '<div>' + rev1 + ': _______________</div>' +
                    '<div>' + hod + ': _______________</div>' +
                    '<div>' + rev2 + ': _______________</div>' +
                    '</div>';

                previewRow.innerHTML = table + sig;
            }

            updatePreview();

            /* ── save bar feedback ───────────────────────────────── */
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

    /**
     * @return array{0: bool, 1: list<string>}  [saved, empty_keys_that_fell_back]
     */
    private static function handle_save(): array
    {
        $empty_keys = [];
        $current    = get_option(PluginSettings::OPTION_KEY, []);
        $current    = is_array($current) ? $current : [];

        // Application Identity
        $app_name = sanitize_text_field(trim((string) ($_POST['app_display_name'] ?? '')));
        if ($app_name === '') {
            $app_name = PluginSettings::DEFAULT_APP_DISPLAY_NAME;
        }
        $current['app_display_name'] = $app_name;

        // Theme Navigation
        $current['theme_nav_auto_bootstrap_enabled'] = !empty($_POST['theme_nav_auto_bootstrap_enabled']);
        $menu_label = sanitize_text_field(trim((string) ($_POST['theme_nav_menu_label'] ?? '')));
        if ($menu_label === '') {
            $menu_label = PluginSettings::DEFAULT_THEME_NAV_MENU_LABEL;
        }
        $current['theme_nav_menu_label']   = $menu_label;
        $current['theme_nav_bridge_enabled'] = !empty($_POST['theme_nav_bridge_enabled']);

        // Default Report Labels
        foreach (self::LABEL_FALLBACKS as $key => $fallback) {
            $val = sanitize_text_field(trim((string) ($_POST[$key] ?? '')));
            if ($val === '') {
                $empty_keys[]   = $key;
                $current[$key]  = $fallback;
            } else {
                $current[$key] = $val;
            }
        }

        update_option(PluginSettings::OPTION_KEY, $current);
        return [true, $empty_keys];
    }

    private static function render_page_header(): void
    {
        ?>
        <div class="scorva-page-header">
            <div class="scorva-page-header__title-group">
                <span class="scorva-page-header__icon">
                    <!-- cog-6-tooth -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="scorva-page-header__title">
                        <?php esc_html_e('General Settings', 'scorva'); ?>
                    </h1>
                    <p class="scorva-page-header__subtitle">
                        <?php esc_html_e('Application identity, theme navigation, and report label defaults.', 'scorva'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function render_identity_card(array $settings, string $save_err): void
    {
        $app_name = (string) ($settings['app_display_name'] ?? '');
        if ($app_name === '') {
            $app_name = PluginSettings::DEFAULT_APP_DISPLAY_NAME;
        }
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <!-- identification icon -->
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                    <path d="M16 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                    <path d="M8 10h.01M8 14h8"/>
                </svg>
                <?php esc_html_e('Application Identity', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <div class="scorva-form-group">
                    <label class="scorva-label" for="scorva-app-display-name">
                        <?php esc_html_e('Display Name', 'scorva'); ?>
                    </label>
                    <input type="text" id="scorva-app-display-name" name="app_display_name"
                           class="scorva-input<?php echo $save_err !== '' ? ' scorva-input--error' : ''; ?>"
                           value="<?php echo esc_attr($app_name); ?>"
                           maxlength="120">
                    <p class="scorva-field-hint">
                        <?php esc_html_e('This name appears in the coordinator and reviewer workspace headers and as a fallback sender name in emails. Default: \'Scorva\'.', 'scorva'); ?>
                    </p>
                    <details class="scorva-expand" style="margin-top:8px">
                        <summary class="scorva-expand__trigger"><?php esc_html_e('How is this name used?', 'scorva'); ?></summary>
                        <ul class="scorva-expand__body" style="margin:8px 0 0 16px;font-size:13px;color:var(--pr-color-text-muted)">
                            <li><?php esc_html_e('Coordinator workspace top-bar wordmark', 'scorva'); ?></li>
                            <li><?php esc_html_e('Reviewer portal greeting', 'scorva'); ?></li>
                            <li><?php esc_html_e('Fallback From Name in emails (when Email Settings From Name is blank)', 'scorva'); ?></li>
                            <li><?php esc_html_e('Capability display labels', 'scorva'); ?></li>
                        </ul>
                    </details>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function render_theme_nav_card(array $settings): void
    {
        $auto_enabled  = !empty($settings['theme_nav_auto_bootstrap_enabled']);
        $menu_label    = (string) ($settings['theme_nav_menu_label'] ?? PluginSettings::DEFAULT_THEME_NAV_MENU_LABEL);
        $bridge_on     = !empty($settings['theme_nav_bridge_enabled']);

        $status_raw    = ThemeNavBootstrap::bootstrap_status();
        $stored_data   = (array) (get_option(ThemeNavBootstrap::OPTION_BOOTSTRAP, []) ?: []);
        $bootstrap_at  = substr((string) ($stored_data['bootstrapped_at'] ?? ''), 0, 10);

        if ($status_raw === 'ok') {
            $status_dot   = '●';
            $status_color = 'var(--pr-color-success)';
            $status_text  = $bootstrap_at
                ? sprintf(__('Added successfully on %s', 'scorva'), esc_html($bootstrap_at))
                : __('Added successfully', 'scorva');
        } elseif ($status_raw === 'disabled') {
            $status_dot   = '○';
            $status_color = 'var(--pr-color-text-muted)';
            $status_text  = __('Auto-add is disabled', 'scorva');
        } else {
            $status_dot   = '●';
            $status_color = '#9a6700';
            $status_text  = $status_raw === 'no_menu_api'
                ? __('No menu API available — manual setup required', 'scorva')
                : __('Manual setup required — add the Reviews link under Appearance → Menus', 'scorva');
        }

        $rerun_url  = wp_nonce_url(
            admin_url('admin-post.php?action=scorva_rerun_bootstrap'),
            'scorva_rerun_bootstrap'
        );
        $reset_url  = wp_nonce_url(
            admin_url('admin-post.php?action=scorva_reset_theme_nav_notice'),
            'scorva_reset_theme_nav_notice'
        );
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <!-- bars-3 (nav/menu icon) -->
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
                <?php esc_html_e('Theme Navigation', 'scorva'); ?>
            </div>
            <div class="scorva-card__body scorva-card__body--no-pad">

                <!-- Row: auto-bootstrap toggle -->
                <div class="scorva-notification-row">
                    <div class="scorva-notification-row__text">
                        <span class="scorva-notification-row__label">
                            <?php esc_html_e('Auto-add "Reviews" to navigation menu', 'scorva'); ?>
                        </span>
                        <span class="scorva-notification-row__desc">
                            <?php esc_html_e('On activation, Scorva attempts to add a Reviews link to the primary WP nav menu.', 'scorva'); ?>
                        </span>
                    </div>
                    <label class="scorva-toggle scorva-notification-row__toggle">
                        <input type="checkbox" id="scorva-theme-nav-auto"
                               name="theme_nav_auto_bootstrap_enabled" value="1"
                               <?php checked($auto_enabled); ?>>
                        <span class="scorva-toggle__track"></span>
                    </label>
                </div>

                <!-- Conditional: menu label -->
                <div id="scorva-menu-label-wrap"
                     class="scorva-conditional-field<?php echo $auto_enabled ? ' scorva-conditional-field--visible' : ''; ?>"
                     style="padding:0 20px">
                    <div class="scorva-form-group" style="padding:12px 0">
                        <label class="scorva-label" for="scorva-menu-label">
                            <?php esc_html_e('Menu label', 'scorva'); ?>
                        </label>
                        <input type="text" id="scorva-menu-label" name="theme_nav_menu_label"
                               class="scorva-input" style="max-width:280px"
                               value="<?php echo esc_attr($menu_label); ?>" maxlength="80">
                        <p class="scorva-field-hint">
                            <?php esc_html_e('Auto-add only runs once on activation. If you change the menu label here, update your nav menu manually.', 'scorva'); ?>
                        </p>
                    </div>
                </div>

                <!-- Row: bridge filter toggle -->
                <div class="scorva-notification-row scorva-notification-row--last">
                    <div class="scorva-notification-row__text">
                        <span class="scorva-notification-row__label">
                            <?php esc_html_e('Expose Reviews URL via theme filter', 'scorva'); ?>
                        </span>
                        <span class="scorva-notification-row__desc">
                            <?php esc_html_e('Registers the pr_reviews_url filter for custom PHP themes that don\'t use WP nav menus.', 'scorva'); ?>
                        </span>
                    </div>
                    <label class="scorva-toggle scorva-notification-row__toggle">
                        <input type="checkbox" name="theme_nav_bridge_enabled" value="1"
                               <?php checked($bridge_on); ?>>
                        <span class="scorva-toggle__track"></span>
                    </label>
                </div>

                <!-- Bootstrap status row -->
                <div style="padding:16px 20px;border-top:1px solid var(--pr-color-border)">
                    <p class="scorva-label" style="margin-bottom:8px">
                        <?php esc_html_e('Bootstrap Status', 'scorva'); ?>
                    </p>
                    <p style="font-size:13px;margin:0 0 12px">
                        <span style="color:<?php echo esc_attr($status_color); ?>;margin-right:6px"><?php echo esc_html($status_dot); ?></span>
                        <?php echo esc_html($status_text); ?>
                    </p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a href="<?php echo esc_url($rerun_url); ?>"
                           class="scorva-btn scorva-btn--secondary"
                           style="height:32px;line-height:32px;padding:0 14px;font-size:13px;text-decoration:none">
                            <?php esc_html_e('Re-run bootstrap', 'scorva'); ?>
                        </a>
                        <a href="<?php echo esc_url($reset_url); ?>"
                           style="height:32px;line-height:32px;font-size:13px;color:var(--pr-color-text-muted);text-decoration:underline">
                            <?php esc_html_e('Reset dismissed notice', 'scorva'); ?>
                        </a>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     * @param list<string>         $empty_keys
     */
    private static function render_labels_card(array $settings, array $empty_keys): void
    {
        $vals = [];
        foreach (self::LABEL_FALLBACKS as $key => $fallback) {
            $stored = trim((string) ($settings[$key] ?? ''));
            $vals[$key] = $stored !== '' ? $stored : $fallback;
        }

        $col_headers = [
            'default_label_sr_no'       => __('Sr. No.', 'scorva'),
            'default_label_reg_no'      => __('Reg No', 'scorva'),
            'default_label_student'     => __('Student', 'scorva'),
            'default_label_guide'       => __('Guide', 'scorva'),
            'default_label_final_marks' => __('Final Marks', 'scorva'),
        ];
        $sig_headers = [
            'default_label_panel_coordinator' => __('Panel Coordinator', 'scorva'),
            'default_label_hod'               => __('HOD', 'scorva'),
            'default_label_reviewer_pattern'  => __('Reviewer Pattern', 'scorva'),
        ];
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <!-- table-cells icon -->
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <line x1="3" y1="9" x2="21" y2="9"/>
                    <line x1="3" y1="15" x2="21" y2="15"/>
                    <line x1="9" y1="3" x2="9" y2="21"/>
                    <line x1="15" y1="3" x2="15" y2="21"/>
                </svg>
                <?php esc_html_e('Default Report Labels', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">

                <p class="scorva-field-hint" style="margin-bottom:16px">
                    <?php esc_html_e('These are defaults for new sessions only. Existing sessions are not affected. Per-session overrides in panel report settings remain available.', 'scorva'); ?>
                </p>

                <!-- Column Headers grid -->
                <p class="scorva-label" style="margin-bottom:10px">
                    <?php esc_html_e('Column Headers', 'scorva'); ?>
                </p>
                <div class="scorva-label-grid">
                    <?php foreach ($col_headers as $key => $label):
                        $slug     = str_replace(['default_label_', '_'], ['', '-'], $key);
                        $has_err  = in_array($key, $empty_keys, true);
                        $fallback = self::LABEL_FALLBACKS[$key];
                        ?>
                        <div class="scorva-label-cell">
                            <label class="scorva-label-cell__key" for="scorva-label-<?php echo esc_attr($slug); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <input type="text"
                                   id="scorva-label-<?php echo esc_attr($slug); ?>"
                                   name="<?php echo esc_attr($key); ?>"
                                   class="scorva-input<?php echo $has_err ? ' scorva-input--error' : ''; ?>"
                                   data-fallback="<?php echo esc_attr($fallback); ?>"
                                   value="<?php echo esc_attr($vals[$key]); ?>">
                            <?php if ($has_err): ?>
                                <p class="scorva-field-error">
                                    <?php echo esc_html(
                                        sprintf(
                                            /* translators: %s: built-in default value */
                                            __('This label cannot be blank — the built-in default (%s) will be used.', 'scorva'),
                                            $fallback
                                        )
                                    ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Signature Defaults divider -->
                <div style="border-top:1px dashed var(--pr-color-border);margin:16px 0 12px;padding-top:12px">
                    <p style="font-size:13px;font-weight:600;color:var(--pr-color-text-muted);margin-bottom:10px">
                        <?php esc_html_e('Signature Block Defaults', 'scorva'); ?>
                    </p>
                    <div class="scorva-label-grid">
                        <?php foreach ($sig_headers as $key => $label):
                            $slug     = str_replace(['default_label_', '_'], ['', '-'], $key);
                            $has_err  = in_array($key, $empty_keys, true);
                            $fallback = self::LABEL_FALLBACKS[$key];
                            $is_full  = $key === 'default_label_reviewer_pattern';
                            ?>
                            <div class="scorva-label-cell<?php echo $is_full ? ' scorva-label-cell--full' : ''; ?>">
                                <label class="scorva-label-cell__key" for="scorva-label-<?php echo esc_attr($slug); ?>">
                                    <?php echo esc_html($label); ?>
                                </label>
                                <input type="text"
                                       id="scorva-label-<?php echo esc_attr($slug); ?>"
                                       name="<?php echo esc_attr($key); ?>"
                                       class="scorva-input<?php echo $has_err ? ' scorva-input--error' : ''; ?>"
                                       data-fallback="<?php echo esc_attr($fallback); ?>"
                                       value="<?php echo esc_attr($vals[$key]); ?>">
                                <?php if ($key === 'default_label_reviewer_pattern'): ?>
                                    <p class="scorva-field-hint">
                                        <?php esc_html_e('Use {n} as the placeholder for the reviewer number — e.g. "Examiner {n}" becomes "Examiner 1", "Examiner 2".', 'scorva'); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($has_err): ?>
                                    <p class="scorva-field-error">
                                        <?php echo esc_html(
                                            sprintf(
                                                /* translators: %s: built-in default value */
                                                __('This label cannot be blank — the built-in default (%s) will be used.', 'scorva'),
                                                $fallback
                                            )
                                        ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live preview -->
                <details class="scorva-expand" style="margin-top:16px">
                    <summary class="scorva-expand__trigger">
                        <?php esc_html_e('Preview labels in a sample table', 'scorva'); ?>
                    </summary>
                    <div class="scorva-expand__body" style="margin-top:10px;padding:12px;background:var(--pr-color-surface);border:1px solid var(--pr-color-border);border-radius:6px">
                        <div id="scorva-label-preview-row"></div>
                        <p style="font-size:11px;color:var(--pr-color-text-muted);margin:8px 0 0;font-style:italic">
                            <?php esc_html_e('Preview only — actual values depend on per-session settings.', 'scorva'); ?>
                        </p>
                    </div>
                </details>

            </div>
        </div>
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
                <?php esc_html_e('Save General Settings', 'scorva'); ?>
            </button>
        </div>
        <?php
    }

    public static function handle_rerun_bootstrap(): void
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('Forbidden', 'scorva'));
        }

        check_admin_referer('scorva_rerun_bootstrap');
        delete_option(ThemeNavBootstrap::OPTION_BOOTSTRAP);
        delete_option(ThemeNavBootstrap::OPTION_STATUS);
        ThemeNavBootstrap::on_activate();

        wp_safe_redirect(add_query_arg(['page' => 'scorva-general', 'bootstrap' => 'rerun'], admin_url('admin.php')));
        exit;
    }

    public static function handle_reset_theme_nav_notice(): void
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('Forbidden', 'scorva'));
        }

        check_admin_referer('scorva_reset_theme_nav_notice');
        delete_option(ThemeNavBootstrap::NOTICE_DISMISS_OPTION);

        wp_safe_redirect(admin_url('admin.php?page=scorva-general'));
        exit;
    }
}
