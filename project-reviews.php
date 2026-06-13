<?php
/**
 * Plugin Name: Scorva
 * Plugin URI:  https://github.com/davidrajm/scorva
 * Description: Project review workflow management for WordPress — sessions, panels, rubrics, reviewer marking, reports, and data export.
 * Version:     0.1.0
 * Author:      David
 * Author URI:  https://github.com/davidrajm
 * Text Domain: scorva
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

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
