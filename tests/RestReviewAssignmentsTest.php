<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Review_Assignments;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use WP_REST_Request;

final class RestReviewAssignmentsTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-review-assignments.php';

        Rest_Bootstrap::register_routes();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_update_students_persists_panel_and_project_title(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_PANELS);

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Assignment title']);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');
        $student_id = $students->insert(['reg_no' => 'A99', 'name' => 'Assignee']);
        $sessions->enrol_student($session_id, $student_id, $panel_a, 'Default title');
        $review_id = $reviews->create($session_id, ['label' => 'Review 1']);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $session_id);
        $request->set_param('review_id', $review_id);
        $request->set_json_params([
            'students' => [
                [
                    'student_id' => $student_id,
                    'panel_id' => $panel_b,
                    'project_title' => 'Review-only title',
                ],
            ],
        ]);

        $result = Rest_Review_Assignments::update_students($request);
        $this->assertIsArray($result);
        $this->assertSame('Review-only title', $result['students'][0]['project_title']);
        $this->assertSame($panel_b, $result['students'][0]['panel_id']);
    }
}
