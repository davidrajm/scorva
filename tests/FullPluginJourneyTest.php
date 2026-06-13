<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Install;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Rest_Binary_Response;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Marks;
use ProjectReviews\Rest_Panel_Reports;
use ProjectReviews\Rest_Progress;
use ProjectReviews\Rest_Reports;
use ProjectReviews\Rest_Reviewer_Unfreeze_Requests;
use ProjectReviews\Rest_Session_Close;
use ProjectReviews\Rest_Sessions;
use ProjectReviews\Rest_Unfreeze_Requests;
use ProjectReviews\Services\AuditService;
use ProjectReviews\Services\FacultyAccountService;
use ProjectReviews\Services\FacultyBridgeService;
use ProjectReviews\Services\MarkService;
use ProjectReviews\Services\PanelHeadService;
use ProjectReviews\Services\PanelReportPdfService;
use ProjectReviews\Services\PanelReportService;
use ProjectReviews\Services\ReportQueryService;
use ProjectReviews\Services\ReviewerProvisionService;
use ProjectReviews\Services\RubricLifecycleService;
use ProjectReviews\Services\SessionCloseService;
use ProjectReviews\Services\SessionPanelReportSettings;
use ProjectReviews\Testing\TestTeardown;
use ProjectReviews\Tests\Support\ScenarioBuilder;
use WP_Error;
use WP_REST_Request;

/**
 * Cross-domain journey tests (FakeWpdb + REST handlers, no HTTP).
 *
 * @group journey
 */
