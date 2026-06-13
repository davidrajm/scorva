<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Rest_Session_Close;
use WP_Error;
use WP_REST_Request;

final class RestSessionCloseTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_user_meta'] = [];

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/services/SessionCloseService.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-session-close.php';

        Rest_Session_Close::register_routes();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['pr_test_user_meta']);
        parent::tearDown();
    }

    public function test_reopen_returns_session_and_reenabled_ids(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 1,
            'title' => 'Closed REST',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 1,
            'user_id' => 42,
            'provisioned_for_session' => 1,
            'disabled_at' => '2026-01-01 00:00:00',
        ]);

        RestTestFixtures::login_with_cap(PR_CAP_CLOSE_SESSION);
        RestTestFixtures::set_valid_rest_nonce('reopen');

        $request = new WP_REST_Request('POST', '/scorva/v1/sessions/1/reopen');
        $request->set_param('id', 1);

        $result = Rest_Session_Close::reopen_session($request);

        $this->assertIsArray($result);
        $this->assertSame(SessionRepository::STATUS_ACTIVE, $result['session']['status'] ?? '');
        $this->assertSame([42], $result['reenabled_user_ids']);
    }

    public function test_reopen_returns_400_when_not_closed(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 2,
            'title' => 'Active REST',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);

        RestTestFixtures::login_with_cap(PR_CAP_CLOSE_SESSION);
        RestTestFixtures::set_valid_rest_nonce('reopen-not-closed');

        $request = new WP_REST_Request('POST', '/scorva/v1/sessions/2/reopen');
        $request->set_param('id', 2);

        $result = Rest_Session_Close::reopen_session($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('session_not_closed', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status'] ?? 0);
    }

    public function test_close_preview_includes_disabled_accounts(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 3,
            'title' => 'Preview REST',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);
        $this->wpdb->insert("{$prefix}pr_session_reviewers", [
            'session_id' => 3,
            'user_id' => 99,
            'provisioned_for_session' => 1,
            'disabled_at' => '2026-01-01 00:00:00',
        ]);

        RestTestFixtures::login_with_cap(PR_CAP_CLOSE_SESSION);

        $request = new WP_REST_Request('GET', '/scorva/v1/sessions/3/close-preview');
        $request->set_param('id', 3);

        $result = Rest_Session_Close::close_preview($request);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['disabled_accounts']);
    }
}
