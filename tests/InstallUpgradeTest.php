<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Install;

final class InstallUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['pr_test_options'] = [];
        $GLOBALS['pr_test_dbdelta_calls'] = [];
        $GLOBALS['wpdb'] = new FakeWpdb();
    }

    public function test_maybe_upgrade_runs_dbdelta_when_version_behind(): void
    {
        Install::maybe_upgrade();

        $this->assertSame(PR_PLUGIN_VERSION, get_option('pr_db_version'));
        $this->assertGreaterThanOrEqual(1, count($GLOBALS['pr_test_dbdelta_calls']));
        $sql = $GLOBALS['pr_test_dbdelta_calls'][0];
        $this->assertStringContainsString('pr_students', $sql);
        $this->assertStringContainsString('pr_field_definitions', $sql);
        $this->assertStringContainsString('pr_student_meta', $sql);
    }

    public function test_maybe_upgrade_is_idempotent_when_version_current(): void
    {
        update_option('pr_db_version', PR_PLUGIN_VERSION);
        $GLOBALS['pr_test_dbdelta_calls'] = [];

        Install::maybe_upgrade();

        $this->assertSame([], $GLOBALS['pr_test_dbdelta_calls']);
    }
}
