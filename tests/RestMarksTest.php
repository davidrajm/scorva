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
use ProjectReviews\Rest_Marks;
use WP_Error;
use WP_REST_Request;

final class RestMarksTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id;

    private int $review_id;

    private int $student_id;

    private int $criterion_id;

    private int $reviewer_user_id = 701;

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

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-marks.php';

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);

        $this->session_id = $sessions->create(['title' => 'REST marks', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($panel_id, [
            'name' => 'Reviewer',
            'email' => 'rev@example.com',
            'user_id' => $this->reviewer_user_id,
        ]);

        $this->student_id = $students->insert(['reg_no' => 'R701', 'name' => 'Student']);
        $sessions->enrol_student($this->session_id, $this->student_id, $panel_id);

        $this->review_id = $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($this->review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
        ]);
        $this->criterion_id = (int) $criteria[0]['id'];
        $reviews->set_marking_active($this->review_id, true);

        Rest_Bootstrap::register_routes();
    }

    public function test_get_and_post_marks_for_assigned_student(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $post = new WP_REST_Request();
        $post->set_param('session_id', $this->session_id);
        $post->set_param('review_id', $this->review_id);
        $post->set_param('student_id', $this->student_id);
        $post->set_json_params([
            'status' => MarkRepository::STATUS_DRAFT,
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_PRESENT,
            'criteria' => [
                ['criterion_id' => $this->criterion_id, 'score' => 7],
            ],
        ]);

        $saved = Rest_Marks::save_marks($post);
        $this->assertIsArray($saved);
        $this->assertCount(1, $saved['marks']);

        $get = new WP_REST_Request();
        $get->set_param('session_id', $this->session_id);
        $get->set_param('review_id', $this->review_id);
        $get->set_param('student_id', $this->student_id);

        $marks = Rest_Marks::get_marks($get);
        $this->assertIsArray($marks);
        $this->assertCount(1, $marks['marks']);
        $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_PRESENT, $marks['attendance_status']);
    }

    public function test_post_marks_without_attendance_returns_attendance_required(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $post = new WP_REST_Request();
        $post->set_param('session_id', $this->session_id);
        $post->set_param('review_id', $this->review_id);
        $post->set_param('student_id', $this->student_id);
        $post->set_json_params([
            'status' => MarkRepository::STATUS_DRAFT,
            'criteria' => [
                ['criterion_id' => $this->criterion_id, 'score' => 7],
            ],
        ]);

        $result = Rest_Marks::save_marks($post);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('attendance_required', $result->get_error_code());
    }

    public function test_post_marks_returns_attendance_conflict_shape(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);
        $reviewer_two = 702;
        $panels->add_reviewer($panel_id, [
            'name' => 'Second Reviewer',
            'email' => 'r2@example.com',
            'user_id' => $reviewer_two,
        ]);
        $assignments->upsert_panel_reviewer($this->review_id, $panel_id, $reviewer_two, 1.0, false);

        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $first = new WP_REST_Request();
        $first->set_param('session_id', $this->session_id);
        $first->set_param('review_id', $this->review_id);
        $first->set_param('student_id', $this->student_id);
        $first->set_json_params([
            'status' => MarkRepository::STATUS_DRAFT,
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_PRESENT,
            'criteria' => [
                ['criterion_id' => $this->criterion_id, 'score' => 7],
            ],
        ]);
        $this->assertIsArray(Rest_Marks::save_marks($first));

        $GLOBALS['pr_test_current_user_id'] = $reviewer_two;
        $second = new WP_REST_Request();
        $second->set_param('session_id', $this->session_id);
        $second->set_param('review_id', $this->review_id);
        $second->set_param('student_id', $this->student_id);
        $second->set_json_params([
            'status' => MarkRepository::STATUS_DRAFT,
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'criteria' => [],
        ]);

        $result = Rest_Marks::save_marks($second);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('attendance_conflict', $result->get_error_code());
        $data = $result->get_error_data();
        $this->assertIsArray($data['conflicts'] ?? null);
        $this->assertNotEmpty($data['conflicts']);
        $this->assertArrayHasKey('reviewer_user_id', $data['conflicts'][0]);
        $this->assertArrayHasKey('reviewer_name', $data['conflicts'][0]);
        $this->assertArrayHasKey('attendance_status', $data['conflicts'][0]);
    }

    public function test_post_marks_for_unassigned_student_returns_not_assigned(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $other_student = (new StudentRepository($this->wpdb))->insert(['reg_no' => 'R999', 'name' => 'Other']);

        $post = new WP_REST_Request();
        $post->set_param('session_id', $this->session_id);
        $post->set_param('review_id', $this->review_id);
        $post->set_param('student_id', $other_student);
        $post->set_json_params([
            'status' => MarkRepository::STATUS_DRAFT,
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_PRESENT,
            'criteria' => [
                ['criterion_id' => $this->criterion_id, 'score' => 7],
            ],
        ]);

        $result = Rest_Marks::save_marks($post);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_assigned', $result->get_error_code());
    }

    public function test_put_correct_attendance_coordinator_success(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');

        $put = new WP_REST_Request();
        $put->set_param('session_id', $this->session_id);
        $put->set_param('review_id', $this->review_id);
        $put->set_param('student_id', $this->student_id);
        $put->set_json_params([
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'reason' => 'Verified with panel chair: student was ill.',
        ]);

        $result = Rest_Marks::correct_attendance($put);
        $this->assertIsArray($result);
        $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_ABSENT, $result['attendance_status']);
        $this->assertSame($this->review_id, $result['review_id']);
        $this->assertSame($this->student_id, $result['student_id']);
        $this->assertGreaterThan(0, $result['reviewers_updated']);
    }

    public function test_put_correct_attendance_reviewer_forbidden(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');

        $put = new WP_REST_Request();
        $put->set_param('session_id', $this->session_id);
        $put->set_param('review_id', $this->review_id);
        $put->set_param('student_id', $this->student_id);
        $put->set_json_params([
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'reason' => 'Should not be allowed for reviewers.',
        ]);

        $callback = null;
        foreach ($GLOBALS['pr_test_registered_routes'] as $route) {
            $path = (string) ($route['route'] ?? '');
            if (str_contains($path, '/attendance') && !str_contains($path, 'attendance_status')) {
                $callback = $route['args']['permission_callback'] ?? null;
                break;
            }
        }

        $this->assertIsCallable($callback);
        $allowed = $callback($put);
        $this->assertInstanceOf(WP_Error::class, $allowed);
        $this->assertSame(403, $allowed->get_error_data()['status'] ?? null);
    }

    public function test_put_correct_attendance_reason_required(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');

        $put = new WP_REST_Request();
        $put->set_param('session_id', $this->session_id);
        $put->set_param('review_id', $this->review_id);
        $put->set_param('student_id', $this->student_id);
        $put->set_json_params([
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'reason' => 'short',
        ]);

        $result = Rest_Marks::correct_attendance($put);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('reason_too_short', $result->get_error_code());
    }

    public function test_put_correct_attendance_blocked_when_review_locked(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');

        $reviews = new ReviewRepository($this->wpdb);
        $reviews->set_coordinator_marks_locked($this->review_id, true);

        $put = new WP_REST_Request();
        $put->set_param('session_id', $this->session_id);
        $put->set_param('review_id', $this->review_id);
        $put->set_param('student_id', $this->student_id);
        $put->set_json_params([
            'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'reason' => 'Should be blocked while review is frozen.',
        ]);

        $result = Rest_Marks::correct_attendance($put);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('coordinator_marks_locked', $result->get_error_code());
    }

    public function test_post_override_coordinator_success(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_OVERRIDE_MARKS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');

        $marks = new MarkRepository($this->wpdb);
        $mark_id = $marks->upsert(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $this->criterion_id,
            5.0,
            MarkRepository::STATUS_SUBMITTED
        );

        $post = new WP_REST_Request();
        $post->set_param('id', $mark_id);
        $post->set_json_params([
            'score' => 7.5,
            'reason' => 'Coordinator corrected panel consensus error',
        ]);

        $result = Rest_Marks::override_mark($post);
        $this->assertIsArray($result);
        $this->assertTrue($result['mark']['coordinator_overridden'] ?? false);
        $this->assertFalse($result['mark']['flagged'] ?? true);
        $this->assertSame(5.0, $result['mark']['overridden_from_score'] ?? null);
    }

    public function test_post_override_reviewer_forbidden(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');
        $GLOBALS['pr_test_current_user_id'] = $this->reviewer_user_id;

        $marks = new MarkRepository($this->wpdb);
        $mark_id = $marks->upsert(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $this->criterion_id,
            5.0,
            MarkRepository::STATUS_SUBMITTED
        );

        $post = new WP_REST_Request();
        $post->set_param('id', $mark_id);
        $post->set_json_params([
            'score' => 6.0,
            'reason' => 'Reviewer must not override marks',
        ]);

        $callback = null;
        foreach ($GLOBALS['pr_test_registered_routes'] as $route) {
            $path = (string) ($route['route'] ?? '');
            if (str_contains($path, '/override')) {
                $callback = $route['args']['permission_callback'] ?? null;
                break;
            }
        }

        $this->assertIsCallable($callback);
        $allowed = $callback($post);
        $this->assertInstanceOf(WP_Error::class, $allowed);
        $this->assertSame(403, $allowed->get_error_data()['status'] ?? null);
    }
}
