<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Backup;
use ProjectReviews\Rest_Binary_Response;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Tests\Support\ScenarioBuilder;
use WP_Error;
use WP_REST_Request;

final class RestBackupTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/tests/RestAuthTest.php';
        RestTestFixtures::reset();
        $GLOBALS['pr_test_transients'] = [];
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/Install.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-binary-response.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/repositories/SessionRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/ReviewRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/StudentRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/PanelRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/MarkRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/ReviewAssignmentRepository.php';
        require_once dirname(__DIR__) . '/includes/services/ExportService.php';
        require_once dirname(__DIR__) . '/includes/services/ScoreService.php';
        require_once dirname(__DIR__) . '/includes/services/MarkService.php';
        require_once dirname(__DIR__) . '/includes/services/ReportsViewService.php';
        require_once dirname(__DIR__) . '/includes/services/BackupService.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-backup.php';

        Rest_Bootstrap::register_routes();
        Rest_Binary_Response::register();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['pr_test_transients']);
        parent::tearDown();
    }

    public function test_authorized_project_backup_returns_zip_magic(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->build();

        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $request = new WP_REST_Request();
        $request->set_param('id', $ctx['session_id']);

        $response = Rest_Backup::download_project($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $body = $this->serve_raw_body($response);
        $this->assertSame('PK', substr($body, 0, 2));
    }

    public function test_unauthorized_backup_returns_403(): void
    {
        RestTestFixtures::login_without_caps();

        $permission = \ProjectReviews\Rest_Auth::require_cap(PR_CAP_MANAGE_SETTINGS);
        $result = $permission();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
        $this->assertSame(403, $result->get_error_data()['status'] ?? null);
    }

    public function test_throttle_returns_429_on_second_rapid_call(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        ScenarioBuilder::fresh($this->wpdb)->build_configured_project()->build();
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SETTINGS);

        $request = new WP_REST_Request();
        $first = Rest_Backup::download_full($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $first);

        $second = Rest_Backup::download_full($request);
        $this->assertInstanceOf(WP_Error::class, $second);
        $this->assertSame('pr_backup_throttled', $second->get_error_code());
        $this->assertSame(429, $second->get_error_data()['status'] ?? null);
    }

    private function serve_raw_body(\WP_REST_Response $response): string
    {
        $data = $response->get_data();

        return is_string($data) ? $data : '';
    }
}
