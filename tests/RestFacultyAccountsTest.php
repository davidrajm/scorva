<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Faculty_Accounts;
use WP_Error;
use WP_REST_Request;

final class RestFacultyAccountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $GLOBALS['wpdb'] = new FakeWpdb();
        $GLOBALS['pr_test_users'] = [];
        $GLOBALS['pr_test_user_meta'] = [];

        if (!defined('PR_CAP_ASSIGN_REVIEWERS')) {
            define('PR_CAP_ASSIGN_REVIEWERS', 'pr_assign_reviewers');
        }

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-faculty-accounts.php';

        Rest_Bootstrap::register_routes();
    }

    public function test_list_accounts_requires_capability(): void
    {
        RestTestFixtures::login_without_caps();
        RestTestFixtures::set_valid_rest_nonce('faculty-list');

        $route = $this->find_route('/faculty-accounts', 'GET');
        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'faculty-list');

        $result = $route['args']['permission_callback']($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
    }

    public function test_import_accounts_with_capability(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ASSIGN_REVIEWERS);
        RestTestFixtures::set_valid_rest_nonce('faculty-import');

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        add_role(\ProjectReviews\Capabilities::ROLE_REVIEWER, 'Reviewer');

        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'faculty-import');
        $request->set_json_params([
            'rows' => [
                [
                    'empId' => 'EMP100',
                    'name' => 'REST Faculty',
                    'email' => 'rest-faculty@example.com',
                ],
            ],
            'duplicate_policy' => 'skip',
        ]);

        $result = Rest_Faculty_Accounts::import_accounts($request);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['imported']);
    }

    /**
     * @return array{args: array<string, mixed>}
     */
    private function find_route(string $path, string $method): array
    {
        global $pr_test_registered_routes;
        foreach ($pr_test_registered_routes as $registered) {
            if ($registered['route'] !== $path) {
                continue;
            }
            $args = $registered['args'];
            if (isset($args[0]) && is_array($args[0])) {
                foreach ($args as $variant) {
                    if (($variant['methods'] ?? '') === $method) {
                        return ['args' => $variant];
                    }
                }
            }
            if (($args['methods'] ?? '') === $method) {
                return ['args' => $args];
            }
        }

        $this->fail("Route {$path} {$method} not registered.");
    }
}
