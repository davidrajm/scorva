<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\AuditService;
use ProjectReviews\Services\SessionCloseService;

final class SessionCloseServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_user_meta'] = [];
        $GLOBALS['pr_test_user_caps_by_user'] = [];
        if (!defined('PR_CAP_MANAGE_SESSIONS')) {
            require_once dirname(__DIR__) . '/includes/capabilities.php';
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['pr_test_user_meta'], $GLOBALS['pr_test_user_caps_by_user']);
        parent::tearDown();
    }

    public function test_close_disables_provisioned_only_by_default(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 1,
            'title' => 'Close me',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 1,
            'user_id' => 100,
            'provisioned_for_session' => 1,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 1,
            'user_id' => 200,
            'provisioned_for_session' => 0,
        ]);

        $GLOBALS['pr_test_user_caps_by_user'][200] = [PR_CAP_MANAGE_SESSIONS => true];

        $result = (new SessionCloseService($this->wpdb))->close(1, false, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([100], $result['disabled_user_ids']);
        $session = (new SessionRepository($this->wpdb))->find_by_id(1);
        $this->assertSame(SessionRepository::STATUS_CLOSED, $session['status'] ?? '');
    }

    public function test_close_with_coordinator_flag_disables_managers(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 2,
            'title' => 'Close all',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 2,
            'user_id' => 300,
            'provisioned_for_session' => 0,
        ]);

        $GLOBALS['pr_test_user_caps_by_user'][300] = [PR_CAP_MANAGE_SESSIONS => true];

        $result = (new SessionCloseService($this->wpdb))->close(2, true, 1);

        $this->assertContains(300, $result['disabled_user_ids'] ?? []);
    }

    public function test_close_skips_provisioned_coordinator_without_opt_in(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 4,
            'title' => 'Coordinator provisioned',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 4,
            'user_id' => 400,
            'provisioned_for_session' => 1,
        ]);

        $GLOBALS['pr_test_user_caps_by_user'][400] = [PR_CAP_MANAGE_SESSIONS => true];

        $result = (new SessionCloseService($this->wpdb))->close(4, false, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['disabled_user_ids']);
    }

    public function test_close_preview_returns_null_when_session_missing(): void
    {
        $preview = (new SessionCloseService($this->wpdb))->close_preview(99999);

        $this->assertNull($preview);
    }

    public function test_close_preview_counts_open_marks_and_provisioned_users(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 10,
            'title' => 'Preview',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 10,
            'user_id' => 501,
            'provisioned_for_session' => 1,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 10,
            'user_id' => 502,
            'provisioned_for_session' => 0,
        ]);

        $preview = (new SessionCloseService($this->wpdb))->close_preview(10);

        $this->assertIsArray($preview);
        $this->assertSame(SessionRepository::STATUS_ACTIVE, $preview['status']);
        $this->assertSame(1, $preview['provisioned_users']);
    }

    public function test_close_writes_audit_rows(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 3,
            'title' => 'Audit',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);

        (new SessionCloseService($this->wpdb))->close(3, false, 9);
        $audit = new AuditService($this->wpdb);
        $log = $audit->list_for_session(3);

        $this->assertGreaterThan(0, $log['total']);
        $actions = array_column($log['items'], 'action');
        $this->assertContains('session_closed', $actions);
    }

    public function test_reopen_restores_active_and_reenables_provisioned_user(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 20,
            'title' => 'Reopen me',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 20,
            'user_id' => 100,
            'provisioned_for_session' => 1,
            'disabled_at' => '2026-01-01 00:00:00',
        ]);
        update_user_meta(100, 'pr_account_disabled', '1');

        $result = (new SessionCloseService($this->wpdb))->reopen(20, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([100], $result['reenabled_user_ids']);
        $session = (new SessionRepository($this->wpdb))->find_by_id(20);
        $this->assertSame(SessionRepository::STATUS_ACTIVE, $session['status'] ?? '');
        $this->assertArrayNotHasKey('pr_account_disabled', $GLOBALS['pr_test_user_meta'][100] ?? []);
    }

    public function test_reopen_restores_draft_from_audit(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 21,
            'title' => 'Draft reopen',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);
        (new AuditService($this->wpdb))->log(
            'session_closed',
            'session',
            21,
            SessionRepository::STATUS_DRAFT,
            SessionRepository::STATUS_CLOSED,
            1
        );

        $result = (new SessionCloseService($this->wpdb))->reopen(21, 1);

        $this->assertTrue($result['ok']);
        $session = (new SessionRepository($this->wpdb))->find_by_id(21);
        $this->assertSame(SessionRepository::STATUS_DRAFT, $session['status'] ?? '');
    }

    public function test_reopen_keeps_meta_when_user_disabled_on_second_session(): void
    {
        $prefix = $this->wpdb->prefix;
        foreach ([30, 31] as $session_id) {
            $this->wpdb->insert("{$prefix}pr_sessions", [
                'id' => $session_id,
                'title' => "Session {$session_id}",
                'status' => SessionRepository::STATUS_CLOSED,
            ]);
            $this->wpdb->insert("{$prefix}pr_session_reviewers", [
                'session_id' => $session_id,
                'user_id' => 500,
                'provisioned_for_session' => 1,
                'disabled_at' => '2026-01-01 00:00:00',
            ]);
        }
        update_user_meta(500, 'pr_account_disabled', '1');

        (new SessionCloseService($this->wpdb))->reopen(30, 1);

        $this->assertSame(
            '1',
            $GLOBALS['pr_test_user_meta'][500]['pr_account_disabled'] ?? null
        );
        $preview31 = (new SessionCloseService($this->wpdb))->close_preview(31);
        $this->assertIsArray($preview31);
        $this->assertSame(1, $preview31['disabled_accounts']);
    }

    public function test_reopen_returns_session_not_closed_for_active_project(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 22,
            'title' => 'Still active',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);

        $result = (new SessionCloseService($this->wpdb))->reopen(22, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame('session_not_closed', $result['error']);
    }

    public function test_reopen_writes_audit_rows(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 23,
            'title' => 'Reopen audit',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 23,
            'user_id' => 600,
            'provisioned_for_session' => 1,
            'disabled_at' => '2026-01-01 00:00:00',
        ]);

        (new SessionCloseService($this->wpdb))->reopen(23, 9);
        $audit = new AuditService($this->wpdb);
        $log = $audit->list_for_session(23);
        $actions = array_column($log['items'], 'action');

        $this->assertContains('session_reopened', $actions);
        $this->assertContains('account_enabled', $actions);
    }

    public function test_close_preview_includes_disabled_accounts_when_closed(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 24,
            'title' => 'Closed preview',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 24,
            'user_id' => 700,
            'provisioned_for_session' => 1,
            'disabled_at' => '2026-01-01 00:00:00',
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 24,
            'user_id' => 701,
            'provisioned_for_session' => 0,
        ]);

        $preview = (new SessionCloseService($this->wpdb))->close_preview(24);

        $this->assertIsArray($preview);
        $this->assertSame(1, $preview['disabled_accounts']);
    }

    public function test_close_preview_disabled_accounts_zero_when_not_closed(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 25,
            'title' => 'Open preview',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 25,
            'user_id' => 800,
            'provisioned_for_session' => 1,
            'disabled_at' => '2026-01-01 00:00:00',
        ]);

        $preview = (new SessionCloseService($this->wpdb))->close_preview(25);

        $this->assertIsArray($preview);
        $this->assertSame(0, $preview['disabled_accounts']);
    }
}
