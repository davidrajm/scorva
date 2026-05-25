<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Capabilities;
use ProjectReviews\Install;
use ProjectReviews\Plugin;
use ProjectReviews\Uninstall;

final class UninstallTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('PR_PLUGIN_VERSION')) {
            define('PR_PLUGIN_VERSION', '0.1.0');
        }

        global $wpdb, $pr_test_options, $pr_test_roles;
        $pr_test_options = [];
        $pr_test_roles = [];
        $this->wpdb = new FakeWpdb();
        $wpdb = $this->wpdb;

        foreach (Install::get_pr_table_names($this->wpdb->prefix) as $table) {
            $this->wpdb->register_table_columns($table, ['id']);
        }
        $view = $this->wpdb->prefix . 'pr_rubric_scores';
        $this->wpdb->query(Install::rubric_scores_view_ddl($this->wpdb->prefix));
        $this->assertTrue($this->wpdb->has_view($view));
    }

    public function test_deactivate_does_not_drop_tables(): void
    {
        require_once dirname(__DIR__) . '/includes/class-plugin.php';

        $tables_before = Install::get_pr_table_names($this->wpdb->prefix);
        foreach ($tables_before as $table) {
            $this->assertNotNull($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'"));
        }

        Plugin::deactivate();

        foreach ($tables_before as $table) {
            $this->assertNotNull(
                $this->wpdb->get_var("SHOW TABLES LIKE '{$table}'"),
                "Table should remain after deactivation: {$table}"
            );
        }

        $drop_queries = array_filter(
            $this->wpdb->queries,
            static fn (string $sql): bool => stripos($sql, 'DROP TABLE') !== false
                || stripos($sql, 'DROP VIEW') !== false
        );
        $this->assertSame([], array_values($drop_queries));
    }

    public function test_uninstall_skips_drop_when_option_false(): void
    {
        require_once dirname(__DIR__) . '/includes/Uninstall.php';
        update_option('pr_delete_data_on_uninstall', false);

        $this->wpdb->queries = [];
        Uninstall::run();

        $drop_queries = array_filter(
            $this->wpdb->queries,
            static fn (string $sql): bool => stripos($sql, 'DROP TABLE') !== false
                || stripos($sql, 'DROP VIEW') !== false
        );
        $this->assertSame([], array_values($drop_queries));

        foreach (Install::get_pr_table_names($this->wpdb->prefix) as $table) {
            $this->assertNotNull($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'"));
        }
    }

    public function test_uninstall_drops_when_option_true(): void
    {
        require_once dirname(__DIR__) . '/includes/Uninstall.php';
        update_option('pr_delete_data_on_uninstall', true);
        foreach (Install::get_uninstall_option_names() as $option) {
            update_option($option, 'stub');
        }

        $this->wpdb->queries = [];
        Uninstall::run();

        $view = $this->wpdb->prefix . 'pr_rubric_scores';
        $this->assertNull($this->wpdb->get_var("SHOW FULL TABLES LIKE '{$view}'"));
        foreach (Install::get_pr_table_names($this->wpdb->prefix) as $table) {
            $this->assertNull($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'"));
        }

        $drop_view = array_filter(
            $this->wpdb->queries,
            static fn (string $sql): bool => stripos($sql, 'DROP VIEW IF EXISTS') !== false
        );
        $this->assertNotEmpty($drop_view);

        $drop_tables = array_filter(
            $this->wpdb->queries,
            static fn (string $sql): bool => stripos($sql, 'DROP TABLE IF EXISTS') !== false
        );
        $this->assertCount(count(Install::get_pr_table_names($this->wpdb->prefix)), $drop_tables);

        foreach (Install::get_uninstall_option_names() as $option) {
            $this->assertFalse(
                array_key_exists($option, $GLOBALS['pr_test_options'] ?? []),
                "Option should be deleted: {$option}"
            );
        }
    }

    public function test_uninstall_removes_caps_from_administrator(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/Uninstall.php';

        CapabilitiesTestFixtures::seed_administrator_role();
        Capabilities::apply_defaults();
        update_option('pr_delete_data_on_uninstall', true);

        $admin = get_role('administrator');
        $this->assertNotNull($admin);
        $this->assertTrue($admin->has_cap(\PR_CAP_MANAGE_SESSIONS));

        Uninstall::run();

        foreach (Capabilities::all() as $cap) {
            $this->assertFalse($admin->has_cap($cap), "Administrator should lose cap: {$cap}");
        }
    }
}
