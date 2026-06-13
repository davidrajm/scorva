<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Rest_Auth;
use ProjectReviews\Rest_Portal;
use ProjectReviews\Services\ReviewerProvisionService;
use ProjectReviews\Services\ReviewerSessionService;
use WP_Error;
use WP_REST_Request;

final class RestPortalTest extends TestCase
{
    private FakeWpdb $wpdb;

    private SessionRepository $sessions;

    private PanelRepository $panels;

    private int $session_id;

    private int $panel_id;

    private int $reviewer_id;

    private string $token;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/tests/RestAuthTest.php';
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_transients'] = [];
        $GLOBALS['pr_test_options'] = [];
        $GLOBALS['pr_test_users'] = [];
        $GLOBALS['pr_test_current_user_id'] = 0;
        unset($_COOKIE[ReviewerSessionService::COOKIE_NAME]);

        $this->sessions = new SessionRepository($this->wpdb);
        $this->panels = new PanelRepository($this->wpdb);

        $this->session_id = $this->sessions->create(['title' => 'Portal test project']);
        $this->panel_id = $this->panels->create($this->session_id, 'Panel A');
        $this->reviewer_id = $this->panels->add_reviewer($this->panel_id, [
            'email' => 'reviewer@example.com',
            'name' => 'Dr. Portal',
            'weight' => 1,
        ]);

        $service = new ReviewerProvisionService($this->sessions, $this->panels);
        $result = $service->generate_reviewer_credentials($this->session_id, $this->reviewer_id);
        $this->token = $result['token'];
        $this->password = $result['password'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $_COOKIE[ReviewerSessionService::COOKIE_NAME]);
        $GLOBALS['pr_test_transients'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        parent::tearDown();
    }

    private function auth_request(string $token, string $password): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_json_params(['token' => $token, 'password' => $password]);

        return $request;
    }

    public function test_credentials_assign_roster_identity_without_wp_user(): void
    {
        $stored = $this->panels->find_reviewer($this->reviewer_id);

        $this->assertSame($this->reviewer_id, (int) $stored['user_id']);
        $this->assertSame([], $GLOBALS['pr_test_users'] ?? []);
    }

    public function test_token_status_reports_validity(): void
    {
        $valid = new WP_REST_Request();
        $valid->set_params(['token' => $this->token]);
        $this->assertTrue(Rest_Portal::token_status($valid)['valid']);

        $invalid = new WP_REST_Request();
        $invalid->set_params(['token' => str_repeat('0', 64)]);
        $this->assertFalse(Rest_Portal::token_status($invalid)['valid']);

        $malformed = new WP_REST_Request();
        $malformed->set_params(['token' => 'not-a-token']);
        $this->assertFalse(Rest_Portal::token_status($malformed)['valid']);
    }

    public function test_auth_with_valid_credentials_starts_session(): void
    {
        $result = Rest_Portal::auth($this->auth_request($this->token, $this->password));

        $this->assertIsArray($result);
        $this->assertTrue($result['authenticated']);
        $this->assertSame($this->reviewer_id, $result['reviewer']['id']);
        $this->assertSame('Dr. Portal', $result['reviewer']['name']);
        $this->assertSame($this->session_id, $result['project']['id']);
        $this->assertSame('Portal test project', $result['project']['title']);

        $context = Rest_Auth::reviewer_session_context();
        $this->assertNotNull($context);
        $this->assertSame($this->reviewer_id, $context['reviewer_id']);
        $this->assertSame($this->session_id, $context['session_id']);

        $permission = Rest_Auth::require_reviewer_session();
        $this->assertTrue($permission());
    }

    public function test_auth_with_wrong_password_returns_401(): void
    {
        $result = Rest_Portal::auth($this->auth_request($this->token, 'wrong-password'));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_portal_invalid_password', $result->get_error_code());
        $this->assertNull(Rest_Auth::reviewer_session_context());
    }

    public function test_auth_with_unknown_token_returns_401(): void
    {
        $result = Rest_Portal::auth($this->auth_request(str_repeat('a', 64), $this->password));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_portal_invalid_token', $result->get_error_code());
    }

    public function test_auth_throttles_after_repeated_failures(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Rest_Portal::auth($this->auth_request($this->token, 'wrong-password'));
        }

        $result = Rest_Portal::auth($this->auth_request($this->token, $this->password));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_portal_throttled', $result->get_error_code());
    }

    public function test_auth_rejected_when_project_closed(): void
    {
        $this->sessions->update($this->session_id, ['status' => 'closed']);

        $result = Rest_Portal::auth($this->auth_request($this->token, $this->password));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_portal_session_closed', $result->get_error_code());
    }

    public function test_session_endpoint_requires_cookie(): void
    {
        $result = Rest_Portal::session(new WP_REST_Request());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_portal_unauthorized', $result->get_error_code());
    }

    public function test_session_endpoint_returns_context_after_auth(): void
    {
        Rest_Portal::auth($this->auth_request($this->token, $this->password));

        $result = Rest_Portal::session(new WP_REST_Request());

        $this->assertIsArray($result);
        $this->assertSame($this->reviewer_id, $result['reviewer']['id']);
        $this->assertSame($this->session_id, $result['project']['id']);
    }

    public function test_logout_destroys_session(): void
    {
        Rest_Portal::auth($this->auth_request($this->token, $this->password));
        $this->assertNotNull(Rest_Auth::reviewer_session_context());

        $result = Rest_Portal::logout(new WP_REST_Request());

        $this->assertTrue($result['logged_out']);
        $this->assertNull(Rest_Auth::reviewer_session_context());

        $permission = Rest_Auth::require_reviewer_session();
        $this->assertInstanceOf(WP_Error::class, $permission());
    }

    public function test_require_reviewer_session_rejects_forged_cookie(): void
    {
        $_COOKIE[ReviewerSessionService::COOKIE_NAME] = str_repeat('f', 64);

        $permission = Rest_Auth::require_reviewer_session();
        $result = $permission();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_portal_unauthorized', $result->get_error_code());
    }
}
