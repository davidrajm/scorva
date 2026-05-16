<?php
/**
 * Plugin Name: Project Reviews
 * Description: Project review workflows for SASTT.
 * Version: 0.1.0
 * Author: SASTT
 * Text Domain: project-reviews
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('PR_PLUGIN_VERSION', '0.1.0');
define('PR_PLUGIN_SLUG', 'project-reviews');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-plugin.php';

$plugin = ProjectReviews\Plugin::instance();
$plugin->init();

register_activation_hook(__FILE__, [ProjectReviews\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [ProjectReviews\Plugin::class, 'deactivate']);
