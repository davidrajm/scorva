<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

use ProjectReviews\Capabilities;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SmtpService;
use ProjectReviews\Services\ThemeNavBootstrap;

final class Admin_Settings
{
    public static function register(): void
    {
        $menu_label = PluginSettings::app_short_name();
        add_options_page(
            $menu_label,
            $menu_label,
            PR_CAP_MANAGE_SETTINGS,
            'scorva-settings',
            [self::class, 'render_page']
        );

        register_setting(
            'project_reviews_settings',
            PluginSettings::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [PluginSettings::class, 'sanitize'],
                'default' => PluginSettings::get(),
            ]
        );

        register_setting(
            'project_reviews_settings',
            SmtpService::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [SmtpService::class, 'sanitize'],
                'default' => (new SmtpService())->get_settings(),
            ]
        );

        register_setting(
            'project_reviews_settings',
            PluginSettings::DELETE_DATA_ON_UNINSTALL_KEY,
            [
                'type' => 'boolean',
                'sanitize_callback' => [PluginSettings::class, 'sanitize_delete_data_on_uninstall'],
                'default' => false,
            ]
        );

        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(string $hook): void
    {
        if ($hook !== 'settings_page_scorva-settings') {
            return;
        }

        wp_enqueue_media();
        wp_register_script(
            'pr-admin-settings',
            false,
            ['jquery'],
            false,
            true
        );
        wp_enqueue_script('pr-admin-settings');
        wp_add_inline_script('pr-admin-settings', self::inline_script());
        wp_localize_script('pr-admin-settings', 'prAdminSettings', [
            'backupUrl' => rest_url(Rest_Bootstrap::NAMESPACE . '/backup/download'),
            'smtpTestUrl' => rest_url(Rest_Bootstrap::NAMESPACE . '/settings/smtp/test'),
            'restNonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function render_page(): void
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'scorva'));
        }

        $settings = PluginSettings::get();
        $option_key = PluginSettings::OPTION_KEY;
        $delete_on_uninstall = PluginSettings::delete_data_on_uninstall();
        $delete_option_key = PluginSettings::DELETE_DATA_ON_UNINSTALL_KEY;
        $app_name = PluginSettings::app_display_name();
        $app_short = PluginSettings::app_short_name();
        ?>
        <div class="wrap">
            <h1><?php
            echo esc_html(
                sprintf(
                    /* translators: %s: application display name */
                    __('%s Settings', 'scorva'),
                    $app_name
                )
            );
            ?></h1>
            <form method="post" action="options.php" id="pr-settings-form">
                <?php settings_fields('project_reviews_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="pr-app-display-name"><?php echo esc_html__('Application display name', 'scorva'); ?></label>
                        </th>
                        <td>
                            <input name="<?php echo esc_attr($option_key); ?>[app_display_name]"
                                id="pr-app-display-name" type="text" class="regular-text"
                                value="<?php echo esc_attr(PluginSettings::app_display_name()); ?>" />
                            <p class="description">
                                <?php echo esc_html__(
                                    'Shown in the app header, landing page, emails, and permission messages.',
                                    'scorva'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pr-from-name"><?php echo esc_html__('From name', 'scorva'); ?></label>
                        </th>
                        <td>
                            <input name="<?php echo esc_attr($option_key); ?>[from_name]"
                                id="pr-from-name" type="text" class="regular-text"
                                value="<?php echo esc_attr((string) $settings['from_name']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pr-reply-to"><?php echo esc_html__('Reply-to email', 'scorva'); ?></label>
                        </th>
                        <td>
                            <input name="<?php echo esc_attr($option_key); ?>[reply_to]"
                                id="pr-reply-to" type="email" class="regular-text"
                                value="<?php echo esc_attr((string) $settings['reply_to']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pr-login-url"><?php echo esc_html__('Base login URL', 'scorva'); ?></label>
                        </th>
                        <td>
                            <input name="<?php echo esc_attr($option_key); ?>[login_url]"
                                id="pr-login-url" type="url" class="regular-text"
                                value="<?php echo esc_attr((string) $settings['login_url']); ?>" />
                            <p class="description">
                                <?php echo esc_html__('Used in reviewer invite emails.', 'scorva'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Site menu', 'scorva'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr($option_key); ?>[theme_nav_auto_bootstrap_enabled]"
                                    value="1" <?php checked(PluginSettings::theme_nav_auto_bootstrap_enabled()); ?> />
                                <?php echo esc_html__(
                                    'Add Reviews link to site menu on activation',
                                    'scorva'
                                ); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr($option_key); ?>[theme_nav_bridge_enabled]"
                                    value="1" <?php checked(PluginSettings::theme_nav_bridge_enabled()); ?> />
                                <?php echo esc_html__(
                                    'Expose Reviews link via theme filter bridge (custom PHP nav themes)',
                                    'scorva'
                                ); ?>
                            </label>
                            <p>
                                <label for="pr-theme-nav-menu-label">
                                    <?php echo esc_html__('Menu label', 'scorva'); ?>
                                </label>
                                <input name="<?php echo esc_attr($option_key); ?>[theme_nav_menu_label]"
                                    id="pr-theme-nav-menu-label" type="text" class="regular-text"
                                    value="<?php echo esc_attr(PluginSettings::theme_nav_menu_label()); ?>" />
                            </p>
                            <p>
                                <label for="pr-reviews-entry-url">
                                    <?php echo esc_html__('Reviews entry URL', 'scorva'); ?>
                                </label>
                                <input id="pr-reviews-entry-url" type="url" class="large-text" readonly
                                    value="<?php echo esc_attr(ThemeNavBootstrap::reviews_url()); ?>" />
                                <button type="button" class="button" id="pr-copy-reviews-url">
                                    <?php echo esc_html__('Copy URL', 'scorva'); ?>
                                </button>
                            </p>
                            <p class="description">
                                <?php
                                $status = ThemeNavBootstrap::bootstrap_status();
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: bootstrap status */
                                        __('Bootstrap status: %s', 'scorva'),
                                        $status
                                    )
                                );
                                ?>
                                —
                                <?php echo esc_html__(
                                    'If automatic setup failed, add a custom link under Appearance → Menus.',
                                    'scorva'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Faculty directory', 'scorva'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr($option_key); ?>[faculty_bridge_enabled]"
                                    value="1" <?php checked(!empty($settings['faculty_bridge_enabled'])); ?> />
                                <?php echo esc_html__(
                                    'Enable faculty directory bridge (read from wp_faculty when available)',
                                    'scorva'
                                ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Notifications', 'scorva'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr($option_key); ?>[notify_rubric_open]"
                                    value="1" <?php checked(!empty($settings['notify_rubric_open'])); ?> />
                                <?php echo esc_html__('Email when a rubric is confirmed (marking opens)', 'scorva'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr($option_key); ?>[notify_session_closed]"
                                    value="1" <?php checked(!empty($settings['notify_session_closed'])); ?> />
                                <?php echo esc_html__('Email when a project is closed', 'scorva'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="description">
                    <?php echo esc_html__(
                        'Panel report PDF letterhead and layout are configured per project under Settings → Panel Report in the coordinator workspace.',
                        'scorva'
                    ); ?>
                </p>

                <h2><?php echo esc_html__('Email delivery (SMTP)', 'scorva'); ?></h2>
                <p><?php echo esc_html__(
                    'Send reviewer invitations and notifications through a dedicated SMTP server. Leave the host empty to use the default WordPress mailer.',
                    'scorva'
                ); ?></p>
                <?php self::render_smtp_settings(); ?>

                <h2><?php echo esc_html__('Backup', 'scorva'); ?></h2>
                <p><?php echo esc_html__(
                    'Download a ZIP archive with plugin database tables, options, and Excel reports for every project. Use this before migration, disaster recovery, or before enabling destructive uninstall below.',
                    'scorva'
                ); ?></p>
                <p>
                    <button type="button" class="button button-secondary" id="pr-download-full-backup">
                        <?php echo esc_html__('Download full backup (ZIP)', 'scorva'); ?>
                    </button>
                    <span id="pr-backup-status" class="description" style="margin-left:8px;"></span>
                </p>

                <h2><?php echo esc_html__('Lifecycle', 'scorva'); ?></h2>
                <p><?php echo esc_html__(
                    'Deactivating the plugin keeps all review data, settings, and user accounts unchanged. Only front-end routes are flushed.',
                    'scorva'
                ); ?></p>
                <p><?php echo esc_html__(
                    'Deleting the plugin from WordPress removes database tables, options, and plugin capabilities only when the checkbox below was enabled and saved before you delete the plugin.',
                    'scorva'
                ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Uninstall data', 'scorva'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr($delete_option_key); ?>"
                                    value="1" <?php checked($delete_on_uninstall); ?> />
                                <?php echo esc_html(
                                    sprintf(
                                        /* translators: %s: short application name */
                                        __('Remove all %s data when uninstalling the plugin', 'scorva'),
                                        $app_short
                                    )
                                ); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__(
                                    'When unchecked (default), uninstall leaves pr_* tables and options in place for reinstall or manual recovery. When checked, uninstall drops all plugin tables and options. WordPress user accounts are never deleted. Back up your site before enabling this option.',
                                    'scorva'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Capability defaults', 'scorva'); ?></h2>
                <p><?php echo esc_html__(
                    'On activation, administrators receive all capabilities. Coordinators may manage projects and override individual marks with a mandatory audit reason. Reviewers may enter marks only.',
                    'scorva'
                ); ?></p>
                <ul class="ul-disc">
                    <?php foreach (Capabilities::all() as $cap) : ?>
                        <li><code><?php echo esc_html($cap); ?></code></li>
                    <?php endforeach; ?>
                </ul>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function render_smtp_settings(): void
    {
        $smtp = (new SmtpService())->get_public_settings();
        $smtp_key = SmtpService::OPTION_KEY;
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pr-smtp-host"><?php echo esc_html__('SMTP host', 'scorva'); ?></label>
                </th>
                <td>
                    <input name="<?php echo esc_attr($smtp_key); ?>[host]"
                        id="pr-smtp-host" type="text" class="regular-text"
                        value="<?php echo esc_attr((string) $smtp['host']); ?>"
                        placeholder="smtp.example.com" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-smtp-port"><?php echo esc_html__('Port', 'scorva'); ?></label>
                </th>
                <td>
                    <input name="<?php echo esc_attr($smtp_key); ?>[port]"
                        id="pr-smtp-port" type="number" min="1" max="65535" class="small-text"
                        value="<?php echo esc_attr((string) $smtp['port']); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-smtp-encryption"><?php echo esc_html__('Encryption', 'scorva'); ?></label>
                </th>
                <td>
                    <select name="<?php echo esc_attr($smtp_key); ?>[encryption]" id="pr-smtp-encryption">
                        <option value="tls" <?php selected((string) $smtp['encryption'], 'tls'); ?>>TLS</option>
                        <option value="ssl" <?php selected((string) $smtp['encryption'], 'ssl'); ?>>SSL</option>
                        <option value="none" <?php selected((string) $smtp['encryption'], 'none'); ?>>
                            <?php echo esc_html__('None', 'scorva'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-smtp-username"><?php echo esc_html__('Username', 'scorva'); ?></label>
                </th>
                <td>
                    <input name="<?php echo esc_attr($smtp_key); ?>[username]"
                        id="pr-smtp-username" type="text" class="regular-text" autocomplete="off"
                        value="<?php echo esc_attr((string) $smtp['username']); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-smtp-password"><?php echo esc_html__('Password', 'scorva'); ?></label>
                </th>
                <td>
                    <input name="<?php echo esc_attr($smtp_key); ?>[password]"
                        id="pr-smtp-password" type="password" class="regular-text" autocomplete="new-password"
                        value=""
                        placeholder="<?php echo esc_attr(
                            !empty($smtp['has_password'])
                                ? __('Saved — leave blank to keep', 'scorva')
                                : ''
                        ); ?>" />
                    <p class="description">
                        <?php echo esc_html__('Stored encrypted. Leave blank to keep the current password.', 'scorva'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-smtp-from-email"><?php echo esc_html__('From email', 'scorva'); ?></label>
                </th>
                <td>
                    <input name="<?php echo esc_attr($smtp_key); ?>[from_email]"
                        id="pr-smtp-from-email" type="email" class="regular-text"
                        value="<?php echo esc_attr((string) $smtp['from_email']); ?>" />
                    <p class="description">
                        <?php echo esc_html__(
                            'Sender address for plugin emails. Should match the SMTP account to avoid spam filtering. The From name is taken from the branding settings above.',
                            'scorva'
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Test', 'scorva'); ?></th>
                <td>
                    <button type="button" class="button button-secondary" id="pr-smtp-send-test">
                        <?php echo esc_html__('Send test email', 'scorva'); ?>
                    </button>
                    <span id="pr-smtp-test-status" class="description" style="margin-left:8px;"></span>
                    <p class="description">
                        <?php echo esc_html__(
                            'Sends a test message to your account email using the saved settings. Save changes first.',
                            'scorva'
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * @param array<string, mixed> $pdf
     */
    private static function render_panel_pdf_settings(string $option_key, array $pdf): void
    {
        $letterhead = is_array($pdf['letterhead'] ?? null) ? $pdf['letterhead'] : [];
        $blocks = is_array($letterhead['blocks'] ?? null) ? $letterhead['blocks'] : [];
        $table = is_array($pdf['table'] ?? null) ? $pdf['table'] : [];
        $signatures = is_array($pdf['signatures'] ?? null) ? $pdf['signatures'] : [];
        $hod = is_array($signatures['hod'] ?? null) ? $signatures['hod'] : [];

        $logo_attachment = 0;
        $logo_width = 4.0;
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'image') {
                $logo_attachment = (int) ($block['attachment_id'] ?? 0);
                $logo_width = (float) ($block['width_in'] ?? 4.0);
                break;
            }
        }

        $text_blocks = array_values(array_filter(
            $blocks,
            static fn ($block): bool => is_array($block) && ($block['type'] ?? 'text') === 'text'
        ));
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Logo', 'scorva'); ?></th>
                <td>
                    <input type="hidden"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][letterhead][blocks][0][type]"
                        value="image" />
                    <input type="hidden" id="pr-pdf-logo-id"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][letterhead][blocks][0][attachment_id]"
                        value="<?php echo esc_attr((string) $logo_attachment); ?>" />
                    <button type="button" class="button" id="pr-pdf-logo-select">
                        <?php echo esc_html__('Select logo', 'scorva'); ?>
                    </button>
                    <button type="button" class="button" id="pr-pdf-logo-clear">
                        <?php echo esc_html__('Remove', 'scorva'); ?>
                    </button>
                    <div id="pr-pdf-logo-preview" style="margin-top:8px;">
                        <?php if ($logo_attachment > 0) : ?>
                            <?php echo wp_get_attachment_image($logo_attachment, 'medium'); ?>
                        <?php endif; ?>
                    </div>
                    <p>
                        <label for="pr-pdf-logo-width">
                            <?php echo esc_html__('Logo width (inches)', 'scorva'); ?>
                        </label>
                        <input type="number" step="0.1" min="0.5" max="8" class="small-text"
                            id="pr-pdf-logo-width"
                            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][letterhead][blocks][0][width_in]"
                            value="<?php echo esc_attr((string) $logo_width); ?>" />
                    </p>
                </td>
            </tr>
        </table>

        <h3><?php echo esc_html__('Letterhead text', 'scorva'); ?></h3>
        <p class="description">
            <?php echo esc_html__('First two lines are typically department and school names. Add more lines as needed.', 'scorva'); ?>
        </p>
        <div id="pr-letterhead-blocks">
            <?php
            if ($text_blocks === []) {
                $text_blocks = [
                    ['type' => 'text', 'value' => '', 'style' => 'title', 'label' => ''],
                    ['type' => 'text', 'value' => '', 'style' => 'subtitle', 'label' => ''],
                ];
            }
            foreach ($text_blocks as $index => $block) :
                self::render_letterhead_text_row($option_key, $index + 1, $block);
            endforeach;
            ?>
        </div>
        <p>
            <button type="button" class="button" id="pr-letterhead-add">
                <?php echo esc_html__('Add letterhead line', 'scorva'); ?>
            </button>
        </p>

        <h3><?php echo esc_html__('Scores table', 'scorva'); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Columns', 'scorva'); ?></th>
                <td>
                    <input type="hidden"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][table][show_sr_no]"
                        value="1" />
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][table][show_attendance]"
                            value="1" <?php checked(!isset($table['show_attendance']) || !empty($table['show_attendance'])); ?> />
                        <?php echo esc_html__('Attendance column', 'scorva'); ?>
                    </label>
                    <br />
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][table][show_project_title]"
                            value="1" <?php checked(!isset($table['show_project_title']) || !empty($table['show_project_title'])); ?> />
                        <?php echo esc_html__('Project title column', 'scorva'); ?>
                    </label>
                    <br />
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][table][show_guide_name]"
                            value="1" <?php checked(!empty($table['show_guide_name'])); ?> />
                        <?php echo esc_html__('Guide name column', 'scorva'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-pdf-attendance-header">
                        <?php echo esc_html__('Attendance header', 'scorva'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" id="pr-pdf-attendance-header"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][table][attendance_column_header]"
                        value="<?php echo esc_attr((string) ($table['attendance_column_header'] ?? 'Attendance')); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-pdf-project-field">
                        <?php echo esc_html__('Project title field key', 'scorva'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" id="pr-pdf-project-field"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][table][project_title_field_key]"
                        value="<?php echo esc_attr((string) ($table['project_title_field_key'] ?? 'project_title')); ?>" />
                    <p class="description">
                        <?php echo esc_html__(
                            'Registry custom field key when per-review project title is empty (default: project_title).',
                            'scorva'
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-pdf-guide-header">
                        <?php echo esc_html__('Guide column header', 'scorva'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" id="pr-pdf-guide-header"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][table][guide_column_header]"
                        value="<?php echo esc_attr((string) ($table['guide_column_header'] ?? 'Guide')); ?>" />
                </td>
            </tr>
        </table>

        <h3><?php echo esc_html__('Signatures', 'scorva'); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Panel coordinator', 'scorva'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][signatures][show_panel_coordinator_line]"
                            value="1" <?php checked(!isset($signatures['show_panel_coordinator_line']) || !empty($signatures['show_panel_coordinator_line'])); ?> />
                        <?php echo esc_html__(
                            'Show panel coordinator line when not in reviewer roster',
                            'scorva'
                        ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-pdf-hod-label">
                        <?php echo esc_html__('Head of department label', 'scorva'); ?>
                    </label>
                </th>
                <td>
                    <input type="hidden"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][signatures][hod][enabled]"
                        value="1" />
                    <input type="text" class="regular-text" id="pr-pdf-hod-label"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][signatures][hod][label]"
                        value="<?php echo esc_attr((string) ($hod['label'] ?? 'Head of the Department')); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pr-pdf-hod-name">
                        <?php echo esc_html__('Head of department name', 'scorva'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" class="regular-text" id="pr-pdf-hod-name"
                        name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][signatures][hod][name]"
                        value="<?php echo esc_attr((string) ($hod['name'] ?? '')); ?>" />
                </td>
            </tr>
        </table>

        <input type="hidden"
            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][report][title]"
            value="Review Report" />
        <input type="hidden"
            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][report][show_review_number]"
            value="1" />
        <input type="hidden"
            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][report][show_panel_name]"
            value="1" />
        <input type="hidden"
            name="<?php echo esc_attr($option_key); ?>[panel_report_pdf][report][show_reviewers_list]"
            value="1" />
        <?php
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function render_letterhead_text_row(string $option_key, int $index, array $block): void
    {
        $base = $option_key . '[panel_report_pdf][letterhead][blocks][' . $index . ']';
        $style = (string) ($block['style'] ?? 'body');
        ?>
        <div class="pr-letterhead-row" style="margin-bottom:12px;padding:12px;border:1px solid #ccd0d4;background:#fff;">
            <input type="hidden" name="<?php echo esc_attr($base); ?>[type]" value="text" />
            <p>
                <label><?php echo esc_html__('Text', 'scorva'); ?></label><br />
                <input type="text" class="large-text"
                    name="<?php echo esc_attr($base); ?>[value]"
                    value="<?php echo esc_attr((string) ($block['value'] ?? '')); ?>" />
            </p>
            <p>
                <label><?php echo esc_html__('Style', 'scorva'); ?></label>
                <select name="<?php echo esc_attr($base); ?>[style]">
                    <option value="title" <?php selected($style, 'title'); ?>><?php echo esc_html__('Title', 'scorva'); ?></option>
                    <option value="subtitle" <?php selected($style, 'subtitle'); ?>><?php echo esc_html__('Subtitle', 'scorva'); ?></option>
                    <option value="body" <?php selected($style, 'body'); ?>><?php echo esc_html__('Body', 'scorva'); ?></option>
                </select>
                <button type="button" class="button-link-delete pr-letterhead-remove" style="margin-left:12px;">
                    <?php echo esc_html__('Remove', 'scorva'); ?>
                </button>
            </p>
        </div>
        <?php
    }

    private static function inline_script(): string
    {
        $option_key = PluginSettings::OPTION_KEY;

        return <<<JS
jQuery(function ($) {
  var frame;
  $('#pr-pdf-logo-select').on('click', function (e) {
    e.preventDefault();
    if (frame) {
      frame.open();
      return;
    }
    frame = wp.media({
      title: 'Select logo',
      button: { text: 'Use logo' },
      multiple: false
    });
    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      $('#pr-pdf-logo-id').val(attachment.id);
      $('#pr-pdf-logo-preview').html(
        attachment.sizes && attachment.sizes.medium
          ? '<img src="' + attachment.sizes.medium.url + '" />'
          : '<img src="' + attachment.url + '" />'
      );
    });
    frame.open();
  });
  $('#pr-pdf-logo-clear').on('click', function (e) {
    e.preventDefault();
    $('#pr-pdf-logo-id').val('0');
    $('#pr-pdf-logo-preview').empty();
  });

  $('#pr-copy-reviews-url').on('click', function () {
    var input = document.getElementById('pr-reviews-entry-url');
    if (!input) {
      return;
    }
    input.select();
    input.setSelectionRange(0, 99999);
    try {
      document.execCommand('copy');
    } catch (err) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value);
      }
    }
  });

  var blockIndex = $('#pr-letterhead-blocks .pr-letterhead-row').length;
  $('#pr-letterhead-add').on('click', function () {
    blockIndex += 1;
    var base = '{$option_key}[panel_report_pdf][letterhead][blocks][' + blockIndex + ']';
    var html = '<div class="pr-letterhead-row" style="margin-bottom:12px;padding:12px;border:1px solid #ccd0d4;background:#fff;">' +
      '<input type="hidden" name="' + base + '[type]" value="text" />' +
      '<p><label>Text</label><br /><input type="text" class="large-text" name="' + base + '[value]" value="" /></p>' +
      '<p><label>Style</label> <select name="' + base + '[style]">' +
      '<option value="title">Title</option><option value="subtitle">Subtitle</option>' +
      '<option value="body" selected>Body</option></select> ' +
      '<button type="button" class="button-link-delete pr-letterhead-remove" style="margin-left:12px;">Remove</button></p>' +
      '</div>';
    $('#pr-letterhead-blocks').append(html);
  });
  $('#pr-letterhead-blocks').on('click', '.pr-letterhead-remove', function () {
    $(this).closest('.pr-letterhead-row').remove();
  });

  var smtpTestBtn = $('#pr-smtp-send-test');
  var smtpTestStatus = $('#pr-smtp-test-status');
  if (smtpTestBtn.length && window.prAdminSettings && window.prAdminSettings.smtpTestUrl) {
    smtpTestBtn.on('click', function () {
      smtpTestBtn.prop('disabled', true);
      smtpTestStatus.text('Sending test email…');
      fetch(window.prAdminSettings.smtpTestUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': window.prAdminSettings.restNonce }
      })
        .then(function (response) {
          return response.json().then(function (payload) {
            if (!response.ok) {
              throw new Error((payload && payload.message) || 'Test email failed.');
            }
            return payload;
          });
        })
        .then(function (payload) {
          smtpTestStatus.text('Test email sent to ' + (payload.to || 'your address') + '.');
        })
        .catch(function (err) {
          smtpTestStatus.text(err.message || 'Test email failed.');
        })
        .finally(function () {
          smtpTestBtn.prop('disabled', false);
        });
    });
  }

  var backupBtn = $('#pr-download-full-backup');
  var backupStatus = $('#pr-backup-status');
  if (backupBtn.length && window.prAdminSettings && window.prAdminSettings.backupUrl) {
    backupBtn.on('click', function () {
      backupBtn.prop('disabled', true);
      backupStatus.text('Preparing backup…');
      fetch(window.prAdminSettings.backupUrl, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': window.prAdminSettings.restNonce }
      })
        .then(function (response) {
          if (!response.ok) {
            return response.json().then(function (payload) {
              throw new Error((payload && payload.message) || 'Backup failed.');
            });
          }
          var disposition = response.headers.get('Content-Disposition') || '';
          var match = disposition.match(/filename="([^"]+)"/);
          var filename = match ? match[1] : 'scorva-backup.zip';
          return response.blob().then(function (blob) {
            return { blob: blob, filename: filename };
          });
        })
        .then(function (result) {
          var url = URL.createObjectURL(result.blob);
          var link = document.createElement('a');
          link.href = url;
          link.download = result.filename;
          document.body.appendChild(link);
          link.click();
          link.remove();
          URL.revokeObjectURL(url);
          backupStatus.text('Download started.');
        })
        .catch(function (err) {
          backupStatus.text(err.message || 'Backup failed.');
        })
        .finally(function () {
          backupBtn.prop('disabled', false);
        });
    });
  }
});
JS;
    }
}
