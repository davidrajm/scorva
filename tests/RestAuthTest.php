<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Auth;
use WP_Error;
use WP_REST_Request;

final class RestAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
    }

    public function test_require_logged_in_rejects_guest(): void
    {
        $callback = Rest_Auth::require_logged_in();
        $result = $callback();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_not_logged_in', $result->get_error_code());
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function test_require_cap_rejects_guest_with_not_logged_in(): void
    {
        $callback = Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS);
        $result = $callback();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_not_logged_in', $result->get_error_code());
    }

    public function test_require_cap_rejects_user_without_capability(): void
    {
        RestTestFixtures::login_without_caps();

        $callback = Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS);
        $result = $callback();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['status']);
    }

    public function test_require_cap_allows_user_with_capability(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $callback = Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS);
        $result = $callback();

        $this->assertTrue($result);
    }

    public function test_require_any_pr_cap_rejects_user_without_project_caps(): void
    {
        RestTestFixtures::login_without_caps();

        $callback = Rest_Auth::require_any_pr_cap();
        $result = $callback();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
    }

    public function test_require_any_pr_cap_allows_user_with_any_pr_cap(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);

        $callback = Rest_Auth::require_any_pr_cap();
        $result = $callback();

        $this->assertTrue($result);
    }

    public function test_verify_rest_nonce_rejects_missing_nonce(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);

        $request = new WP_REST_Request();
        $result = Rest_Auth::verify_rest_nonce($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_cookie_invalid_nonce', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['status']);
    }

    public function test_verify_rest_nonce_accepts_valid_wp_rest_nonce(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce-token');

        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'valid-nonce-token');

        $result = Rest_Auth::verify_rest_nonce($request);

        $this->assertTrue($result);
    }

    public function test_with_rest_nonce_wraps_permission_after_nonce_check(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('mutation-nonce');

        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'mutation-nonce');

        $callback = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));
        $result = $callback($request);

        $this->assertTrue($result);
    }

    public function test_with_rest_nonce_rejects_before_capability_check(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $request = new WP_REST_Request();
        $callback = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));
        $result = $callback($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_cookie_invalid_nonce', $result->get_error_code());
    }
}

final class RestTestFixtures
{
    public static function reset(): void
    {
        global $pr_test_current_user_id, $pr_test_user_caps, $pr_test_rest_nonce, $pr_test_registered_routes, $pr_test_created_user_ids;
        $pr_test_current_user_id = 0;
        $pr_test_user_caps = [];
        $pr_test_rest_nonce = null;
        $pr_test_registered_routes = [];
        $pr_test_created_user_ids = [];
    }

    public static function track_created_user_id(int $user_id): void
    {
        global $pr_test_created_user_ids;
        if (!isset($pr_test_created_user_ids)) {
            $pr_test_created_user_ids = [];
        }
        if ($user_id > 0 && !in_array($user_id, $pr_test_created_user_ids, true)) {
            $pr_test_created_user_ids[] = $user_id;
        }
    }

    public static function login_without_caps(): void
    {
        global $pr_test_current_user_id, $pr_test_user_caps;
        $pr_test_current_user_id = 42;
        $pr_test_user_caps = [];
    }

    public static function login_with_cap(string $cap): void
    {
        global $pr_test_current_user_id, $pr_test_user_caps;
        $pr_test_current_user_id = 42;
        $pr_test_user_caps = [$cap => true];
    }

    /**
     * @param list<string> $caps
     */
    public static function login_with_caps(array $caps): void
    {
        global $pr_test_current_user_id, $pr_test_user_caps;
        $pr_test_current_user_id = 42;
        $pr_test_user_caps = [];
        foreach ($caps as $cap) {
            $pr_test_user_caps[$cap] = true;
        }
    }

    public static function set_valid_rest_nonce(string $nonce): void
    {
        global $pr_test_rest_nonce;
        $pr_test_rest_nonce = $nonce;
    }
}
