<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Reviewer_Assignments;
use ProjectReviews\Services\MarkService;
use WP_Error;
use WP_REST_Request;

final class RestReviewerAssignmentsTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id;

    private int $review_id;

    private int $panel_id;

    private int $student_id;

    private int $reviewer_user_id = 801;

    /** @var list<int> */
    private array $criterion_ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        if (!defined('PR_CAP_ENTER_MARKS')) {
            require_once dirname(__DIR__) . '/includes/capabilities.php';
        }

        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reviewer-assignments.php';

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);

        $this->session_id = $sessions->create(['title' => 'Reviewer grid', 'status' => SessionRepository::STATUS_ACTIVE]);
        $this->panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($this->panel_id, [
            'name' => 'Reviewer',
            'email' => 'rev801@example.com',
            'user_id' => $this->reviewer_user_id,
        ]);

        $this->student_id = $students->insert(['reg_no' => 'R801', 'name' => 'Student']);
        $sessions->enrol_student($this->session_id, $this->student_id, $this->panel_id);

        $this->review_id = $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($this->review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
            ['label' => 'Design', 'max_marks' => 5],
        ]);
        $this->criterion_ids = array_map(static fn (array $row): int => (int) $row['id'], $criteria);
        $reviews->set_marking_active($this->review_id, true);

        Rest_Bootstrap::register_routes();
    }

    public function test_list_students_includes_criteria_and_scores(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            null,
            new MarkRepository($this->wpdb)
        );
        $reviews = new ReviewRepository($this->wpdb);
        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $max = (float) ($row['max_marks'] ?? 10);
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [
                    [
                        'criterion_id' => (int) $row['id'],
                        'score' => min(5.0 + (int) ($row['sort_order'] ?? 0), $max),
                    ],
                ],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $this->review_id);
        $request->set_param('panel_id', $this->panel_id);

        $result = Rest_Reviewer_Assignments::list_students($request);
        $this->assertIsArray($result);
        $this->assertCount(2, $result['criteria']);
        $this->assertFalse($result['review_frozen']);
        $this->assertCount(1, $result['students']);
        $student = $result['students'][0];
        $this->assertSame('draft', $student['mark_status']);
        $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_PRESENT, $student['attendance_status']);
        $scores = (array) $student['scores'];
        $this->assertSame(5.0, $scores[(string) $this->criterion_ids[0]]);
    }

    public function test_freeze_marks_endpoint(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            null,
            new MarkRepository($this->wpdb)
        );
        $reviews = new ReviewRepository( $this->wpdb );
        foreach ( $reviews->list_criteria( $this->review_id ) as $row ) {
            $max = (float) ( $row['max_marks'] ?? 10 );
            $saved = $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [
                    [
                        'criterion_id' => (int) $row['id'],
                        'score' => min( 8.0, $max ),
                    ],
                ],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
            $this->assertIsArray( $saved );
        }

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $this->review_id);
        $request->set_json_params(['panel_id' => $this->panel_id]);

        $result = Rest_Reviewer_Assignments::freeze_marks($request);
        $this->assertIsArray($result);
        $this->assertTrue($result['frozen']);

        $list = new WP_REST_Request();
        $list->set_param('session_id', $this->session_id);
        $list->set_param('review_id', $this->review_id);
        $list->set_param('panel_id', $this->panel_id);
        $students = Rest_Reviewer_Assignments::list_students($list);
        $this->assertTrue($students['review_frozen']);
        $this->assertSame('frozen', $students['students'][0]['mark_status']);
    }

    public function test_request_unfreeze_and_list_status(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            null,
            new MarkRepository($this->wpdb)
        );
        $reviews = new ReviewRepository($this->wpdb);
        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $max = (float) ($row['max_marks'] ?? 10);
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [['criterion_id' => (int) $row['id'], 'score' => min(8.0, $max)]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }

        $freeze = new WP_REST_Request();
        $freeze->set_param('session_id', $this->session_id);
        $freeze->set_param('review_id', $this->review_id);
        $freeze->set_json_params(['panel_id' => $this->panel_id]);
        Rest_Reviewer_Assignments::freeze_marks($freeze);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $this->review_id);
        $request->set_json_params([
            'panel_id' => $this->panel_id,
            'reason' => 'Entered wrong total for one student',
        ]);

        $created = Rest_Reviewer_Assignments::request_unfreeze($request);
        $this->assertIsArray($created);
        $this->assertSame('pending', $created['status']);

        $again = Rest_Reviewer_Assignments::request_unfreeze($request);
        $this->assertSame($created['id'], $again['id']);

        $list = new WP_REST_Request();
        $list->set_param('session_id', $this->session_id);
        $list->set_param('review_id', $this->review_id);
        $list->set_param('panel_id', $this->panel_id);
        $students = Rest_Reviewer_Assignments::list_students($list);
        $this->assertTrue($students['review_frozen']);
        $this->assertSame('pending', $students['unfreeze_request_status']);
    }

    public function test_list_assignments_includes_co_reviewers_excluding_self(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $solo = Rest_Reviewer_Assignments::list_assignments(new WP_REST_Request());
        $this->assertIsArray($solo);
        $this->assertCount(1, $solo['assignments']);
        $this->assertSame([], $solo['assignments'][0]['co_reviewers']);

        $co_reviewer_user_id = 802;
        $panels = new PanelRepository($this->wpdb);
        $panels->add_reviewer($this->panel_id, [
            'name' => 'Co Reviewer',
            'email' => 'rev802@example.com',
            'user_id' => $co_reviewer_user_id,
        ]);

        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $assignments->upsert_panel_reviewer(
            $this->review_id,
            $this->panel_id,
            $co_reviewer_user_id
        );

        $with_co = Rest_Reviewer_Assignments::list_assignments(new WP_REST_Request());
        $this->assertCount(1, $with_co['assignments']);
        $co_reviewers = $with_co['assignments'][0]['co_reviewers'];
        $this->assertCount(1, $co_reviewers);
        $this->assertSame('Co Reviewer', $co_reviewers[0]['name']);
        $this->assertSame($co_reviewer_user_id, $co_reviewers[0]['user_id']);
    }

    public function test_list_assignments_includes_draft_session_as_blocked(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $sessions = new SessionRepository($this->wpdb);
        $sessions->update($this->session_id, ['status' => SessionRepository::STATUS_DRAFT]);

        $result = Rest_Reviewer_Assignments::list_assignments(new WP_REST_Request());
        $this->assertCount(1, $result['assignments']);
        $this->assertFalse($result['assignments'][0]['markable']);
        $this->assertSame('session_not_active', $result['assignments'][0]['blocked_reason']);
    }

    public function test_list_assignments_syncs_session_co_reviewer_without_manual_upsert(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $co_reviewer_user_id = 803;
        $panels = new PanelRepository($this->wpdb);
        $panels->add_reviewer($this->panel_id, [
            'name' => '',
            'email' => 'ra-2@vit.ac.in',
            'user_id' => $co_reviewer_user_id,
        ]);

        $result = Rest_Reviewer_Assignments::list_assignments(new WP_REST_Request());
        $co_reviewers = $result['assignments'][0]['co_reviewers'];
        $this->assertCount(1, $co_reviewers);
        $this->assertSame('ra-2@vit.ac.in', $co_reviewers[0]['name']);
        $this->assertSame($co_reviewer_user_id, $co_reviewers[0]['user_id']);
    }

    public function test_freeze_incomplete_returns_error(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            null,
            new MarkRepository($this->wpdb)
        );
        $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => 8]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $this->review_id);
        $request->set_json_params(['panel_id' => $this->panel_id]);

        $result = Rest_Reviewer_Assignments::freeze_marks($request);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('incomplete_marks', $result->get_error_code());
        $this->assertStringContainsString('Design', $result->message);
        $this->assertStringContainsString('Student', $result->message);
    }
}
