<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class SessionPanelReportSettings
{
    private const OPTION_PREFIX = 'pr_session_panel_report_';

    public static function option_key(int $session_id): string
    {
        return self::OPTION_PREFIX . max(0, $session_id);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(int $session_id): array
    {
        $defaults = PluginSettings::default_panel_report_pdf();
        if ($session_id <= 0) {
            return $defaults;
        }

        if (!function_exists('get_option')) {
            return $defaults;
        }

        $stored = get_option(self::option_key($session_id), null);
        if (is_array($stored) && $stored !== []) {
            return PluginSettings::merge_panel_report_pdf($defaults, $stored);
        }

        $legacy = PluginSettings::panel_report_pdf();
        if (self::has_meaningful_content($legacy)) {
            return PluginSettings::merge_panel_report_pdf($defaults, $legacy);
        }

        return $defaults;
    }

    public static function is_settings_frozen(int $session_id): bool
    {
        $settings = self::get($session_id);

        return !empty($settings['settings_frozen']);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function freeze_settings(int $session_id): array|\WP_Error
    {
        if ($session_id <= 0) {
            return new \WP_Error(
                'invalid_session',
                __('Invalid project.', 'project-reviews'),
                ['status' => 400]
            );
        }

        if (self::is_settings_frozen($session_id)) {
            return new \WP_Error(
                'panel_report_settings_frozen',
                __('Panel report settings are already frozen.', 'project-reviews'),
                ['status' => 409]
            );
        }

        $settings = self::get($session_id);
        $settings['settings_frozen'] = true;
        $settings['settings_frozen_at'] = function_exists('wp_date')
            ? wp_date('c')
            : gmdate('c');

        if (function_exists('update_option')) {
            update_option(self::option_key($session_id), $settings, false);
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function unfreeze_settings(int $session_id): array|\WP_Error
    {
        if ($session_id <= 0) {
            return new \WP_Error(
                'invalid_session',
                __('Invalid project.', 'project-reviews'),
                ['status' => 400]
            );
        }

        if (!self::is_settings_frozen($session_id)) {
            return new \WP_Error(
                'panel_report_settings_not_frozen',
                __('Panel report settings are not frozen.', 'project-reviews'),
                ['status' => 409]
            );
        }

        $settings = self::get($session_id);
        $settings['settings_frozen'] = false;
        $settings['settings_frozen_at'] = '';

        if (function_exists('update_option')) {
            update_option(self::option_key($session_id), $settings, false);
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|\WP_Error
     */
    public static function save(int $session_id, array $input): array|\WP_Error
    {
        if ($session_id <= 0) {
            return PluginSettings::default_panel_report_pdf();
        }

        if (self::is_settings_frozen($session_id)) {
            return new \WP_Error(
                'panel_report_settings_frozen',
                __('Panel report settings are frozen. Unfreeze settings before making changes.', 'project-reviews'),
                ['status' => 403]
            );
        }

        $sanitized = PluginSettings::sanitize_panel_report_pdf($input);
        $sanitized['settings_frozen'] = false;
        $sanitized['settings_frozen_at'] = '';

        if (function_exists('update_option')) {
            update_option(self::option_key($session_id), $sanitized, false);
        }

        return $sanitized;
    }

    public static function delete(int $session_id): void
    {
        if ($session_id <= 0 || !function_exists('delete_option')) {
            return;
        }

        delete_option(self::option_key($session_id));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function has_meaningful_content(array $settings): bool
    {
        $letterhead = is_array($settings['letterhead'] ?? null) ? $settings['letterhead'] : [];
        $blocks = is_array($letterhead['blocks'] ?? null) ? $letterhead['blocks'] : [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'image' && (int) ($block['attachment_id'] ?? 0) > 0) {
                return true;
            }
            if (trim((string) ($block['value'] ?? '')) !== '') {
                return true;
            }
        }

        $report = is_array($settings['report'] ?? null) ? $settings['report'] : [];
        if (trim((string) ($report['program_name'] ?? '')) !== ''
            || trim((string) ($report['semester'] ?? '')) !== '') {
            return true;
        }

        $hod = is_array($settings['signatures']['hod'] ?? null)
            ? $settings['signatures']['hod']
            : [];
        if (trim((string) ($hod['name'] ?? '')) !== '') {
            return true;
        }

        return false;
    }
}
