<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
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
        if (!defined('PR_CAP_MANAGE_SESSIONS')) {
            require_once dirname(__DIR__) . '/includes/capabilities.php';
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['pr_test_user_meta']);
        parent::tearDown();
    }

    public function test_close_sets_session_closed(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 1,
            'title' => 'Close me',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);

        $result = (new SessionCloseService($this->wpdb))->close(1, 1);

        $this->assertTrue($result['ok']);
        $session = (new SessionRepository($this->wpdb))->find_by_id(1);
        $this->assertSame(SessionRepository::STATUS_CLOSED, $session['status'] ?? '');
    }

    public function test_close_returns_error_for_missing_session(): void
    {
        $result = (new SessionCloseService($this->wpdb))->close(99999, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame('session_not_found', $result['error']);
    }

    public function test_close_returns_error_when_already_closed(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 2,
            'title' => 'Already closed',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);

        $result = (new SessionCloseService($this->wpdb))->close(2, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame('session_already_closed', $result['error']);
    }

    public function test_close_preview_returns_null_when_session_missing(): void
    {
        $preview = (new SessionCloseService($this->wpdb))->close_preview(99999);

        $this->assertNull($preview);
    }

    public function test_close_preview_counts_credentialed_reviewers(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Preview', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel A');

        $r1_id = $panels->add_reviewer($panel_id, [
            'email' => 'a@example.com',
            'name' => 'Reviewer A',
        ]);
        $panels->update_reviewer($r1_id, ['token' => str_repeat('a', 64), 'password_hash' => 'hash_a']);
        $panels->add_reviewer($panel_id, [
            'email' => 'b@example.com',
            'name' => 'Reviewer B',
        ]);

        $preview = (new SessionCloseService($this->wpdb))->close_preview($session_id);

        $this->assertIsArray($preview);
        $this->assertSame(SessionRepository::STATUS_ACTIVE, $preview['status']);
        $this->assertSame(1, $preview['credentialed_reviewers']);
    }

    public function test_close_writes_audit_rows(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 3,
            'title' => 'Audit',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);

        (new SessionCloseService($this->wpdb))->close(3, 9);
        $audit = new AuditService($this->wpdb);
        $log = $audit->list_for_session(3);

        $this->assertGreaterThan(0, $log['total']);
        $actions = array_column($log['items'], 'action');
        $this->assertContains('session_closed', $actions);
    }

    public function test_reopen_restores_active_status(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 20,
            'title' => 'Reopen me',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);

        $result = (new SessionCloseService($this->wpdb))->reopen(20, 1);

        $this->assertTrue($result['ok']);
        $session = (new SessionRepository($this->wpdb))->find_by_id(20);
        $this->assertSame(SessionRepository::STATUS_ACTIVE, $session['status'] ?? '');
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

        (new SessionCloseService($this->wpdb))->reopen(23, 9);
        $audit = new AuditService($this->wpdb);
        $log = $audit->list_for_session(23);
        $actions = array_column($log['items'], 'action');

        $this->assertContains('session_reopened', $actions);
    }
}
