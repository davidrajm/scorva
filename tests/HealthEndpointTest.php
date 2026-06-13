<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Auth;
use ProjectReviews\Rest_Bootstrap;
use WP_Error;

final class HealthEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        if (!defined('PR_PLUGIN_VERSION')) {
            define('PR_PLUGIN_VERSION', '0.1.0');
        }
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
    }

    public function test_handle_health_returns_version(): void
    {
        $payload = Rest_Bootstrap::handle_health();

        $this->assertSame('ok', $payload['status']);
        $this->assertSame('0.1.0', $payload['version']);
    }

    public function test_register_routes_registers_health_endpoint(): void
    {
        Rest_Bootstrap::register_routes();

        global $pr_test_registered_routes;
        $health = $this->find_route('/health');
        $this->assertSame('scorva/v1', $health['namespace']);
        $this->assertSame('/health', $health['route']);
        $this->assertSame('GET', $health['args']['methods']);
    }

    public function test_health_permission_rejects_logged_out_user(): void
    {
        Rest_Bootstrap::register_routes();

        global $pr_test_registered_routes;
        $permission = $pr_test_registered_routes[0]['args']['permission_callback'];
        $result = $permission();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_not_logged_in', $result->get_error_code());
    }

    public function test_health_permission_allows_user_with_pr_cap(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        Rest_Bootstrap::register_routes();

        global $pr_test_registered_routes;
        $permission = $pr_test_registered_routes[0]['args']['permission_callback'];
        $result = $permission();

        $this->assertTrue($result);
    }

    public function test_plugin_init_registers_rest_routes_on_rest_api_init(): void
    {
        if (!defined('PR_PLUGIN_DIR')) {
            define('PR_PLUGIN_DIR', dirname(__DIR__) . '/');
        }
        if (!defined('PR_PLUGIN_FILE')) {
            define('PR_PLUGIN_FILE', dirname(__DIR__) . '/project-reviews.php');
        }

        require_once dirname(__DIR__) . '/includes/class-plugin.php';
        \ProjectReviews\Plugin::instance()->init();

        global $pr_test_registered_routes;
        $this->assertNotEmpty($pr_test_registered_routes);
        $health = $this->find_route('/health');
        $this->assertSame('/health', $health['route']);
    }

    /**
     * @return array{namespace: string, route: string, args: array<string, mixed>}
     */
    private function find_route(string $route): array
    {
        global $pr_test_registered_routes;
        foreach ($pr_test_registered_routes as $registered) {
            if ($registered['route'] === $route) {
                return $registered;
            }
        }

        $this->fail("Route {$route} not registered.");
    }
}
