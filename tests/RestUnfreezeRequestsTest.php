<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Unfreeze_Requests;
use ProjectReviews\Services\MarkService;
use WP_Error;
use WP_REST_Request;

final class RestUnfreezeRequestsTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id;

    private int $review_id;

    private int $panel_id;

    private int $student_id;

    private int $reviewer_user_id = 901;

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
                'ID' => $this->reviewer_user_id,
                'display_name' => 'Dr. Lee',
                'user_email' => 'lee@example.com',
                'user_login' => 'lee',
            ],
        ];

        if (!defined('PR_CAP_MANAGE_SESSIONS')) {
            require_once dirname(__DIR__) . '/includes/capabilities.php';
        }

        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-unfreeze-requests.php';

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);

        $this->session_id = $sessions->create(['title' => 'Capstone 2026', 'status' => SessionRepository::STATUS_ACTIVE]);
        $this->panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($this->panel_id, [
            'name' => 'Dr. Lee',
            'email' => 'lee@example.com',
            'user_id' => $this->reviewer_user_id,
        ]);

        $this->student_id = $students->insert(['reg_no' => 'U901', 'name' => 'Student']);
        $sessions->enrol_student($this->session_id, $this->student_id, $this->panel_id);

        $this->review_id = $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($this->review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
        ]);
        $reviews->set_marking_active($this->review_id, true);

        $service = new MarkService(
            $sessions,
            $reviews,
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb),
            new UnfreezeRequestRepository($this->wpdb)
        );
        foreach ($criteria as $row) {
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [['criterion_id' => (int) $row['id'], 'score' => 8]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }
        $service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->reviewer_user_id
        );

        $repo = new UnfreezeRequestRepository($this->wpdb);
        $repo->create_pending(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->reviewer_user_id,
            'Need to fix incorrect criterion scores'
        );

        Rest_Bootstrap::register_routes();
    }

    public function test_coordinator_list_returns_empty(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');
        $GLOBALS['pr_test_current_user_id'] = $this->coordinator_user_id;

        $request = new WP_REST_Request();
        $request->set_param('status', 'pending');

        $result = Rest_Unfreeze_Requests::list_requests($request);
        $this->assertSame([], $result['requests']);
    }

    public function test_coordinator_grant_returns_use_panel_head_grant(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');
        $GLOBALS['pr_test_current_user_id'] = $this->coordinator_user_id;

        $pending = (new UnfreezeRequestRepository($this->wpdb))->find_pending_for_assignment(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->reviewer_user_id
        );
        $this->assertNotNull($pending);

        $request = new WP_REST_Request();
        $request->set_param('id', (int) ($pending['id'] ?? 0));

        $result = Rest_Unfreeze_Requests::grant_request($request);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('use_panel_head_grant', $result->get_error_code());
    }

    public function test_reviewer_cannot_grant(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $pending = (new UnfreezeRequestRepository($this->wpdb))->find_pending_for_assignment(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->reviewer_user_id
        );

        $request = new WP_REST_Request();
        $request->set_param('id', (int) ($pending['id'] ?? 0));

        $callback = null;
        foreach ($GLOBALS['pr_test_registered_routes'] as $route) {
            $path = (string) ($route['route'] ?? '');
            if (str_contains($path, 'unfreeze-requests') && str_contains($path, 'grant')) {
                $callback = $route['args']['permission_callback'] ?? null;
                break;
            }
        }

        $this->assertIsCallable($callback);
        $allowed = $callback($request);
        $this->assertInstanceOf(WP_Error::class, $allowed);
    }
}
