<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file) . '/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void
    {
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback): void
    {
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(): void
    {
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
