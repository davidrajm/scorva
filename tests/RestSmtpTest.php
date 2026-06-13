<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Smtp;
use WP_Error;
use WP_REST_Request;

final class RestSmtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/tests/RestAuthTest.php';
        RestTestFixtures::reset();
        $GLOBALS['pr_test_options'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_users'] = [];
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/services/PluginSettings.php';
        require_once dirname(__DIR__) . '/includes/services/SmtpService.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-smtp.php';

        Rest_Smtp::register_routes();
    }

    protected function tearDown(): void
    {
        $GLOBALS['pr_test_options'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_users'] = [];
        parent::tearDown();
    }

    public function test_test_endpoint_route_is_registered(): void
    {
        $routes = array_values(array_filter(
            $GLOBALS['pr_test_registered_routes'],
            static fn (array $route): bool => $route['route'] === '/settings/smtp/test'
        ));

        $this->assertCount(1, $routes);
        $this->assertSame(Rest_Bootstrap::NAMESPACE, $routes[0]['namespace']);
        $this->assertSame('POST', $routes[0]['args']['methods']);
    }

    public function test_permission_requires_manage_settings_cap(): void
    {
        RestTestFixtures::login_without_caps();

        $permission = \ProjectReviews\Rest_Auth::require_cap(PR_CAP_MANAGE_SETTINGS);
        $result = $permission(new WP_REST_Request());

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_send_test_emails_current_user(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SETTINGS);

        $response = Rest_Smtp::send_test(new WP_REST_Request());

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['sent']);
        $this->assertSame('user42@example.test', $data['to']);
        $this->assertCount(1, $GLOBALS['pr_test_sent_mail']);
        $this->assertSame('user42@example.test', $GLOBALS['pr_test_sent_mail'][0]['to']);
    }

    public function test_send_test_fails_when_user_has_no_email(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SETTINGS);

        $user = new \Pr_Test_User();
        $user->ID = 42;
        $user->user_login = 'admin';
        $user->user_email = '';
        $user->display_name = 'Admin';
        $GLOBALS['pr_test_users'][42] = $user;

        $result = Rest_Smtp::send_test(new WP_REST_Request());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_smtp_no_recipient', $result->get_error_code());
        $this->assertSame([], $GLOBALS['pr_test_sent_mail']);
    }
}
