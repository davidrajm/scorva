<?php
/**
 * Plugin Name: Scorva
 * Description: The Review Management System — project review workflows for SASTT.
 * Version: 0.1.0
 * Author: SASTT
 * Text Domain: scorva
 */
// TODO (manual step): rename the plugin folder from `project-reviews/` to `scorva/` after committing
// this change — the folder rename must be done outside Claude Code to avoid breaking the session.

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PR_PLUGIN_VERSION')) {
    define('PR_PLUGIN_VERSION', '0.1.0');
}
if (!defined('PR_PLUGIN_SLUG')) {
    define('PR_PLUGIN_SLUG', 'scorva');
}
if (!defined('PR_PLUGIN_FILE')) {
    define('PR_PLUGIN_FILE', __FILE__);
}
if (!defined('PR_PLUGIN_DIR')) {
    define('PR_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-plugin.php';

add_action('init', static function (): void {
    ProjectReviews\Plugin::instance()->init();
});

register_activation_hook(__FILE__, [ProjectReviews\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [ProjectReviews\Plugin::class, 'deactivate']);
