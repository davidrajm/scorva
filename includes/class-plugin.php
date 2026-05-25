<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\ThemeNavBootstrap;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init(): void
    {
        require_once PR_PLUGIN_DIR . 'includes/Install.php';
        Install::ensure_schema_patches();

        require_once PR_PLUGIN_DIR . 'includes/capabilities.php';
        require_once PR_PLUGIN_DIR . 'includes/routes.php';
        require_once PR_PLUGIN_DIR . 'includes/workspace-access.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-auth.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-bootstrap.php';
        require_once PR_PLUGIN_DIR . 'includes/admin/class-admin-settings.php';
        require_once PR_PLUGIN_DIR . 'includes/theme-nav.php';
        require_once PR_PLUGIN_DIR . 'includes/services/ThemeNavBootstrap.php';
        \ProjectReviews\Services\ThemeNavBootstrap::register_hooks();

        Routes::register_rewrites();
        Routes::register_hooks();
        WorkspaceAccess::register_hooks();
        self::maybe_flush_rewrites();
        add_action('rest_api_init', [Rest_Bootstrap::class, 'register_routes']);
        add_action('admin_menu', [\ProjectReviews\Admin\Admin_Settings::class, 'register']);
    }

    public static function activate(): void
    {
        require_once PR_PLUGIN_DIR . 'includes/Install.php';
        require_once PR_PLUGIN_DIR . 'includes/capabilities.php';
        require_once PR_PLUGIN_DIR . 'includes/routes.php';
        require_once PR_PLUGIN_DIR . 'includes/services/PluginSettings.php';
        require_once PR_PLUGIN_DIR . 'includes/services/ThemeNavBootstrap.php';
        Install::maybe_upgrade();
        Capabilities::apply_defaults();
        Routes::register_rewrites();
        ThemeNavBootstrap::on_activate();
        flush_rewrite_rules();
        if (function_exists('update_option') && defined('PR_PLUGIN_VERSION')) {
            update_option('pr_rewrite_version', PR_PLUGIN_VERSION);
        }
    }

    private static function maybe_flush_rewrites(): void
    {
        if (!function_exists('get_option') || !defined('PR_PLUGIN_VERSION')) {
            return;
        }

        $flushed_version = (string) get_option('pr_rewrite_version', '0');
        if (version_compare($flushed_version, PR_PLUGIN_VERSION, '>=')) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('pr_rewrite_version', PR_PLUGIN_VERSION);
    }

    /**
     * Deactivation is intentionally non-destructive: custom tables, options, capabilities,
     * roles, and WordPress user accounts are preserved. Re-activation restores routes
     * and schema via {@see activate()} / {@see Install::maybe_upgrade()}.
     */
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
