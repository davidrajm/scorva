<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Capabilities;

final class CapabilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('PR_PLUGIN_VERSION')) {
            define('PR_PLUGIN_VERSION', '0.1.0');
        }
        CapabilitiesTestFixtures::reset();
    }

    public function test_capability_constants_exist(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';

        $this->assertSame('pr_manage_sessions', PR_CAP_MANAGE_SESSIONS);
        $this->assertContains('pr_enter_marks', Capabilities::all());
    }

    public function test_all_capabilities_from_design_spec(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';

        $expected = [
            'pr_manage_sessions',
            'pr_upload_students',
            'pr_manage_panels',
            'pr_assign_reviewers',
            'pr_configure_weights',
            'pr_confirm_rubrics',
            'pr_enter_marks',
            'pr_override_marks',
            'pr_view_reports',
            'pr_close_session',
            'pr_manage_settings',
        ];

        $this->assertSame($expected, Capabilities::all());
    }

    public function test_apply_defaults_grants_administrator_all_caps(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';

        CapabilitiesTestFixtures::seed_administrator_role();
        Capabilities::apply_defaults();

        $admin = get_role('administrator');
        $this->assertNotNull($admin);

        foreach (Capabilities::all() as $cap) {
            $this->assertTrue($admin->has_cap($cap), "Administrator missing cap: {$cap}");
        }
    }

    public function test_coordinator_role_includes_override_marks(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';

        Capabilities::apply_defaults();

        $coordinator = get_role(Capabilities::ROLE_COORDINATOR);
        $this->assertNotNull($coordinator);
        $this->assertTrue($coordinator->has_cap(\PR_CAP_OVERRIDE_MARKS));

        foreach (Capabilities::coordinator_caps() as $cap) {
            $this->assertTrue($coordinator->has_cap($cap), "Coordinator missing cap: {$cap}");
        }
    }

    public function test_reviewer_role_only_enter_marks(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';

        Capabilities::apply_defaults();

        $reviewer = get_role(Capabilities::ROLE_REVIEWER);
        $this->assertNotNull($reviewer);
        $this->assertTrue($reviewer->has_cap(\PR_CAP_ENTER_MARKS));

        $other_caps = array_diff(Capabilities::all(), [\PR_CAP_ENTER_MARKS]);
        foreach ($other_caps as $cap) {
            $this->assertFalse($reviewer->has_cap($cap), "Reviewer should not have cap: {$cap}");
        }
    }

    public function test_user_has_coordinator_workspace_access(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';

        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_user_caps'] = [\PR_CAP_ENTER_MARKS => true];
        $this->assertFalse(Capabilities::user_has_coordinator_workspace_access());
        $this->assertTrue(Capabilities::user_has_reviewer_workspace_access());

        $GLOBALS['pr_test_user_caps'] = [\PR_CAP_MANAGE_SESSIONS => true];
        $this->assertTrue(Capabilities::user_has_coordinator_workspace_access());
        $this->assertFalse(Capabilities::user_has_reviewer_workspace_access());
    }

    public function test_apply_defaults_is_idempotent_by_version(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';

        CapabilitiesTestFixtures::seed_administrator_role();
        Capabilities::apply_defaults();
        $admin = get_role('administrator');
        $admin->remove_cap(\PR_CAP_MANAGE_SESSIONS);

        Capabilities::apply_defaults();
        $this->assertFalse($admin->has_cap(\PR_CAP_MANAGE_SESSIONS));
    }
}

/**
 * Test-only helpers for role/option stubs (see tests/bootstrap.php).
 */
final class CapabilitiesTestFixtures
{
    public static function reset(): void
    {
        global $pr_test_roles, $pr_test_options;
        $pr_test_roles = [];
        $pr_test_options = [];
    }

    public static function seed_administrator_role(): void
    {
        add_role('administrator', 'Administrator', []);
    }
}
