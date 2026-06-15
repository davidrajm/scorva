<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class ThemeNavBootstrap
{
    public const OPTION_BOOTSTRAP = 'pr_theme_nav_bootstrap';

    public const OPTION_STATUS = 'pr_theme_nav_bootstrap_status';

    public const NOTICE_DISMISS_OPTION = 'pr_theme_nav_manual_notice_dismissed';

    private const BOOTSTRAP_VERSION = 1;

    public static function on_activate(): void
    {
        if (!PluginSettings::theme_nav_auto_bootstrap_enabled()) {
            self::set_status('disabled');

            return;
        }

        if (!function_exists('wp_create_nav_menu')) {
            self::set_status('no_menu_api');

            return;
        }

        $menu_id = self::resolve_menu_id();
        if ($menu_id <= 0) {
            self::set_status('manual');
            self::clear_bootstrap_option();

            return;
        }

        $item_id = self::ensure_menu_item($menu_id);
        if ($item_id <= 0) {
            self::set_status('manual');
            self::clear_bootstrap_option();

            return;
        }

        $locations = self::assign_menu_locations($menu_id);
        self::persist_bootstrap($menu_id, $item_id, $locations);
        self::set_status('ok');
    }

    public static function sync_menu_item(): void
    {
        if (!function_exists('wp_update_nav_menu_item')) {
            return;
        }

        $stored = get_option(self::OPTION_BOOTSTRAP, []);
        if (!is_array($stored)) {
            return;
        }

        $menu_id = (int) ($stored['menu_id'] ?? 0);
        $item_id = (int) ($stored['menu_item_id'] ?? 0);
        if ($menu_id <= 0 || $item_id <= 0) {
            return;
        }

        $label = PluginSettings::theme_nav_menu_label();
        $url = self::reviews_url();

        wp_update_nav_menu_item(
            $menu_id,
            $item_id,
            [
                'menu-item-title' => $label,
                'menu-item-url' => $url,
                'menu-item-status' => 'publish',
                'menu-item-type' => 'custom',
            ]
        );

        $stored['label_hash'] = self::label_hash($label);
        $stored['url'] = $url;
        update_option(self::OPTION_BOOTSTRAP, $stored);
    }

    public static function bootstrap_status(): string
    {
        $status = get_option(self::OPTION_STATUS, '');
        if (is_string($status) && $status !== '') {
            return $status;
        }

        return 'manual';
    }

    public static function reviews_url(): string
    {
        return function_exists('home_url') ? home_url('/reviews/') : '/reviews/';
    }

    public static function register_hooks(): void
    {
        add_action('admin_init', [self::class, 'maybe_sync_on_admin_init'], 20);
        add_action('admin_notices', [self::class, 'maybe_render_manual_notice']);
        add_action('admin_post_pr_dismiss_theme_nav_notice', [self::class, 'dismiss_manual_notice']);

        if (function_exists('add_action')) {
            add_action(
                'update_option_' . PluginSettings::OPTION_KEY,
                static function (): void {
                    self::sync_menu_item();
                },
                10,
                0
            );
        }
    }

    public static function maybe_sync_on_admin_init(): void
    {
        if (!function_exists('current_user_can') || !current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            return;
        }

        self::sync_menu_item();
    }

    public static function maybe_render_manual_notice(): void
    {
        if (!function_exists('current_user_can') || !current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            return;
        }

        if (self::bootstrap_status() !== 'manual') {
            return;
        }

        if (get_option(self::NOTICE_DISMISS_OPTION, false)) {
            return;
        }

        $reviews_url = esc_url(self::reviews_url());
        $label = esc_html(PluginSettings::theme_nav_menu_label());
        $dismiss_url = wp_nonce_url(
            admin_url('admin-post.php?action=pr_dismiss_theme_nav_notice'),
            'pr_dismiss_theme_nav_notice'
        );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php
                echo esc_html__(
                    'Project Reviews could not add a menu link automatically.',
                    'scorva'
                );
                ?>
                <?php
                printf(
                    /* translators: 1: menu label, 2: reviews URL */
                    esc_html__(
                        'Add a custom link labeled %1$s pointing to %2$s under Appearance → Menus.',
                        'scorva'
                    ),
                    '<strong>' . $label . '</strong>',
                    '<code>' . $reviews_url . '</code>'
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url($dismiss_url); ?>">
                    <?php echo esc_html__('Dismiss this notice', 'scorva'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public static function dismiss_manual_notice(): void
    {
        if (!function_exists('current_user_can') || !current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('Forbidden', 'scorva'));
        }

        check_admin_referer('pr_dismiss_theme_nav_notice');
        update_option(self::NOTICE_DISMISS_OPTION, true);

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect(admin_url('admin.php?page=scorva-general'));
        }
    }

    /**
     * Remove plugin-created nav menu item during opt-in uninstall wipe.
     */
    public static function remove_menu_item_on_uninstall(): void
    {
        $stored = get_option(self::OPTION_BOOTSTRAP, []);
        if (!is_array($stored)) {
            return;
        }

        $item_id = (int) ($stored['menu_item_id'] ?? 0);
        if ($item_id <= 0 || !function_exists('wp_delete_post')) {
            return;
        }

        wp_delete_post($item_id, true);
    }

    private static function resolve_menu_id(): int
    {
        $assigned = self::find_menu_from_assigned_locations();
        if ($assigned > 0) {
            return $assigned;
        }

        if (function_exists('wp_get_nav_menus')) {
            $menus = wp_get_nav_menus();
            if (is_array($menus) && $menus !== []) {
                $first = $menus[0];
                if (is_object($first) && isset($first->term_id)) {
                    return (int) $first->term_id;
                }
            }
        }

        if (!function_exists('wp_create_nav_menu')) {
            return 0;
        }

        $created = wp_create_nav_menu(__('Site navigation', 'scorva'));
        if (is_wp_error($created)) {
            return 0;
        }

        return (int) $created;
    }

    private static function find_menu_from_assigned_locations(): int
    {
        if (!function_exists('get_nav_menu_locations')) {
            return 0;
        }

        $locations = get_nav_menu_locations();
        if (!is_array($locations)) {
            return 0;
        }

        $priority = apply_filters(
            'pr_theme_nav_location_priority',
            ['primary', 'menu-1', 'header', 'main', 'top']
        );

        foreach ($priority as $location) {
            if (!is_string($location) || $location === '') {
                continue;
            }
            $menu_id = (int) ($locations[$location] ?? 0);
            if ($menu_id > 0) {
                return $menu_id;
            }
        }

        foreach ($locations as $menu_id) {
            $menu_id = (int) $menu_id;
            if ($menu_id > 0) {
                return $menu_id;
            }
        }

        return 0;
    }

    private static function ensure_menu_item(int $menu_id): int
    {
        $stored = get_option(self::OPTION_BOOTSTRAP, []);
        if (is_array($stored)) {
            $stored_item = (int) ($stored['menu_item_id'] ?? 0);
            if ($stored_item > 0 && (int) ($stored['menu_id'] ?? 0) === $menu_id) {
                $existing = self::find_item_by_url($menu_id);
                if ($existing === 0 || $existing === $stored_item) {
                    return self::upsert_menu_item($menu_id, $stored_item);
                }
            }
        }

        $by_url = self::find_item_by_url($menu_id);
        if ($by_url > 0) {
            return self::upsert_menu_item($menu_id, $by_url);
        }

        return self::upsert_menu_item($menu_id, 0);
    }

    private static function find_item_by_url(int $menu_id): int
    {
        if (!function_exists('wp_get_nav_menu_items')) {
            return 0;
        }

        $target = self::normalize_url(self::reviews_url());
        $items = wp_get_nav_menu_items($menu_id);
        if (!is_array($items)) {
            return 0;
        }

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }
            $type = (string) ($item->type ?? '');
            if ($type !== 'custom') {
                continue;
            }
            $url = self::normalize_url((string) ($item->url ?? ''));
            if ($url === $target) {
                return (int) ($item->ID ?? 0);
            }
        }

        return 0;
    }

    private static function upsert_menu_item(int $menu_id, int $item_id): int
    {
        if (!function_exists('wp_update_nav_menu_item')) {
            return 0;
        }

        $label = PluginSettings::theme_nav_menu_label();
        $url = self::reviews_url();

        $result = wp_update_nav_menu_item(
            $menu_id,
            $item_id,
            [
                'menu-item-title' => $label,
                'menu-item-url' => $url,
                'menu-item-status' => 'publish',
                'menu-item-type' => 'custom',
            ]
        );

        if (is_wp_error($result)) {
            return 0;
        }

        return (int) $result;
    }

    /**
     * @return list<string>
     */
    private static function assign_menu_locations(int $menu_id): array
    {
        if (!function_exists('get_registered_nav_menus') || !function_exists('get_theme_mod')) {
            return [];
        }

        $registered = get_registered_nav_menus();
        if (!is_array($registered) || $registered === []) {
            return [];
        }

        $locations = get_nav_menu_locations();
        if (!is_array($locations)) {
            $locations = [];
        }

        $force = (bool) apply_filters('pr_theme_nav_force_location_assign', false);
        $assigned = [];

        foreach (array_keys($registered) as $location) {
            if (!is_string($location) || $location === '') {
                continue;
            }
            $current = (int) ($locations[$location] ?? 0);
            if ($current > 0 && !$force) {
                continue;
            }
            $locations[$location] = $menu_id;
            $assigned[] = $location;
        }

        if ($assigned !== [] && function_exists('set_theme_mod')) {
            set_theme_mod('nav_menu_locations', $locations);
        }

        return $assigned;
    }

    /**
     * @param list<string> $locations
     */
    private static function persist_bootstrap(int $menu_id, int $item_id, array $locations): void
    {
        $label = PluginSettings::theme_nav_menu_label();
        update_option(
            self::OPTION_BOOTSTRAP,
            [
                'version' => self::BOOTSTRAP_VERSION,
                'menu_id' => $menu_id,
                'menu_item_id' => $item_id,
                'locations' => $locations,
                'url' => self::reviews_url(),
                'label_hash' => self::label_hash($label),
                'bootstrapped_at' => gmdate('c'),
            ]
        );
    }

    private static function clear_bootstrap_option(): void
    {
        if (function_exists('delete_option')) {
            delete_option(self::OPTION_BOOTSTRAP);
        }
    }

    private static function set_status(string $status): void
    {
        if (function_exists('update_option')) {
            update_option(self::OPTION_STATUS, $status);
        }
    }

    private static function label_hash(string $label): string
    {
        return md5($label);
    }

    private static function normalize_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        return rtrim($url, '/');
    }
}
