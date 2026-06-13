<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class PluginSettings
{
    public const OPTION_KEY = 'pr_plugin_settings';

    public const DEFAULT_APP_DISPLAY_NAME = 'Scorva: The Review Management System';

    /** Legacy email From name before configurable branding (story 20-1). */
    public const LEGACY_FROM_NAME = 'Project Reviews';

    private const APP_DISPLAY_NAME_MAX_LENGTH = 120;

    private const THEME_NAV_MENU_LABEL_MAX_LENGTH = 80;

    public const DEFAULT_THEME_NAV_MENU_LABEL = 'Reviews';

    /** Top-level option read during uninstall (not nested in pr_plugin_settings). */
    public const DELETE_DATA_ON_UNINSTALL_KEY = 'pr_delete_data_on_uninstall';

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $defaults = self::defaults();
        if (!function_exists('get_option')) {
            return $defaults;
        }

        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $merged = array_merge($defaults, $stored);
        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @deprecated Use SessionPanelReportSettings::get() for per-project PDF template.
     */
    public static function panel_report_pdf(): array
    {
        $settings = self::get();
        $pdf = $settings['panel_report_pdf'] ?? [];

        return is_array($pdf) ? $pdf : self::default_panel_report_pdf();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize_panel_report_pdf(array $input): array
    {
        return self::sanitize_panel_report_pdf_internal($input);
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    public static function merge_panel_report_pdf(array $defaults, array $stored): array
    {
        return self::merge_panel_report_pdf_internal($defaults, $stored);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $app_display = trim((string) ($input['app_display_name'] ?? ''));
        $from = trim((string) ($input['from_name'] ?? ''));
        $reply = trim((string) ($input['reply_to'] ?? ''));
        $login = trim((string) ($input['login_url'] ?? ''));

        if (function_exists('sanitize_text_field')) {
            $app_display = sanitize_text_field($app_display);
            $from = sanitize_text_field($from);
        }
        if (function_exists('sanitize_email')) {
            $reply = sanitize_email($reply);
        }
        if (function_exists('esc_url_raw')) {
            $login = esc_url_raw($login);
        }

        if (strlen($app_display) > self::APP_DISPLAY_NAME_MAX_LENGTH) {
            $app_display = substr($app_display, 0, self::APP_DISPLAY_NAME_MAX_LENGTH);
        }
        if ($app_display === '') {
            $app_display = self::DEFAULT_APP_DISPLAY_NAME;
        }

        $theme_nav_label = trim((string) ($input['theme_nav_menu_label'] ?? ''));
        if (function_exists('sanitize_text_field')) {
            $theme_nav_label = sanitize_text_field($theme_nav_label);
        }
        if (strlen($theme_nav_label) > self::THEME_NAV_MENU_LABEL_MAX_LENGTH) {
            $theme_nav_label = substr($theme_nav_label, 0, self::THEME_NAV_MENU_LABEL_MAX_LENGTH);
        }
        if ($theme_nav_label === '') {
            $theme_nav_label = self::DEFAULT_THEME_NAV_MENU_LABEL;
        }

        return [
            'app_display_name' => $app_display,
            'from_name' => $from,
            'reply_to' => $reply,
            'login_url' => $login,
            'notify_rubric_open' => !empty($input['notify_rubric_open']),
            'notify_session_closed' => !empty($input['notify_session_closed']),
            'faculty_bridge_enabled' => !empty($input['faculty_bridge_enabled']),
            'theme_nav_auto_bootstrap_enabled' => !empty($input['theme_nav_auto_bootstrap_enabled']),
            'theme_nav_menu_label' => $theme_nav_label,
            'theme_nav_bridge_enabled' => !empty($input['theme_nav_bridge_enabled']),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function save(array $settings): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        update_option(self::OPTION_KEY, self::sanitize($settings));
    }

    public static function app_display_name(): string
    {
        $name = trim((string) (self::get()['app_display_name'] ?? ''));

        return $name !== '' ? $name : self::DEFAULT_APP_DISPLAY_NAME;
    }

    public static function app_short_name(): string
    {
        $full = self::app_display_name();
        $pos = strpos($full, ':');
        if ($pos !== false) {
            $short = trim(substr($full, 0, $pos));
            if ($short !== '') {
                return $short;
            }
        }

        return $full;
    }

    public static function from_name(): string
    {
        $name = trim((string) (self::get()['from_name'] ?? ''));
        if ($name === '' || $name === self::LEGACY_FROM_NAME) {
            return self::app_short_name();
        }

        return $name;
    }

    public static function reply_to(): string
    {
        return trim((string) (self::get()['reply_to'] ?? ''));
    }

    public static function login_url(): string
    {
        $url = trim((string) (self::get()['login_url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return function_exists('wp_login_url') ? wp_login_url() : '/wp-login.php';
    }

    public static function login_url_with_redirect(string $redirect_to): string
    {
        $login = self::login_url();
        if ($redirect_to === '' || !function_exists('add_query_arg')) {
            return $login;
        }

        return add_query_arg('redirect_to', $redirect_to, $login);
    }

    /**
     * @return list<string>
     */
    public static function mail_headers(): array
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $reply = self::reply_to();
        if ($reply !== '') {
            $headers[] = 'Reply-To: ' . $reply;
        }

        return $headers;
    }

    public static function notify_rubric_open(): bool
    {
        return !empty(self::get()['notify_rubric_open']);
    }

    public static function notify_session_closed(): bool
    {
        return !empty(self::get()['notify_session_closed']);
    }

    public static function faculty_bridge_enabled(): bool
    {
        return !empty(self::get()['faculty_bridge_enabled']);
    }

    public static function theme_nav_auto_bootstrap_enabled(): bool
    {
        $settings = self::get();
        if (!array_key_exists('theme_nav_auto_bootstrap_enabled', $settings)) {
            return true;
        }

        return !empty($settings['theme_nav_auto_bootstrap_enabled']);
    }

    public static function theme_nav_bridge_enabled(): bool
    {
        $settings = self::get();
        if (!array_key_exists('theme_nav_bridge_enabled', $settings)) {
            return true;
        }

        return !empty($settings['theme_nav_bridge_enabled']);
    }

    public static function theme_nav_menu_label(): string
    {
        $label = trim((string) (self::get()['theme_nav_menu_label'] ?? ''));
        if ($label === '') {
            $label = self::DEFAULT_THEME_NAV_MENU_LABEL;
        }

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('pr_theme_nav_menu_label', $label);
            if (is_string($filtered) && $filtered !== '') {
                return $filtered;
            }
        }

        return $label;
    }

    public static function delete_data_on_uninstall(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        return (bool) get_option(self::DELETE_DATA_ON_UNINSTALL_KEY, false);
    }

    /**
     * @param mixed $value
     */
    public static function sanitize_delete_data_on_uninstall($value): bool
    {
        return !empty($value);
    }

    public static function set_delete_data_on_uninstall(bool $enabled): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        update_option(self::DELETE_DATA_ON_UNINSTALL_KEY, $enabled);
    }

    /**
     * @return array<string, mixed>
     */
    public static function default_panel_report_pdf(): array
    {
        return [
            'settings_frozen' => false,
            'settings_frozen_at' => '',
            'styles' => [
                'table_border_pt' => 1,
                'table_border_color' => '#000000',
            ],
            'letterhead' => [
                'blocks' => [
                    [
                        'type' => 'image',
                        'attachment_id' => 0,
                        'width_in' => 4.0,
                        'align' => 'center',
                    ],
                    [
                        'type' => 'text',
                        'value' => '',
                        'style' => 'title',
                        'label' => '',
                    ],
                    [
                        'type' => 'text',
                        'value' => '',
                        'style' => 'subtitle',
                        'label' => '',
                    ],
                ],
            ],
            'report' => [
                'title' => 'Review Report',
                'program_name' => '',
                'semester' => '',
                'show_review_number' => true,
                'show_panel_name' => true,
                'show_reviewers_list' => true,
            ],
            'table' => [
                'show_sr_no' => true,
                'sr_no_column_header' => 'Sr. No.',
                'show_reg_no' => true,
                'reg_no_column_header' => 'Reg No',
                'show_student_name' => true,
                'student_column_header' => 'Student',
                'show_attendance' => true,
                'attendance_column_header' => 'At',
                'show_project_title' => true,
                'project_title_column_header' => 'Project title',
                'project_title_field_key' => 'project_title',
                'show_guide_name' => true,
                'guide_column_header' => 'Guide',
                'final_marks_column_header' => 'Final Marks',
                'reviewer_header_pattern' => 'R{n}',
                'show_reviewer_legend' => true,
            ],
            'footer' => [
                'show_generated_datetime' => true,
            ],
            'signatures' => [
                'section_heading' => 'Signatures with date',
                'show_panel_coordinator_line' => true,
                'panel_coordinator_label' => 'Panel coordinator',
                'reviewer_label_pattern' => 'Reviewer {n}',
                'hod' => [
                    'enabled' => true,
                    'label' => 'Head of the Department',
                    'name' => '',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function sanitize_panel_report_pdf_internal(array $input): array
    {
        $defaults = self::default_panel_report_pdf();

        $styles_in = is_array($input['styles'] ?? null) ? $input['styles'] : [];
        $border_pt = (float) ($styles_in['table_border_pt'] ?? $defaults['styles']['table_border_pt']);
        if ($border_pt <= 0) {
            $border_pt = 1.0;
        }
        $border_color = trim((string) ($styles_in['table_border_color'] ?? '#000000'));
        if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $border_color)) {
            $border_color = '#000000';
        }

        $letterhead_blocks = [];
        $letterhead_in = is_array($input['letterhead'] ?? null) ? $input['letterhead'] : [];
        $blocks_in = is_array($letterhead_in['blocks'] ?? null) ? $letterhead_in['blocks'] : [];
        foreach ($blocks_in as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? 'text');
            if ($type === 'image') {
                $width = (float) ($block['width_in'] ?? 4.0);
                $width = max(0.5, min(8.0, $width <= 0 ? 4.0 : $width));
                $letterhead_blocks[] = [
                    'type' => 'image',
                    'attachment_id' => max(0, (int) ($block['attachment_id'] ?? 0)),
                    'width_in' => $width,
                    'align' => 'center',
                ];
                continue;
            }

            $style = (string) ($block['style'] ?? 'body');
            if (!in_array($style, ['title', 'subtitle', 'body'], true)) {
                $style = 'body';
            }
            $letterhead_blocks[] = [
                'type' => 'text',
                'value' => self::sanitize_plain_text((string) ($block['value'] ?? '')),
                'style' => $style,
                'label' => self::sanitize_plain_text((string) ($block['label'] ?? '')),
            ];
        }
        $letterhead_blocks = self::normalize_letterhead_blocks($letterhead_blocks, $defaults);

        $report_in = is_array($input['report'] ?? null) ? $input['report'] : [];
        $footer_in = is_array($input['footer'] ?? null) ? $input['footer'] : [];
        $table_in = is_array($input['table'] ?? null) ? $input['table'] : [];
        $signatures_in = is_array($input['signatures'] ?? null) ? $input['signatures'] : [];
        $hod_in = is_array($signatures_in['hod'] ?? null) ? $signatures_in['hod'] : [];

        $title = self::sanitize_plain_text((string) ($report_in['title'] ?? 'Review Report'));
        if ($title === '') {
            $title = 'Review Report';
        }

        $field_key = self::sanitize_plain_text(
            (string) ($table_in['project_title_field_key'] ?? 'project_title')
        );
        if ($field_key === '') {
            $field_key = 'project_title';
        }

        $reviewer_pattern = self::sanitize_plain_text(
            (string) ($table_in['reviewer_header_pattern'] ?? 'R{n}')
        );
        if ($reviewer_pattern === '' || !str_contains($reviewer_pattern, '{n}')) {
            $reviewer_pattern = 'R{n}';
        }

        $final_marks_header = self::sanitize_plain_text(
            (string) ($table_in['final_marks_column_header'] ?? 'Final Marks')
        );
        if ($final_marks_header === '') {
            $final_marks_header = 'Final Marks';
        }

        $attendance_header = self::sanitize_plain_text(
            (string) ($table_in['attendance_column_header'] ?? 'At')
        );
        if ($attendance_header === '') {
            $attendance_header = 'At';
        }

        $sr_header = self::sanitize_plain_text(
            (string) ($table_in['sr_no_column_header'] ?? 'Sr. No.')
        ) ?: 'Sr. No.';
        $reg_header = self::sanitize_plain_text(
            (string) ($table_in['reg_no_column_header'] ?? 'Reg No')
        ) ?: 'Reg No';
        $student_header = self::sanitize_plain_text(
            (string) ($table_in['student_column_header'] ?? 'Student')
        ) ?: 'Student';
        $project_header = self::sanitize_plain_text(
            (string) ($table_in['project_title_column_header'] ?? 'Project title')
        ) ?: 'Project title';

        return [
            'settings_frozen' => false,
            'settings_frozen_at' => '',
            'styles' => [
                'table_border_pt' => $border_pt,
                'table_border_color' => $border_color,
            ],
            'letterhead' => [
                'blocks' => $letterhead_blocks,
            ],
            'report' => [
                'title' => $title,
                'program_name' => self::sanitize_plain_text((string) ($report_in['program_name'] ?? '')),
                'semester' => self::sanitize_plain_text((string) ($report_in['semester'] ?? '')),
                'show_review_number' => !empty($report_in['show_review_number']),
                'show_panel_name' => !empty($report_in['show_panel_name']),
                'show_reviewers_list' => !empty($report_in['show_reviewers_list']),
            ],
            'footer' => [
                'show_generated_datetime' => !isset($footer_in['show_generated_datetime'])
                    || !empty($footer_in['show_generated_datetime']),
            ],
            'table' => [
                'show_sr_no' => !isset($table_in['show_sr_no']) || !empty($table_in['show_sr_no']),
                'sr_no_column_header' => $sr_header,
                'show_reg_no' => !isset($table_in['show_reg_no']) || !empty($table_in['show_reg_no']),
                'reg_no_column_header' => $reg_header,
                'show_student_name' => !isset($table_in['show_student_name']) || !empty($table_in['show_student_name']),
                'student_column_header' => $student_header,
                'show_attendance' => !isset($table_in['show_attendance']) || !empty($table_in['show_attendance']),
                'attendance_column_header' => $attendance_header,
                'show_project_title' => !isset($table_in['show_project_title']) || !empty($table_in['show_project_title']),
                'project_title_column_header' => $project_header,
                'project_title_field_key' => $field_key,
                'show_guide_name' => !empty($table_in['show_guide_name']),
                'guide_column_header' => self::sanitize_plain_text(
                    (string) ($table_in['guide_column_header'] ?? 'Guide')
                ) ?: 'Guide',
                'final_marks_column_header' => $final_marks_header,
                'reviewer_header_pattern' => $reviewer_pattern,
                'show_reviewer_legend' => !isset($table_in['show_reviewer_legend'])
                    || !empty($table_in['show_reviewer_legend']),
            ],
            'signatures' => [
                'section_heading' => self::sanitize_plain_text(
                    (string) ($signatures_in['section_heading'] ?? 'Signatures with date')
                ) ?: 'Signatures with date',
                'show_panel_coordinator_line' => !isset($signatures_in['show_panel_coordinator_line'])
                    || !empty($signatures_in['show_panel_coordinator_line']),
                'panel_coordinator_label' => self::sanitize_plain_text(
                    (string) ($signatures_in['panel_coordinator_label'] ?? 'Panel coordinator')
                ) ?: 'Panel coordinator',
                'reviewer_label_pattern' => self::sanitize_plain_text(
                    (string) ($signatures_in['reviewer_label_pattern'] ?? 'Reviewer {n}')
                ) ?: 'Reviewer {n}',
                'hod' => [
                    'enabled' => !isset($hod_in['enabled']) || !empty($hod_in['enabled']),
                    'label' => self::sanitize_plain_text(
                        (string) ($hod_in['label'] ?? 'Head of the Department')
                    ) ?: 'Head of the Department',
                    'name' => self::sanitize_plain_text((string) ($hod_in['name'] ?? '')),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private static function merge_panel_report_pdf_internal(array $defaults, array $stored): array
    {
        $merged = array_replace_recursive($defaults, $stored);
        if (isset($stored['letterhead']['blocks']) && is_array($stored['letterhead']['blocks'])) {
            $merged['letterhead']['blocks'] = $stored['letterhead']['blocks'];
        }

        $merged['settings_frozen'] = !empty($stored['settings_frozen']);
        $merged['settings_frozen_at'] = self::sanitize_plain_text((string) ($stored['settings_frozen_at'] ?? ''));

        return $merged;
    }

    /**
     * Ensure logo image block is first; keep at least default text lines when empty.
     *
     * @param list<array<string, mixed>> $blocks
     * @param array<string, mixed> $defaults
     * @return list<array<string, mixed>>
     */
    private static function normalize_letterhead_blocks(array $blocks, array $defaults): array
    {
        if ($blocks === []) {
            return $defaults['letterhead']['blocks'];
        }

        $image = null;
        $text_blocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'image') {
                if ($image === null) {
                    $image = $block;
                }
                continue;
            }
            $text_blocks[] = $block;
        }

        if ($image === null) {
            foreach ($defaults['letterhead']['blocks'] as $default_block) {
                if (is_array($default_block) && ($default_block['type'] ?? '') === 'image') {
                    $image = $default_block;
                    break;
                }
            }
        }

        $normalized = [];
        if ($image !== null) {
            $normalized[] = $image;
        }
        foreach ($text_blocks as $text_block) {
            $normalized[] = $text_block;
        }

        return $normalized !== [] ? $normalized : $defaults['letterhead']['blocks'];
    }

    private static function sanitize_plain_text(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return function_exists('sanitize_text_field') ? sanitize_text_field($value) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'app_display_name' => '',
            'from_name' => 'Scorva',
            'reply_to' => '',
            'login_url' => '',
            'notify_rubric_open' => false,
            'notify_session_closed' => false,
            'faculty_bridge_enabled' => false,
            'theme_nav_auto_bootstrap_enabled' => true,
            'theme_nav_menu_label' => self::DEFAULT_THEME_NAV_MENU_LABEL,
            'theme_nav_bridge_enabled' => true,
        ];
    }
}
