<?php

declare(strict_types=1);

namespace ProjectReviews\Testing;

use ProjectReviews\Capabilities;
use ProjectReviews\Install;

/**
 * Opt-in teardown for E2E/staging data. Never invoked from PHPUnit tearDown or CI test suites.
 */
final class TestTeardown
{
    /**
     * @param array{
     *     dry_run?: bool,
     *     confirm?: bool,
     *     purge_options?: bool,
     *     full_drop?: bool,
     *     force_local?: bool
     * } $options
     * @return array{exit_code: int, lines: list<string>}
     */
    public static function run(array $options = []): array
    {
        $dryRun = !empty($options['dry_run']);
        $confirm = !empty($options['confirm']);
        $purgeOptions = !empty($options['purge_options']);
        $fullDrop = !empty($options['full_drop']);
        $forceLocal = !empty($options['force_local']);

        $lines = [];

        if (!$confirm && !$dryRun) {
            $lines[] = 'Refusing to run without --confirm or --dry-run.';
            $lines[] = 'Example: composer test:teardown -- --confirm';

            return ['exit_code' => 1, 'lines' => $lines];
        }

        if (defined('PR_TEST_TEARDOWN_DISABLED') && PR_TEST_TEARDOWN_DISABLED && !$forceLocal) {
            $lines[] = 'PR_TEST_TEARDOWN_DISABLED is set.';

            return ['exit_code' => 1, 'lines' => $lines];
        }

        $envType = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        if (!in_array($envType, ['local', 'development'], true) && !$forceLocal) {
            $lines[] = "Environment is '{$envType}'. Pass --force-local to override.";

            return ['exit_code' => 1, 'lines' => $lines];
        }

        global $wpdb;
        if (!isset($wpdb)) {
            $lines[] = 'WordPress database ($wpdb) is not available.';

            return ['exit_code' => 1, 'lines' => $lines];
        }

        $prefix = (string) ($wpdb->prefix ?? '');
        $tables = Install::get_pr_table_names($prefix);
        $fixtureUsers = self::fixture_user_ids();

        $prefixLabel = $dryRun ? '[dry-run] ' : '';
        $lines[] = $prefixLabel . 'Would truncate ' . count($tables) . ' pr_* tables.';
        $lines[] = $prefixLabel . 'Would delete ' . count($fixtureUsers) . ' fixture user(s).';
        if ($purgeOptions) {
            $lines[] = $prefixLabel . 'Would delete plugin options.';
        }
        if ($fullDrop) {
            $lines[] = $prefixLabel . 'WARNING: Would DROP all plugin tables and recreate schema on next activation.';
        }

        if ($dryRun || !$confirm) {
            return ['exit_code' => $dryRun ? 0 : 1, 'lines' => $lines];
        }

        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$table}");
        }

        if ($purgeOptions) {
            foreach (Install::get_uninstall_option_names() as $option) {
                if (function_exists('delete_option')) {
                    delete_option($option);
                }
            }
        }

        foreach ($fixtureUsers as $userId) {
            if (function_exists('wp_delete_user')) {
                wp_delete_user((int) $userId);
            }
        }

        if ($fullDrop) {
            Install::drop_all($wpdb);
            if (method_exists(Install::class, 'maybe_upgrade')) {
                Install::maybe_upgrade();
            }
        }

        if (class_exists(Capabilities::class)) {
            Capabilities::remove_custom_roles_if_empty();
        }

        $lines[] = 'Teardown complete.';

        return ['exit_code' => 0, 'lines' => $lines];
    }

    /**
     * Truncate plugin tables only (schema preserved). For PHPUnit stub DB.
     */
    public static function truncate_tables(object $wpdb): void
    {
        $prefix = (string) ($wpdb->prefix ?? '');
        foreach (Install::get_pr_table_names($prefix) as $table) {
            $wpdb->query("TRUNCATE TABLE {$table}");
        }
    }

    /**
     * @return list<int>
     */
    public static function fixture_user_ids(): array
    {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users([
            'meta_key' => 'pr_test_fixture',
            'meta_value' => '1',
            'fields' => 'ID',
        ]);

        $ids = [];
        foreach ($users as $user) {
            if (is_numeric($user)) {
                $ids[] = (int) $user;
            } elseif (is_object($user) && isset($user->ID)) {
                $ids[] = (int) $user->ID;
            }
        }

        return $ids;
    }
}
