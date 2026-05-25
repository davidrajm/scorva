<?php

declare(strict_types=1);

namespace ProjectReviews;

final class Uninstall
{
    public static function run(): void
    {
        if (!function_exists('get_option')) {
            return;
        }

        if (!get_option('pr_delete_data_on_uninstall', false)) {
            return;
        }

        global $wpdb;
        if (!isset($wpdb)) {
            return;
        }

        require_once __DIR__ . '/Install.php';
        require_once __DIR__ . '/capabilities.php';
        require_once __DIR__ . '/services/ThemeNavBootstrap.php';

        \ProjectReviews\Services\ThemeNavBootstrap::remove_menu_item_on_uninstall();

        Install::drop_all($wpdb);

        if (function_exists('delete_option')) {
            foreach (Install::get_uninstall_option_names() as $option) {
                delete_option($option);
            }
        }

        Capabilities::remove_from_all_roles();
        Capabilities::remove_custom_roles_if_empty();
    }
}
