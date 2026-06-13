<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
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

    public function test_reopen_returns_session(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => 1,
            'title' => 'Closed REST',
            'status' => SessionRepository::STATUS_CLOSED,
        ]);

        RestTestFixtures::login_with_cap(PR_CAP_CLOSE_SESSION);
        RestTestFixtures::set_valid_rest_nonce('reopen');

        $request = new WP_REST_Request('POST', '/project-reviews/v1/sessions/1/reopen');
        $request->set_param('id', 1);

        $result = Rest_Session_Close::reopen_session($request);

        $this->assertIsArray($result);
        $this->assertSame(SessionRepository::STATUS_ACTIVE, $result['session']['status'] ?? '');
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

        $request = new WP_REST_Request('POST', '/project-reviews/v1/sessions/2/reopen');
        $request->set_param('id', 2);

        $result = Rest_Session_Close::reopen_session($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('session_not_closed', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status'] ?? 0);
    }

    public function test_close_preview_includes_credentialed_reviewers(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Preview REST', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel A');
        $r_id = $panels->add_reviewer($panel_id, ['email' => 'r@example.com', 'name' => 'R']);
        $panels->update_reviewer($r_id, ['token' => str_repeat('b', 64), 'password_hash' => 'hash_b']);

        RestTestFixtures::login_with_cap(PR_CAP_CLOSE_SESSION);

        $request = new WP_REST_Request('GET', "/project-reviews/v1/sessions/{$session_id}/close-preview");
        $request->set_param('id', $session_id);

        $result = Rest_Session_Close::close_preview($request);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['credentialed_reviewers']);
    }
}