final class FullPluginJourneyTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_users'] = [];
        $GLOBALS['pr_test_user_meta'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_environment_type'] = 'local';

        $this->load_rest_stack();
        Rest_Bootstrap::register_routes();
        Rest_Binary_Response::register();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['pr_test_user_meta'], $GLOBALS['pr_test_user_caps_by_user']);
        $GLOBALS['pr_test_users'] = [];
        parent::tearDown();
    }

    public function test_happy_path_coordinator_setup_through_reports_export(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->with_marks_submitted()
            ->build();

        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);
        RestTestFixtures::set_valid_rest_nonce('journey-progress');

        $progress_request = new WP_REST_Request();
        $progress_request->set_param('session_id', $ctx['session_id']);
        $progress = Rest_Progress::get_progress($progress_request);
        $this->assertNotEmpty($progress['reviews'] ?? []);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $ctx['session_id']);
        $request->set_param('review_id', $ctx['review_ids'][0]);
        $request->set_param('format', 'xlsx');
        $request->set_param('sort_key', 'reg_no');
        $request->set_param('sort_dir', 'asc');

        $xlsx = Rest_Reports::scores_matrix_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $xlsx);
        $body = $this->serve_raw_body($xlsx);
        $this->assertGreaterThan(100, strlen($body));
        $this->assertSame('PK', substr($body, 0, 2));

        $csv_request = new WP_REST_Request();
        $csv_request->set_param('session_id', $ctx['session_id']);
        $csv_request->set_param('review_id', $ctx['review_ids'][0]);
        $csv_request->set_param('format', 'csv');

        $csv = Rest_Reports::panel_roster_download($csv_request);
        $this->assertInstanceOf(\WP_REST_Response::class, $csv);
        $csv_body = $this->serve_raw_body($csv);
        $this->assertGreaterThan(10, strlen($csv_body));
        $this->assertStringContainsString(',', $csv_body);
    }

    public function test_reviewer_assignment_scoping_and_blocked_states(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->build();

        $reviews = new ReviewRepository($this->wpdb);
        $draft_review_id = $reviews->create($ctx['session_id'], ['label' => 'Draft only']);

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            $reviews,
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );

        $blocked = $service->save_marks(
            $ctx['session_id'],
            $draft_review_id,
            $ctx['student_ids'][0],
            $ctx['reviewer_user_ids'][0],
            [['criterion_id' => $ctx['criterion_ids'][0], 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertInstanceOf(WP_Error::class, $blocked);
        $this->assertSame('rubric_not_confirmed', $blocked->get_error_code());

        (new SessionRepository($this->wpdb))->update($ctx['session_id'], [
            'status' => SessionRepository::STATUS_CLOSED,
        ]);
        $closed = $service->save_marks(
            $ctx['session_id'],
            $ctx['review_ids'][0],
            $ctx['student_ids'][0],
            $ctx['reviewer_user_ids'][0],
            [['criterion_id' => $ctx['criterion_ids'][0], 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertInstanceOf(WP_Error::class, $closed);
        $this->assertSame('session_closed', $closed->get_error_code());

        (new SessionRepository($this->wpdb))->update($ctx['session_id'], [
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $unassigned = $service->save_marks(
            $ctx['session_id'],
            $ctx['review_ids'][0],
            $ctx['student_ids'][0],
            99999,
            [['criterion_id' => $ctx['criterion_ids'][0], 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertInstanceOf(WP_Error::class, $unassigned);
        $this->assertSame('not_assigned', $unassigned->get_error_code());
    }

    public function test_session_close_and_reopen_lifecycle(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->build();

        $close = (new SessionCloseService($this->wpdb))->close($ctx['session_id'], false, 1);
        $this->assertTrue($close['ok']);
        $session = (new SessionRepository($this->wpdb))->find_by_id($ctx['session_id']);
        $this->assertSame(SessionRepository::STATUS_CLOSED, $session['status'] ?? '');

        RestTestFixtures::login_with_cap(PR_CAP_CLOSE_SESSION);
        RestTestFixtures::set_valid_rest_nonce('journey-reopen');
        $request = new WP_REST_Request('POST', '/scorva/v1/sessions/' . $ctx['session_id'] . '/reopen');
        $request->set_param('id', $ctx['session_id']);
        $reopened = Rest_Session_Close::reopen_session($request);
        $this->assertIsArray($reopened);
        $this->assertSame(SessionRepository::STATUS_ACTIVE, $reopened['session']['status'] ?? '');

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );
        $save = $service->save_marks(
            $ctx['session_id'],
            $ctx['review_ids'][0],
            $ctx['student_ids'][0],
            $ctx['reviewer_user_ids'][0],
            [['criterion_id' => $ctx['criterion_ids'][0], 'score' => 6]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($save);
    }

    public function test_rubric_reconfirm_keep_flag_and_clear(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->with_marks_submitted()
            ->build();

        $review_id = $ctx['review_ids'][0];
        $reviews = new ReviewRepository($this->wpdb);
        $lifecycle = new RubricLifecycleService($reviews);

        $lifecycle->unlock($review_id);
        $reviews->replace_criteria($review_id, [
            ['label' => 'Updated criterion', 'max_marks' => 15],
        ]);

        $result = $lifecycle->confirm($review_id, 'keep_flag');
        $this->assertTrue($result['confirmed']);
        $this->assertGreaterThan(0, $result['marks_flagged']);

        $marks = $reviews->list_marks_for_review($review_id);
        $this->assertNotEmpty($marks);
        $this->assertSame(1, (int) ($marks[0]['flagged'] ?? 0));
    }

    public function test_unfreeze_request_flows(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->build();

        $review_id = $ctx['review_ids'][0];
        $panel_id = $ctx['panel_id'];
        $reviewer_id = $ctx['reviewer_user_ids'][1];
        $head_id = $ctx['reviewer_user_ids'][0];

        $this->assign_panel_head($ctx['session_id'], $review_id, $panel_id, $head_id);

        $marks = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb),
            new UnfreezeRequestRepository($this->wpdb)
        );
        foreach ((new ReviewRepository($this->wpdb))->list_criteria($review_id) as $criterion) {
            $marks->save_marks(
                $ctx['session_id'],
                $review_id,
                $ctx['student_ids'][0],
                $reviewer_id,
                [['criterion_id' => (int) $criterion['id'], 'score' => 8]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }
        $marks->freeze_review_marks($ctx['session_id'], $review_id, $panel_id, $reviewer_id);

        $pending = (new UnfreezeRequestRepository($this->wpdb))->create_pending(
            $ctx['session_id'],
            $review_id,
            $panel_id,
            $reviewer_id,
            'Need to correct scores after freeze'
        );

        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('journey-unfreeze-coord');
        $GLOBALS['pr_test_current_user_id'] = 9001;
        $grant = Rest_Unfreeze_Requests::grant_request($this->id_request((int) $pending['id']));
        $this->assertInstanceOf(WP_Error::class, $grant);
        $this->assertSame('use_panel_head_grant', $grant->get_error_code());

        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        RestTestFixtures::set_valid_rest_nonce('journey-unfreeze-head');
        $GLOBALS['pr_test_current_user_id'] = $head_id;
        $granted = Rest_Reviewer_Unfreeze_Requests::grant_request($this->id_request((int) $pending['id']));
        $this->assertIsArray($granted);
        $this->assertTrue($granted['granted']);
    }

    public function test_faculty_pool_bulk_invite(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->build();

        RestTestFixtures::login_with_cap(PR_CAP_ASSIGN_REVIEWERS);
        RestTestFixtures::set_valid_rest_nonce('journey-faculty');

        $import = \ProjectReviews\Rest_Faculty_Accounts::import_accounts(
            $this->json_request([
                'rows' => [
                    ['empId' => 'EMP200', 'name' => 'Faculty Journey', 'email' => 'faculty-journey@example.com'],
                ],
                'duplicate_policy' => 'skip',
            ])
        );
        $this->assertIsArray($import);
        $this->assertSame(1, $import['imported']);

        $invite = (new ReviewerProvisionService(
            new SessionRepository($this->wpdb),
            new PanelRepository($this->wpdb)
        ))->invite_all_session_reviewers($ctx['session_id']);
        $this->assertIsArray($invite);
        $this->assertGreaterThanOrEqual(1, $invite['sent'] ?? 0);
    }

    public function test_panel_head_pdf_and_freeze(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->build();

        $head_id = $ctx['reviewer_user_ids'][0];
        $review_id = $ctx['review_ids'][0];
        $this->freeze_all_reviewer_marks($ctx, $review_id);
        $this->assign_panel_head($ctx['session_id'], $review_id, $ctx['panel_id'], $head_id);

        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $head_id;

        $freeze_request = new WP_REST_Request();
        $freeze_request->set_param('session_id', $ctx['session_id']);
        $freeze_request->set_param('review_id', $ctx['review_ids'][0]);
        $freeze_request->set_param('panel_id', $ctx['panel_id']);
        $frozen = Rest_Panel_Reports::freeze_panel($freeze_request);
        $this->assertIsArray($frozen);
        $this->assertTrue($frozen['frozen']);

        $this->assertTrue(
            (new PanelFreezeRepository($this->wpdb))->is_frozen($ctx['review_ids'][0], $ctx['panel_id'])
        );

        SessionPanelReportSettings::freeze_settings($ctx['session_id']);
        $pdf = (new PanelReportPdfService())->render(
            (new PanelReportService())->get_report(
                $ctx['session_id'],
                $ctx['review_ids'][0],
                $ctx['panel_id'],
                $head_id
            )
        );
        if ($pdf instanceof WP_Error && $pdf->get_error_code() === 'pdf_unavailable') {
            $this->markTestSkipped($pdf->get_error_message());
        }
        $this->assertIsArray($pdf);
        $this->assertStringStartsWith('%PDF', $pdf['pdf']);
    }

    public function test_audit_override_requires_reason(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->with_marks_submitted()
            ->build();

        $marks_repo = new MarkRepository($this->wpdb);
        $mark_rows = $marks_repo->list_for_student_review(
            $ctx['session_id'],
            $ctx['review_ids'][0],
            $ctx['student_ids'][0],
            $ctx['reviewer_user_ids'][0]
        );
        $this->assertNotEmpty($mark_rows);
        $mark_id = (int) ($mark_rows[0]['id'] ?? 0);

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );

        $short = $service->validate_override_reason('short');
        $this->assertFalse($short['ok']);
        $this->assertSame('reason_too_short', $short['error']);

        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_user_caps_by_user'] = [1 => [PR_CAP_OVERRIDE_MARKS => true]];
        $override = $service->override_mark($mark_id, 8.0, 'Coordinator corrected consensus error', 1);
        $this->assertTrue($override['ok'] ?? false, (string) ($override['error'] ?? ''));

        $audit = (new AuditService($this->wpdb))->list_for_session($ctx['session_id']);
        $actions = array_column($audit['items'] ?? [], 'action');
        $this->assertContains('mark_override', $actions);
    }

    public function test_plugin_lifecycle_deactivate_preserves_data(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)->build_configured_project()->build();
        $this->assertGreaterThan(0, $ctx['session_id']);

        require_once dirname(__DIR__) . '/includes/class-plugin.php';
        $this->wpdb->queries = [];
        \ProjectReviews\Plugin::deactivate();

        $drop_queries = array_filter(
            $this->wpdb->queries,
            static fn (string $sql): bool => stripos($sql, 'DROP TABLE') !== false
                || stripos($sql, 'DROP VIEW') !== false
        );
        $this->assertSame([], array_values($drop_queries));

        $session = (new SessionRepository($this->wpdb))->find_by_id($ctx['session_id']);
        $this->assertIsArray($session);
        $this->assertSame('Journey Project', $session['title'] ?? '');
    }

    public function test_teardown_truncate_leaves_schema(): void
    {
        $ctx = ScenarioBuilder::fresh($this->wpdb)->build_configured_project()->build();
        $tables = Install::get_pr_table_names($this->wpdb->prefix);

        $this->wpdb->query(Install::rubric_scores_view_ddl($this->wpdb->prefix));
        TestTeardown::truncate_tables($this->wpdb);

        foreach ($tables as $table) {
            $this->assertNotNull($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'"));
            $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $this->assertSame(0, $count, "Table should be empty after truncate: {$table}");
        }

        $view = $this->wpdb->prefix . 'pr_rubric_scores';
        $this->assertTrue($this->wpdb->has_view($view));
        unset($ctx);
    }

    /**
     * @param array{session_id: int, panel_id: int, student_ids: list<int>, reviewer_user_ids: list<int>} $ctx
     */
    private function freeze_all_reviewer_marks(array $ctx, int $review_id): void
    {
        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );
        $reviews = new ReviewRepository($this->wpdb);
        $criteria = $reviews->list_criteria($review_id);

        foreach ($ctx['reviewer_user_ids'] as $reviewer_user_id) {
            foreach ($ctx['student_ids'] as $student_id) {
                $payload = array_map(
                    static fn (array $row): array => [
                        'criterion_id' => (int) ($row['id'] ?? 0),
                        'score' => 7,
                    ],
                    $criteria
                );
                $service->save_marks(
                    $ctx['session_id'],
                    $review_id,
                    $student_id,
                    $reviewer_user_id,
                    $payload,
                    MarkRepository::STATUS_DRAFT,
                    ReviewAssignmentRepository::ATTENDANCE_PRESENT
                );
            }
            $freeze = $service->freeze_review_marks(
                $ctx['session_id'],
                $review_id,
                $ctx['panel_id'],
                $reviewer_user_id
            );
            $this->assertIsArray($freeze, $freeze instanceof WP_Error ? $freeze->get_error_message() : '');
        }
    }

    private function load_rest_stack(): void
    {
        RestTestFixtures::reset();
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-binary-response.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-sessions.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-students.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reviews.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-marks.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-scores.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-progress.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reports.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-session-close.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-unfreeze-requests.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-panel-unfreeze-requests.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reviewer-unfreeze-requests.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-panel-reports.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-faculty-accounts.php';
        require_once dirname(__DIR__) . '/includes/services/SessionCloseService.php';
        require_once dirname(__DIR__) . '/includes/services/MarkService.php';
        require_once dirname(__DIR__) . '/includes/services/ScoreService.php';
        require_once dirname(__DIR__) . '/includes/services/ExportService.php';
        require_once dirname(__DIR__) . '/includes/services/ReportQueryService.php';
        require_once dirname(__DIR__) . '/includes/services/ReportsViewService.php';
        require_once dirname(__DIR__) . '/includes/services/RubricLifecycleService.php';
        require_once dirname(__DIR__) . '/includes/services/ReviewerProvisionService.php';
        require_once dirname(__DIR__) . '/includes/services/AuditService.php';
        require_once dirname(__DIR__) . '/includes/services/PanelHeadService.php';
        require_once dirname(__DIR__) . '/includes/services/PanelReportService.php';
        require_once dirname(__DIR__) . '/includes/services/PanelReportPdfService.php';
        require_once dirname(__DIR__) . '/includes/services/SessionPanelReportSettings.php';
        require_once dirname(__DIR__) . '/includes/repositories/StudentRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/SessionRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/PanelRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/ReviewRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/MarkRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/ReviewAssignmentRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/UnfreezeRequestRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/PanelFreezeRepository.php';
        Install::ensure_schema_patches();
    }

    private function assign_panel_head(int $session_id, int $review_id, int $panel_id, int $head_user_id): void
    {
        $panels = new PanelRepository($this->wpdb);
        foreach ($panels->list_reviewers($panel_id) as $row) {
            if ((int) ($row['user_id'] ?? 0) === $head_user_id) {
                (new PanelHeadService($panels))->set_session_panel_head((int) $row['id'], true);
                break;
            }
        }
        (new ReviewAssignmentRepository($this->wpdb))->seed_from_session_defaults($review_id, $session_id);
    }

    private function id_request(int $id): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_param('id', $id);

        return $request;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json_request(array $body): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_header('Content-Type', 'application/json');
        $request->set_json_params($body);

        return $request;
    }

    private function serve_raw_body(\WP_REST_Response $response): string
    {
        ob_start();
        $served = apply_filters(
            'rest_pre_serve_request',
            false,
            $response,
            new WP_REST_Request(),
            new \WP_REST_Server()
        );
        $body = (string) ob_get_clean();
        $this->assertTrue($served);

        return $body;
    }
}
