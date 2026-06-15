<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

use ProjectReviews\Services\PluginSettings;

final class Admin_Menu
{
    public static function register(): void
    {
        require_once PR_PLUGIN_DIR . 'includes/admin/class-admin-role-editor.php';
        require_once PR_PLUGIN_DIR . 'includes/admin/class-admin-branding-directory.php';
        require_once PR_PLUGIN_DIR . 'includes/admin/class-admin-email-settings.php';
        require_once PR_PLUGIN_DIR . 'includes/admin/class-admin-backup-lifecycle.php';
        require_once PR_PLUGIN_DIR . 'includes/admin/class-admin-general-settings.php';

        // Admin-post handlers for General Settings actions
        add_action('admin_post_scorva_rerun_bootstrap', [Admin_General_Settings::class, 'handle_rerun_bootstrap']);
        add_action('admin_post_scorva_reset_theme_nav_notice', [Admin_General_Settings::class, 'handle_reset_theme_nav_notice']);

        $short = PluginSettings::app_short_name();

        add_menu_page(
            $short,
            $short,
            PR_CAP_MANAGE_SETTINGS,
            'scorva',
            [Admin_Role_Editor::class, 'render_page'],
            'dashicons-awards',
            58
        );

        // First submenu shares the parent slug to rename the auto-generated entry.
        add_submenu_page(
            'scorva',
            __('Role Editor', 'scorva'),
            __('Role Editor', 'scorva'),
            PR_CAP_MANAGE_SETTINGS,
            'scorva',
            [Admin_Role_Editor::class, 'render_page']
        );

        add_submenu_page(
            'scorva',
            __('Branding', 'scorva'),
            __('Branding', 'scorva'),
            PR_CAP_MANAGE_SETTINGS,
            'scorva-branding',
            [Admin_Branding_Directory::class, 'render_page']
        );

        add_submenu_page(
            'scorva',
            __('Email Settings', 'scorva'),
            __('Email Settings', 'scorva'),
            PR_CAP_MANAGE_SETTINGS,
            'scorva-email',
            [Admin_Email_Settings::class, 'render_page']
        );

        add_submenu_page(
            'scorva',
            __('Backup & Lifecycle', 'scorva'),
            __('Backup & Lifecycle', 'scorva'),
            PR_CAP_MANAGE_SETTINGS,
            'scorva-backup',
            [Admin_Backup_Lifecycle::class, 'render_page']
        );

        add_submenu_page(
            'scorva',
            __('General Settings', 'scorva'),
            __('General Settings', 'scorva'),
            PR_CAP_MANAGE_SETTINGS,
            'scorva-general',
            [Admin_General_Settings::class, 'render_page']
        );

        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(string $hook): void
    {
        if (!str_contains($hook, 'scorva')) {
            return;
        }

        wp_enqueue_style(
            'scorva-admin',
            plugins_url('assets/css/admin.css', PR_PLUGIN_FILE),
            [],
            PR_PLUGIN_VERSION
        );

        if (str_contains($hook, 'scorva-branding')) {
            wp_enqueue_media();
        }
    }

}
