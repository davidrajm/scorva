<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Rest_Auth;
use ProjectReviews\Rest_Marks;
use ProjectReviews\Rest_Portal;
use ProjectReviews\Rest_Reviewer_Assignments;
use ProjectReviews\Services\ReviewerProvisionService;
use ProjectReviews\Services\ReviewerSessionService;
use ProjectReviews\Tests\Support\ScenarioBuilder;
use WP_REST_Request;

/**
 * Full token-portal journey: credentials → login → assignments → marks.
 * No WordPress user is created anywhere; the reviewer identity in the
 * marks pipeline is the roster row id.
 *
 * @group journey
 */
final class PortalMarkingJourneyTest extends TestCase
{
    private FakeWpdb $wpdb;

    private ScenarioBuilder $scenario;

    private int $reviewer_id;

    private string $token;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/tests/RestAuthTest.php';
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_transients'] = [];
        $GLOBALS['pr_test_users'] = [];
        $GLOBALS['pr_test_current_user_id'] = 0;
        unset($_COOKIE[ReviewerSessionService::COOKIE_NAME]);

        $this->scenario = ScenarioBuilder::fresh($this->wpdb)
            ->with_students(2)
            ->with_active_project('Portal Journey')
            ->with_panel('Panel A')
            ->with_reviews(1)
            ->with_confirmed_rubrics()
            ->with_marking_active();

        $panels = new PanelRepository($this->wpdb);
        $this->reviewer_id = $panels->add_reviewer($this->scenario->panel_id(), [
            'name' => 'Dr. Token',
            'email' => 'token@example.com',
            'weight' => 1,
        ]);

        $service = new ReviewerProvisionService(null, $panels);
        $result = $service->generate_reviewer_credentials(
            $this->scenario->session_id(),
            $this->reviewer_id
        );
        $this->assertIsArray($result);
        $this->token = $result['token'];
        $this->password = $result['password'];

        $auth = new WP_REST_Request();
        $auth->set_json_params(['token' => $this->token, 'password' => $this->password]);
        $auth_result = Rest_Portal::auth($auth);
        $this->assertIsArray($auth_result);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $_COOKIE[ReviewerSessionService::COOKIE_NAME]);
        $GLOBALS['pr_test_transients'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        parent::tearDown();
    }

    public function test_portal_reviewer_sees_assignments_without_wp_login(): void
    {
        $this->assertSame(0, get_current_user_id());

        $result = Rest_Reviewer_Assignments::list_assignments(new WP_REST_Request());

        $this->assertCount(1, $result['assignments']);
        $assignment = $result['assignments'][0];
        $this->assertSame($this->scenario->session_id(), $assignment['session_id']);
        $this->assertSame($this->scenario->panel_id(), $assignment['panel_id']);
        $this->assertTrue($assignment['markable']);
    }

    public function test_portal_reviewer_can_save_and_read_marks(): void
    {
        $session_id = $this->scenario->session_id();
        $review_id = $this->scenario->first_review_id();
        $student_id = $this->scenario->first_student_id();
        $criterion_id = $this->scenario->first_criterion_id();

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
            'student_id' => $student_id,
        ]);
        $save->set_json_params([
            'status' => 'draft',
            'criteria' => [['criterion_id' => $criterion_id, 'score' => 7.5]],
            'attendance_status' => 'present',
        ]);

        $result = Rest_Marks::save_marks($save);
        $this->assertIsArray($result);

        $rows = $this->wpdb->get_all_rows($this->wpdb->prefix . 'pr_marks');
        $this->assertNotEmpty($rows);
        $this->assertSame(
            $this->reviewer_id,
            (int) $rows[0]['reviewer_user_id'],
            'Mark must be attributed to the roster row identity.'
        );

        $get = new WP_REST_Request();
        $get->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
            'student_id' => $student_id,
        ]);
        $read = Rest_Marks::get_marks($get);
        $this->assertIsArray($read);
    }

    public function test_permission_callbacks_pass_with_portal_session_only(): void
    {
        $assignments_permission = Rest_Auth::allow_reviewer_session(
            Rest_Auth::require_cap(\PR_CAP_ENTER_MARKS)
        );
        $this->assertTrue($assignments_permission(new WP_REST_Request()));

        (new ReviewerSessionService())->destroy();

        $result = $assignments_permission(new WP_REST_Request());
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function test_list_students_scopes_to_reviewer_panel(): void
    {
        $result = Rest_Reviewer_Assignments::list_students(self::students_request(
            $this->scenario->session_id(),
            $this->scenario->first_review_id(),
            $this->scenario->panel_id()
        ));

        $this->assertIsArray($result);
        $this->assertCount(2, $result['students']);
        $this->assertSame('Portal Journey', $result['session_title']);
    }

    private static function students_request(int $session_id, int $review_id, int $panel_id): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
            'panel_id' => $panel_id,
        ]);

        return $request;
    }
}
