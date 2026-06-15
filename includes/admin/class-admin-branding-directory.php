<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

use ProjectReviews\Services\PluginSettings;

final class Admin_Branding_Directory
{
    private const NONCE_ACTION = 'scorva_branding_save';
    private const NONCE_FIELD  = '_scorva_branding_nonce';

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
                $logo_id = max(0, (int) ($_POST['global_logo_id'] ?? 0));

                $current                   = get_option(PluginSettings::OPTION_KEY, []);
                $current                   = is_array($current) ? $current : [];
                $current['global_logo_id'] = $logo_id;

                update_option(PluginSettings::OPTION_KEY, $current);
                $saved = true;
            }
        }

        $settings = PluginSettings::get();
        $logo_id  = max(0, (int) ($settings['global_logo_id'] ?? 0));
        $logo_url = PluginSettings::global_logo_url();
        ?>
        <div class="scorva-admin-page">
            <div class="scorva-admin-page__accent-bar"></div>

            <form method="post" id="scorva-branding-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="global_logo_id" id="scorva-logo-id"
                       value="<?php echo esc_attr((string) $logo_id); ?>">

                <?php self::render_page_header($saved, $save_err); ?>

                <?php self::render_logo_card($logo_id, $logo_url); ?>

                <?php self::render_save_bar($saved, $save_err); ?>
            </form>
        </div>

        <div id="scorva-toast" class="scorva-toast" aria-live="polite"></div>

        <script>
        (function () {
            /* ── media library ─────────────────────────────────────── */
            var mediaFrame;

            function openMediaLibrary() {
                if (mediaFrame) { mediaFrame.open(); return; }
                mediaFrame = wp.media({
                    title: 'Select Institution Logo',
                    button: {text: 'Use this logo'},
                    multiple: false,
                    library: {type: 'image'},
                });
                mediaFrame.on('select', function () {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    document.getElementById('scorva-logo-id').value = attachment.id;
                    var preview = document.getElementById('scorva-logo-preview');
                    var placeholder = document.getElementById('scorva-logo-placeholder');
                    var imgEl = document.getElementById('scorva-logo-img');
                    if (preview && placeholder && imgEl) {
                        imgEl.src = attachment.url;
                        preview.style.display = '';
                        placeholder.style.display = 'none';
                    }
                });
                mediaFrame.open();
            }

            document.addEventListener('click', function (e) {
                if (e.target.closest('#scorva-logo-placeholder, #scorva-logo-change-btn')) {
                    openMediaLibrary();
                }
                if (e.target.closest('#scorva-logo-remove-btn')) {
                    document.getElementById('scorva-logo-id').value = '0';
                    var preview = document.getElementById('scorva-logo-preview');
                    var placeholder = document.getElementById('scorva-logo-placeholder');
                    if (preview) preview.style.display = 'none';
                    if (placeholder) placeholder.style.display = '';
                }
            });

            /* ── save bar feedback ─────────────────────────────────── */
            var saveStatus = document.getElementById('scorva-save-status');
            <?php if ($saved): ?>
            if (saveStatus) {
                saveStatus.textContent = 'Settings saved.';
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

    private static function render_page_header(bool $saved, string $save_err): void
    {
        ?>
        <div class="scorva-page-header">
            <div class="scorva-page-header__title-group">
                <span class="scorva-page-header__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <path d="M3 9h18M9 21V9"/>
                    </svg>
                </span>
                <div>
                    <h1 class="scorva-page-header__title">
                        <?php esc_html_e('Branding', 'scorva'); ?>
                    </h1>
                    <p class="scorva-page-header__subtitle">
                        <?php esc_html_e('Set a global institution logo for panel report PDFs and scoring sheets.', 'scorva'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_logo_card(int $logo_id, string $logo_url): void
    {
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <?php esc_html_e('Institution Logo', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <!-- Empty state -->
                <div id="scorva-logo-placeholder"
                     class="scorva-logo-upload"
                     role="button"
                     tabindex="0"
                     aria-label="<?php esc_attr_e('Select institution logo', 'scorva'); ?>"
                     style="<?php echo $logo_id > 0 ? 'display:none' : ''; ?>">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <p class="scorva-logo-upload__label">
                        <?php esc_html_e('Select institution logo', 'scorva'); ?>
                    </p>
                    <p class="scorva-logo-upload__hint">
                        <?php esc_html_e('PNG, JPG, SVG · Recommended: 400 × 120 px', 'scorva'); ?>
                    </p>
                </div>

                <!-- Filled state -->
                <div id="scorva-logo-preview"
                     class="scorva-logo-preview"
                     style="<?php echo $logo_id > 0 ? '' : 'display:none'; ?>">
                    <img id="scorva-logo-img"
                         src="<?php echo esc_url($logo_url); ?>"
                         alt="<?php esc_attr_e('Institution logo', 'scorva'); ?>"
                         class="scorva-logo-preview__img">
                    <div class="scorva-logo-preview__actions">
                        <button type="button" id="scorva-logo-change-btn" class="scorva-btn scorva-btn--secondary">
                            <?php esc_html_e('&#9998; Change logo', 'scorva'); ?>
                        </button>
                        <button type="button" id="scorva-logo-remove-btn" class="scorva-btn scorva-btn--ghost scorva-btn--danger">
                            <?php esc_html_e('&#x2715; Remove', 'scorva'); ?>
                        </button>
                    </div>
                </div>

                <p class="scorva-logo-hint">
                    <?php esc_html_e('Auto-applied to scoring sheets &amp; panel report PDFs for new sessions. Per-session overrides remain available.', 'scorva'); ?>
                </p>
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
                <?php esc_html_e('Save Settings', 'scorva'); ?>
            </button>
        </div>
        <?php
    }
}
