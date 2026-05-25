<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Students;
use ProjectReviews\Rest_Bootstrap;
use WP_Error;
use WP_REST_Request;

final class RestStudentsTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        if (!defined('PR_CAP_UPLOAD_STUDENTS')) {
            define('PR_CAP_UPLOAD_STUDENTS', 'pr_upload_students');
        }

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-students.php';

        Rest_Bootstrap::register_routes();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_list_students_requires_capability(): void
    {
        RestTestFixtures::login_without_caps();
        RestTestFixtures::set_valid_rest_nonce('list-students');
        $route = $this->find_route('/students', 'GET');
        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'list-students');
        $result = $route['args']['permission_callback']($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
    }

    public function test_create_student_validates_required_fields(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_UPLOAD_STUDENTS);
        RestTestFixtures::set_valid_rest_nonce('create-student');

        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'create-student');
        $request->set_json_params(['reg_no' => '', 'name' => '']);

        $result = Rest_Students::create_student($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_invalid_student', $result->get_error_code());
    }

    public function test_create_and_list_students(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_UPLOAD_STUDENTS);

        $create = new WP_REST_Request();
        $create->set_json_params([
            'reg_no' => 'R100',
            'name' => 'Grace Hopper',
            'batch' => '2026',
        ]);

        $created = Rest_Students::create_student($create);
        $this->assertIsArray($created);
        $this->assertSame('R100', $created['reg_no']);

        $list_request = new WP_REST_Request();
        $list_request->set_param('search', 'Grace');
        $list = Rest_Students::list_students($list_request);

        $this->assertCount(1, $list['students']);
        $this->assertSame('Grace Hopper', $list['students'][0]['name']);
    }

    public function test_duplicate_reg_no_returns_clear_error(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_UPLOAD_STUDENTS);

        $request = new WP_REST_Request();
        $request->set_json_params([
            'reg_no' => 'R200',
            'name' => 'First Student',
        ]);
        Rest_Students::create_student($request);

        $duplicate = new WP_REST_Request();
        $duplicate->set_json_params([
            'reg_no' => 'R200',
            'name' => 'Second Student',
        ]);
        $result = Rest_Students::create_student($duplicate);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_duplicate_reg_no', $result->get_error_code());
        $this->assertSame(409, $result->get_error_data()['status']);
    }

    public function test_field_schema_crud(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_UPLOAD_STUDENTS);

        $create = new WP_REST_Request();
        $create->set_json_params([
            'field_key' => 'department',
            'label' => 'Department',
        ]);
        $field = Rest_Students::create_field_definition($create);
        $this->assertSame('department', $field['field_key']);

        $schema = Rest_Students::list_field_schema();
        $this->assertCount(1, $schema['fields']);

        $update = new WP_REST_Request();
        $update->set_params(['id' => $field['id']]);
        $update->set_json_params(['label' => 'Dept']);
        $updated = Rest_Students::update_field_definition($update);
        $this->assertSame('Dept', $updated['label']);

        $delete = new WP_REST_Request();
        $delete->set_params(['id' => $field['id']]);
        $deleted = Rest_Students::delete_field_definition($delete);
        $this->assertTrue($deleted['deleted']);
    }

    public function test_import_students_with_row_errors(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_UPLOAD_STUDENTS);

        $request = new WP_REST_Request();
        $request->set_json_params([
            'rows' => [
                ['reg_no' => 'R301', 'name' => 'Valid'],
                ['reg_no' => '', 'name' => 'Missing reg'],
            ],
            'duplicate_policy' => 'skip',
        ]);

        $result = Rest_Students::import_students($request);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['failed']);
        $this->assertNotEmpty($result['error_csv']);
        $this->assertStringContainsString('Registration number is required', $result['error_csv']);
    }

    public function test_import_duplicate_policy_update(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_UPLOAD_STUDENTS);

        $first = new WP_REST_Request();
        $first->set_json_params([
            'rows' => [
                ['reg_no' => 'R400', 'name' => 'Original'],
            ],
            'duplicate_policy' => 'skip',
        ]);
        Rest_Students::import_students($first);

        $second = new WP_REST_Request();
        $second->set_json_params([
            'rows' => [
                ['reg_no' => 'R400', 'name' => 'Updated'],
            ],
            'duplicate_policy' => 'update',
        ]);
        $result = Rest_Students::import_students($second);

        $this->assertSame(1, $result['updated']);

        $student = (new \ProjectReviews\Repositories\StudentRepository($this->wpdb))->find_by_reg_no('R400');
        $this->assertSame('Updated', $student['name']);
    }

    /**
     * @return array{args: array<string, mixed>}
     */
    private function find_route(string $route, string $method): array
    {
        global $pr_test_registered_routes;
        foreach ($pr_test_registered_routes as $registered) {
            if ($registered['route'] !== $route) {
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

        $this->fail("Route {$route} {$method} not registered.");
    }
}
