<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Install;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\PanelUnfreezeRequestRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Panel_Reports;
use ProjectReviews\Rest_Panel_Unfreeze_Requests;
use ProjectReviews\Services\MarkService;
use ProjectReviews\Services\PanelHeadService;
use WP_Error;
use WP_REST_Request;

final class RestPanelUnfreezeRequestsTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id;

    private int $review_id;

    private int $panel_id;

    private int $head_user_id = 901;

    private int $coordinator_user_id = 902;

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_current_user_id'] = $this->coordinator_user_id;
        $GLOBALS['pr_test_users'] = [
            (object) [
                'ID' => $this->head_user_id,
                'display_name' => 'Dr. Head',
                'user_email' => 'head@example.com',
                'user_login' => 'head',
            ],
        ];

        if (!defined('PR_CAP_MANAGE_SESSIONS')) {
            require_once dirname(__DIR__) . '/includes/capabilities.php';
        }

        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-panel-reports.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-panel-unfreeze-requests.php';

        Install::ensure_schema_patches();

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);

        $this->session_id = $sessions->create([
            'title' => 'Capstone 2026',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $this->panel_id = $panels->create($this->session_id, 'Panel A');
        $head_id = $panels->add_reviewer($this->panel_id, [
            'name' => 'Dr. Head',
            'email' => 'head@example.com',
            'user_id' => $this->head_user_id,
        ]);
        (new PanelHeadService($panels))->set_session_panel_head($head_id, true);

        $this->review_id = $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $reviews->set_marking_active($this->review_id, true);
        (new ReviewAssignmentRepository($this->wpdb))->seed_from_session_defaults($this->review_id, $this->session_id);

        (new PanelFreezeRepository($this->wpdb))->freeze($this->review_id, $this->panel_id, $this->head_user_id);

        Rest_Bootstrap::register_routes();
    }

    public function test_panel_head_can_request_panel_unfreeze(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->head_user_id;

        $request = new WP_REST_Request('POST');
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $this->review_id);
        $request->set_param('panel_id', $this->panel_id);
        $request->set_json_params(['reason' => 'Frozen by mistake after PDF review.']);

        $result = Rest_Panel_Reports::request_panel_unfreeze($request);
        $this->assertIsArray($result);
        $this->assertSame('pending', $result['status']);
        $this->assertGreaterThan(0, $result['id']);
    }

    public function test_coordinator_lists_and_grants_panel_unfreeze(): void
    {
        $repo = new PanelUnfreezeRequestRepository($this->wpdb);
        $row = $repo->create_pending(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id,
            'Need to reopen panel'
        );

        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');
        $GLOBALS['pr_test_current_user_id'] = $this->coordinator_user_id;

        $list = Rest_Panel_Unfreeze_Requests::list_requests(new WP_REST_Request());
        $this->assertCount(1, $list['requests']);
        $this->assertSame('Panel A', $list['requests'][0]['panel_name']);

        $grant_request = new WP_REST_Request();
        $grant_request->set_param('id', (int) ($row['id'] ?? 0));
        $grant = Rest_Panel_Unfreeze_Requests::grant_request($grant_request);
        $this->assertIsArray($grant);
        $this->assertTrue($grant['granted']);
        $this->assertTrue($grant['panel_unfrozen']);

        $freezes = new PanelFreezeRepository($this->wpdb);
        $this->assertFalse($freezes->is_frozen($this->review_id, $this->panel_id));
    }

    public function test_panel_grant_does_not_revert_marks(): void
    {
        $freezes = new PanelFreezeRepository($this->wpdb);
        $freezes->unfreeze($this->review_id, $this->panel_id);

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'U1', 'name' => 'Student']);
        (new SessionRepository($this->wpdb))->enrol_student($this->session_id, $student_id, $this->panel_id);

        $reviews = new ReviewRepository($this->wpdb);
        $criteria = $reviews->replace_criteria($this->review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
        ]);
        $reviewer_id = 903;
        $panels = new PanelRepository($this->wpdb);
        $panels->add_reviewer($this->panel_id, [
            'name' => 'Reviewer',
            'email' => 'rev@example.com',
            'user_id' => $reviewer_id,
        ]);
        (new ReviewAssignmentRepository($this->wpdb))->seed_from_session_defaults($this->review_id, $this->session_id);

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            $reviews,
            new ReviewAssignmentRepository($this->wpdb),
            new \ProjectReviews\Repositories\MarkRepository($this->wpdb)
        );
        foreach ($criteria as $row) {
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $student_id,
                $reviewer_id,
                [['criterion_id' => (int) $row['id'], 'score' => 8]],
                \ProjectReviews\Repositories\MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }
        $freeze_result = $service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $reviewer_id
        );
        $this->assertIsArray($freeze_result);

        $freezes->freeze($this->review_id, $this->panel_id, $this->head_user_id);

        $pending = (new PanelUnfreezeRequestRepository($this->wpdb))->create_pending(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id,
            'Reopen panel'
        );

        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        $GLOBALS['pr_test_current_user_id'] = $this->coordinator_user_id;

        $grant_request = new WP_REST_Request();
        $grant_request->set_param('id', (int) ($pending['id'] ?? 0));
        Rest_Panel_Unfreeze_Requests::grant_request($grant_request);

        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);
        $this->assertFalse($freezes->is_frozen($this->review_id, $this->panel_id));
        $this->assertTrue(
            $marks->is_student_frozen_for_reviewer(
                $this->session_id,
                $this->review_id,
                $student_id,
                $reviewer_id,
                count($criteria)
            )
        );
    }

    public function test_panel_head_cannot_grant_coordinator_endpoint(): void
    {
        $row = (new PanelUnfreezeRequestRepository($this->wpdb))->create_pending(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id,
            'Reopen'
        );

        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->head_user_id;

        $callback = null;
        foreach ($GLOBALS['pr_test_registered_routes'] as $route) {
            $path = (string) ($route['route'] ?? '');
            if (str_contains($path, 'panel-unfreeze-requests') && str_contains($path, 'grant')) {
                $callback = $route['args']['permission_callback'] ?? null;
                break;
            }
        }

        $this->assertIsCallable($callback);
        $request = new WP_REST_Request();
        $request->set_param('id', (int) ($row['id'] ?? 0));
        $allowed = $callback($request);
        $this->assertInstanceOf(WP_Error::class, $allowed);
    }
}
