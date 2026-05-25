<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Sessions;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use WP_Error;
use WP_REST_Request;

final class RestSessionsTest extends TestCase
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
        require_once dirname(__DIR__) . '/includes/rest/class-rest-sessions.php';

        Rest_Bootstrap::register_routes();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_create_session_stores_draft_status(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $request = new WP_REST_Request();
        $request->set_json_params(['title' => 'Spring reviews']);

        $result = Rest_Sessions::create_session($request);

        $this->assertIsArray($result);
        $this->assertSame('draft', $result['status']);
        $this->assertSame('Spring reviews', $result['title']);
        $this->assertSame(0, $result['enrolled_count']);
        $this->assertGreaterThan(0, (new ReviewRepository($this->wpdb))->count_for_session((int) $result['id']));
    }

    public function test_create_session_with_student_ids_enrols_roster(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R301', 'name' => 'Enrolled on create']);

        $result = Rest_Sessions::create_session(
            $this->json_request([
                'title' => 'Project with roster',
                'student_ids' => [$student_id, 999, $student_id],
            ])
        );

        $this->assertIsArray($result);
        $this->assertSame(1, $result['enrolled_count']);
        $this->assertSame([$student_id], $result['enrolment']['enrolled']);
        $this->assertCount(2, $result['enrolment']['skipped']);
    }

    public function test_list_sessions_includes_enrolled_count(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R302', 'name' => 'Listed count']);

        $session = Rest_Sessions::create_session(
            $this->json_request([
                'title' => 'Counted session',
                'student_ids' => [$student_id],
            ])
        );
        $this->assertIsArray($session);

        Rest_Sessions::create_session($this->json_request(['title' => 'Empty roster']));

        $listed = Rest_Sessions::list_sessions(new WP_REST_Request());
        $this->assertIsArray($listed);

        $by_title = [];
        foreach ($listed as $row) {
            $by_title[$row['title']] = $row['enrolled_count'];
        }

        $this->assertSame(1, $by_title['Counted session']);
        $this->assertSame(0, $by_title['Empty roster']);
    }

    public function test_list_sessions_filters_by_status(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        Rest_Sessions::create_session($this->json_request(['title' => 'Draft session']));
        $active = Rest_Sessions::create_session($this->json_request(['title' => 'Active session']));
        $this->assertIsArray($active);
        Rest_Sessions::update_session($this->route_request('PUT', ['id' => $active['id']], ['status' => 'active']));

        $request = new WP_REST_Request();
        $request->set_param('status', 'active');
        $listed = Rest_Sessions::list_sessions($request);

        $this->assertIsArray($listed);
        $this->assertCount(1, $listed);
        $this->assertSame('active', $listed[0]['status']);
    }

    public function test_wizard_state_roster_first_gate(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Wizard gate']));
        $this->assertIsArray($session);

        $request = new WP_REST_Request();
        $request->set_param('id', $session['id']);

        $empty = Rest_Sessions::wizard_state($request);
        $this->assertIsArray($empty);
        $this->assertSame(1, $empty['review_count']);
        $this->assertSame(0, $empty['enrolled_count']);
        $this->assertTrue($empty['can_advance_to_students']);
        $this->assertFalse($empty['can_advance_to_panels']);

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R303', 'name' => 'Gate student']);
        Rest_Sessions::enrol_students(
            $this->route_request('POST', ['id' => $session['id']], ['student_ids' => [$student_id]])
        );

        $with_roster = Rest_Sessions::wizard_state($request);
        $this->assertSame(1, $with_roster['enrolled_count']);
        $this->assertTrue($with_roster['can_advance_to_panels']);
        $this->assertFalse($with_roster['can_advance_to_assignments']);

        $panels = new PanelRepository($this->wpdb);
        $panel_id = $panels->create((int) $session['id'], 'Panel A');
        (new SessionRepository($this->wpdb))->assign_panel(
            (int) $session['id'],
            $student_id,
            $panel_id
        );
        $panels->add_reviewer($panel_id, [
            'name' => 'Reviewer',
            'email' => 'rev@example.com',
            'user_id' => 9001,
        ]);

        $reviews = new ReviewRepository($this->wpdb);
        $review_list = $reviews->list_for_session((int) $session['id']);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);
        foreach ($review_list as $review) {
            $assignments->seed_from_session_defaults((int) $review['id'], (int) $session['id']);
        }

        $panels_assigned = Rest_Sessions::wizard_state($request);
        $this->assertTrue($panels_assigned['can_advance_to_reviewers']);
        $this->assertFalse($panels_assigned['can_advance_to_assignments']);
        $this->assertTrue($panels_assigned['assignments_complete']);
        $this->assertTrue($panels_assigned['can_advance_to_rubrics']);
        $this->assertFalse($panels_assigned['can_advance_to_reviews']);
    }

    public function test_delete_panel_with_assigned_student_returns_409(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Panel delete guard']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R401', 'name' => 'Assigned panel student']);

        Rest_Sessions::enrol_students(
            $this->route_request('POST', ['id' => $session_id], ['student_ids' => [$student_id]])
        );

        $panel = Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Panel A'])
        );
        $this->assertIsArray($panel);
        $panel_id = (int) $panel['id'];

        Rest_Sessions::update_enrolled_student(
            $this->route_request(
                'PUT',
                ['id' => $session_id, 'student_id' => $student_id],
                ['panel_id' => $panel_id]
            )
        );

        $result = Rest_Sessions::delete_panel(
            $this->route_request('DELETE', ['id' => $session_id, 'panel_id' => $panel_id])
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_panel_has_students', $result->get_error_code());
    }

    public function test_delete_panel_after_unassigning_students(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Panel delete ok']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R402', 'name' => 'Unassigned panel student']);

        Rest_Sessions::enrol_students(
            $this->route_request('POST', ['id' => $session_id], ['student_ids' => [$student_id]])
        );

        $panel = Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Panel B'])
        );
        $this->assertIsArray($panel);
        $panel_id = (int) $panel['id'];

        Rest_Sessions::update_enrolled_student(
            $this->route_request(
                'PUT',
                ['id' => $session_id, 'student_id' => $student_id],
                ['panel_id' => $panel_id]
            )
        );

        Rest_Sessions::update_enrolled_student(
            $this->route_request(
                'PUT',
                ['id' => $session_id, 'student_id' => $student_id],
                ['panel_id' => null]
            )
        );

        $result = Rest_Sessions::delete_panel(
            $this->route_request('DELETE', ['id' => $session_id, 'panel_id' => $panel_id])
        );

        $this->assertSame(['deleted' => true], $result);
        $this->assertNull((new PanelRepository($this->wpdb))->find_by_id($panel_id));
    }

    public function test_rename_panel_and_duplicate_name_returns_409(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Panel rename']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $alpha = Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Alpha'])
        );
        $this->assertIsArray($alpha);

        $beta = Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Beta'])
        );
        $this->assertIsArray($beta);
        $beta_id = (int) $beta['id'];

        $renamed = Rest_Sessions::update_panel(
            $this->route_request(
                'PUT',
                ['id' => $session_id, 'panel_id' => $beta_id],
                ['name' => 'Gamma']
            )
        );
        $this->assertIsArray($renamed);
        $this->assertSame('Gamma', $renamed['name']);
        $this->assertSame(0, $renamed['student_count']);
        $this->assertTrue($renamed['deletable']);

        $duplicate = Rest_Sessions::update_panel(
            $this->route_request(
                'PUT',
                ['id' => $session_id, 'panel_id' => $beta_id],
                ['name' => 'Alpha']
            )
        );
        $this->assertInstanceOf(WP_Error::class, $duplicate);
        $this->assertSame('pr_duplicate_panel', $duplicate->get_error_code());
    }

    public function test_list_panels_includes_student_count_and_deletable(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Panel list']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R403', 'name' => 'Listed student']);

        Rest_Sessions::enrol_students(
            $this->route_request('POST', ['id' => $session_id], ['student_ids' => [$student_id]])
        );

        $occupied = Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Occupied'])
        );
        $this->assertIsArray($occupied);

        Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Empty'])
        );

        Rest_Sessions::update_enrolled_student(
            $this->route_request(
                'PUT',
                ['id' => $session_id, 'student_id' => $student_id],
                ['panel_id' => (int) $occupied['id']]
            )
        );

        $listed = Rest_Sessions::list_panels(
            $this->route_request('GET', ['id' => $session_id])
        );
        $this->assertIsArray($listed);

        $by_name = [];
        foreach ($listed['panels'] as $panel) {
            $by_name[$panel['name']] = $panel;
        }

        $this->assertSame(1, $by_name['Occupied']['student_count']);
        $this->assertFalse($by_name['Occupied']['deletable']);
        $this->assertSame(0, $by_name['Empty']['student_count']);
        $this->assertTrue($by_name['Empty']['deletable']);
    }

    public function test_roster_project_title_and_removal_guard(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Titles']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R501', 'name' => 'Title student']);
        $panel = Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Panel A'])
        );
        $this->assertIsArray($panel);

        Rest_Sessions::enrol_students(
            $this->route_request('POST', ['id' => $session_id], ['student_ids' => [$student_id]])
        );

        Rest_Sessions::update_enrolled_student(
            $this->route_request(
                'PUT',
                ['id' => $session_id, 'student_id' => $student_id],
                [
                    'panel_id' => (int) $panel['id'],
                    'project_title' => 'Machine Learning for Healthcare',
                ]
            )
        );

        $listed = Rest_Sessions::list_enrolled_students(
            $this->route_request('GET', ['id' => $session_id])
        );
        $this->assertIsArray($listed);
        $this->assertSame(
            'Machine Learning for Healthcare',
            $listed['students'][0]['project_title']
        );
        $this->assertFalse($listed['students'][0]['has_scores']);

        $prefix = $this->wpdb->prefix;
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = (int) $reviews->list_for_session($session_id)[0]['id'];
        $this->wpdb->insert("{$prefix}pr_marks", [
            'session_id' => $session_id,
            'review_id' => $review_id,
            'student_id' => $student_id,
            'reviewer_user_id' => 1,
            'criterion_id' => 1,
            'score' => 7.5,
            'status' => 'draft',
        ]);

        $blocked = Rest_Sessions::remove_enrolled_student(
            $this->route_request('DELETE', ['id' => $session_id, 'student_id' => $student_id])
        );
        $this->assertInstanceOf(WP_Error::class, $blocked);
        $this->assertSame('pr_student_has_scores', $blocked->get_error_code());

        $this->wpdb->update(
            "{$prefix}pr_marks",
            ['score' => null],
            ['session_id' => $session_id, 'student_id' => $student_id],
            ['%f'],
            ['%d', '%d']
        );

        $removed = Rest_Sessions::remove_enrolled_student(
            $this->route_request('DELETE', ['id' => $session_id, 'student_id' => $student_id])
        );
        $this->assertSame(['removed' => true], $removed);
        $this->assertNull($students->find_by_id($student_id));
    }

    public function test_remove_all_enrolled_students_clears_roster_and_orphan_registry(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Bulk clear']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $id_a = $students->insert(['reg_no' => 'R801', 'name' => 'Bulk A']);
        $id_b = $students->insert(['reg_no' => 'R802', 'name' => 'Bulk B']);

        Rest_Sessions::enrol_students(
            $this->route_request(
                'POST',
                ['id' => $session_id],
                ['student_ids' => [$id_a, $id_b]]
            )
        );

        $result = Rest_Sessions::remove_all_enrolled_students(
            $this->route_request('DELETE', ['id' => $session_id])
        );
        $this->assertIsArray($result);
        $this->assertSame(2, $result['removed']);
        $this->assertSame(2, $result['registry_deleted']);
        $this->assertSame(0, $result['skipped_has_scores']);

        $listed = Rest_Sessions::list_enrolled_students(
            $this->route_request('GET', ['id' => $session_id])
        );
        $this->assertIsArray($listed);
        $this->assertSame([], $listed['students']);
        $this->assertNull($students->find_by_id($id_a));
        $this->assertNull($students->find_by_id($id_b));
    }

    public function test_remove_all_preserves_registry_when_enrolled_in_another_project(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session_a = Rest_Sessions::create_session($this->json_request(['title' => 'Project A']));
        $session_b = Rest_Sessions::create_session($this->json_request(['title' => 'Project B']));
        $this->assertIsArray($session_a);
        $this->assertIsArray($session_b);
        $session_a_id = (int) $session_a['id'];
        $session_b_id = (int) $session_b['id'];

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R803', 'name' => 'Shared']);

        Rest_Sessions::enrol_students(
            $this->route_request(
                'POST',
                ['id' => $session_a_id],
                ['student_ids' => [$student_id]]
            )
        );
        Rest_Sessions::enrol_students(
            $this->route_request(
                'POST',
                ['id' => $session_b_id],
                ['student_ids' => [$student_id]]
            )
        );

        $result = Rest_Sessions::remove_all_enrolled_students(
            $this->route_request('DELETE', ['id' => $session_a_id])
        );
        $this->assertIsArray($result);
        $this->assertSame(1, $result['removed']);
        $this->assertSame(0, $result['registry_deleted']);

        $this->assertNotNull($students->find_by_id($student_id));
        $sessions = new SessionRepository($this->wpdb);
        $this->assertNull($sessions->find_enrolment($session_a_id, $student_id));
        $this->assertNotNull($sessions->find_enrolment($session_b_id, $student_id));
    }

    public function test_remove_all_blocked_without_confirm_when_scores_exist(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Scored bulk']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R804', 'name' => 'Scored']);

        Rest_Sessions::enrol_students(
            $this->route_request(
                'POST',
                ['id' => $session_id],
                ['student_ids' => [$student_id]]
            )
        );

        $prefix = $this->wpdb->prefix;
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = (int) $reviews->list_for_session($session_id)[0]['id'];
        $this->wpdb->insert("{$prefix}pr_marks", [
            'session_id' => $session_id,
            'review_id' => $review_id,
            'student_id' => $student_id,
            'reviewer_user_id' => 1,
            'criterion_id' => 1,
            'score' => 8.0,
            'status' => 'draft',
        ]);

        $blocked = Rest_Sessions::remove_all_enrolled_students(
            $this->route_request('DELETE', ['id' => $session_id])
        );
        $this->assertInstanceOf(WP_Error::class, $blocked);
        $this->assertSame('pr_remove_students_confirmation_required', $blocked->get_error_code());

        $sessions = new SessionRepository($this->wpdb);
        $this->assertNotNull($sessions->find_enrolment($session_id, $student_id));
        $this->assertNotNull($students->find_by_id($student_id));
    }

    public function test_remove_all_with_confirm_deletes_scores_and_orphan_registry(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Confirm bulk']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R805', 'name' => 'Confirm scored']);

        Rest_Sessions::enrol_students(
            $this->route_request(
                'POST',
                ['id' => $session_id],
                ['student_ids' => [$student_id]]
            )
        );

        $prefix = $this->wpdb->prefix;
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = (int) $reviews->list_for_session($session_id)[0]['id'];
        $this->wpdb->insert("{$prefix}pr_marks", [
            'session_id' => $session_id,
            'review_id' => $review_id,
            'student_id' => $student_id,
            'reviewer_user_id' => 1,
            'criterion_id' => 1,
            'score' => 6.0,
            'status' => 'draft',
        ]);

        $result = Rest_Sessions::remove_all_enrolled_students(
            $this->route_request(
                'DELETE',
                ['id' => $session_id],
                ['confirm_with_scores' => 'Confirm']
            )
        );
        $this->assertIsArray($result);
        $this->assertSame(1, $result['removed']);
        $this->assertSame(1, $result['registry_deleted']);

        $sessions = new SessionRepository($this->wpdb);
        $this->assertNull($sessions->find_enrolment($session_id, $student_id));
        $this->assertNull($students->find_by_id($student_id));

        $mark_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}pr_marks WHERE session_id = %d AND student_id = %d",
                $session_id,
                $student_id
            )
        );
        $this->assertSame('0', (string) $mark_count);
    }

    public function test_import_enrolment_auto_creates_missing_students(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Auto enrol']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $students->insert(['reg_no' => 'R601', 'name' => 'Known student']);

        $result = Rest_Sessions::import_enrolment(
            $this->route_request(
                'POST',
                ['id' => $session_id],
                [
                    'rows' => [
                        ['reg_no' => 'R601', 'panel' => 'Panel A'],
                        [
                            'reg_no' => 'R999',
                            'name' => 'Created via CSV',
                            'panel' => 'Panel A',
                        ],
                    ],
                ]
            )
        );

        $this->assertIsArray($result);
        $this->assertSame(2, $result['enrolled']);

        $listed = Rest_Sessions::list_enrolled_students(
            $this->route_request('GET', ['id' => $session_id])
        );
        $this->assertIsArray($listed);
        $this->assertCount(2, $listed['students']);

        $created = $students->find_by_reg_no('R999');
        $this->assertNotNull($created);
        $this->assertSame('Created via CSV', $created['name']);
    }

    public function test_enrol_student_by_reg_no_creates_and_enrols(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'POST enrol']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $result = Rest_Sessions::enrol_students(
            $this->route_request(
                'POST',
                ['id' => $session_id],
                [
                    'reg_no' => 'R700',
                    'name' => 'Wizard Add',
                    'program' => 'BSc',
                    'batch' => '2026',
                ]
            )
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('student', $result);
        $this->assertSame('R700', $result['student']['student']['reg_no']);
        $this->assertSame('Wizard Add', $result['student']['student']['name']);
        $this->assertSame('BSc', $result['student']['student']['program']);
        $this->assertSame('2026', $result['student']['student']['batch']);

        $students = new StudentRepository($this->wpdb);
        $this->assertNotNull($students->find_by_reg_no('R700'));
    }

    public function test_import_enrolment_sets_project_title(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'CSV titles']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $students = new StudentRepository($this->wpdb);
        $students->insert(['reg_no' => 'R502', 'name' => 'CSV student']);

        Rest_Sessions::create_panel(
            $this->route_request('POST', ['id' => $session_id], ['name' => 'Panel A'])
        );

        $result = Rest_Sessions::import_enrolment(
            $this->route_request(
                'POST',
                ['id' => $session_id],
                [
                    'rows' => [
                        [
                            'reg_no' => 'R502',
                            'panel' => 'Panel A',
                            'project_title' => 'Distributed Systems Study',
                        ],
                    ],
                ]
            )
        );
        $this->assertIsArray($result);
        $this->assertSame(1, $result['enrolled']);

        $listed = Rest_Sessions::list_enrolled_students(
            $this->route_request('GET', ['id' => $session_id])
        );
        $this->assertSame(
            'Distributed Systems Study',
            $listed['students'][0]['project_title']
        );
    }

    public function test_list_sessions_includes_has_entered_scores(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $empty = Rest_Sessions::create_session($this->json_request(['title' => 'No scores']));
        $this->assertIsArray($empty);

        $scored = Rest_Sessions::create_session($this->json_request(['title' => 'With scores']));
        $this->assertIsArray($scored);
        $session_id = (int) $scored['id'];
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = 0;
        foreach ($reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            break;
        }
        $this->assertGreaterThan(0, $review_id);
        $reviews->replace_criteria($review_id, [
            ['label' => 'Quality', 'max_marks' => 10, 'weight' => 1],
        ]);
        $this->seed_entered_mark($session_id, $review_id);

        $listed = Rest_Sessions::list_sessions(new WP_REST_Request());
        $this->assertIsArray($listed);

        $by_title = [];
        foreach ($listed as $row) {
            $by_title[$row['title']] = $row['has_entered_scores'];
        }

        $this->assertFalse($by_title['No scores']);
        $this->assertTrue($by_title['With scores']);
    }

    public function test_delete_closed_session_with_scores_succeeds(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Closed delete']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];
        Rest_Sessions::update_session(
            $this->route_request('PUT', ['id' => $session_id], ['status' => SessionRepository::STATUS_CLOSED])
        );
        $review_id = $this->first_review_id($session_id);
        $this->seed_entered_mark($session_id, $review_id);

        $result = Rest_Sessions::delete_session(
            $this->route_request(
                'DELETE',
                ['id' => $session_id],
                ['confirm_label' => 'Closed delete']
            )
        );
        $this->assertSame(['deleted' => true], $result);
        $this->assertNull((new SessionRepository($this->wpdb))->find_by_id($session_id));
    }

    public function test_delete_session_without_scores_succeeds(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Disposable']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];

        $result = Rest_Sessions::delete_session(
            $this->route_request('DELETE', ['id' => $session_id])
        );
        $this->assertSame(['deleted' => true], $result);
        $this->assertNull((new SessionRepository($this->wpdb))->find_by_id($session_id));
        $this->assertSame(
            0,
            (new ReviewRepository($this->wpdb))->count_for_session($session_id)
        );
    }

    public function test_delete_session_with_scores_requires_matching_title(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Scored project']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];
        $review_id = $this->first_review_id($session_id);
        $this->seed_entered_mark($session_id, $review_id);

        $reject = Rest_Sessions::delete_session(
            $this->route_request('DELETE', ['id' => $session_id], ['confirm_label' => 'Wrong'])
        );
        $this->assertInstanceOf(WP_Error::class, $reject);
        $this->assertSame('pr_session_delete_confirmation_required', $reject->get_error_code());
        $this->assertNotNull((new SessionRepository($this->wpdb))->find_by_id($session_id));

        $ok = Rest_Sessions::delete_session(
            $this->route_request(
                'DELETE',
                ['id' => $session_id],
                ['confirm_label' => 'Scored project']
            )
        );
        $this->assertSame(['deleted' => true], $ok);
        $this->assertNull((new SessionRepository($this->wpdb))->find_by_id($session_id));
        $this->assertSame(
            0,
            (new ReviewRepository($this->wpdb))->count_for_session($session_id)
        );
    }

    public function test_delete_session_with_scores_blocked_without_confirm_label(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Needs phrase']));
        $this->assertIsArray($session);
        $session_id = (int) $session['id'];
        $review_id = $this->first_review_id($session_id);
        $this->seed_entered_mark($session_id, $review_id);

        $reject = Rest_Sessions::delete_session(
            $this->route_request('DELETE', ['id' => $session_id])
        );
        $this->assertInstanceOf(WP_Error::class, $reject);
        $this->assertSame('pr_session_delete_confirmation_required', $reject->get_error_code());
    }

    public function test_enrol_students_endpoint(): void
    {
        global $pr_test_user_caps;
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        $pr_test_user_caps[PR_CAP_UPLOAD_STUDENTS] = true;

        $session = Rest_Sessions::create_session($this->json_request(['title' => 'Enrol']));
        $students = new StudentRepository($this->wpdb);
        $student_id = $students->insert(['reg_no' => 'R300', 'name' => 'Student']);

        $request = new WP_REST_Request();
        $request->set_param('id', $session['id']);
        $request->set_json_params(['student_ids' => [$student_id]]);

        $result = Rest_Sessions::enrol_students($request);
        $this->assertSame([$student_id], $result['enrolled']);
    }

    private function first_review_id(int $session_id): int
    {
        $reviews = new ReviewRepository($this->wpdb);
        foreach ($reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            if ($review_id > 0) {
                $reviews->replace_criteria($review_id, [
                    ['label' => 'Criterion', 'max_marks' => 10, 'weight' => 1],
                ]);

                return $review_id;
            }
        }

        $this->fail('Expected at least one review round for session.');

        return 0;
    }

    private function seed_entered_mark(int $session_id, int $review_id): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        $criteria = $reviews->list_criteria($review_id);
        $criterion_id = (int) ($criteria[0]['id'] ?? 0);

        $this->wpdb->insert(
            $this->wpdb->prefix . 'pr_marks',
            [
                'session_id' => $session_id,
                'review_id' => $review_id,
                'student_id' => 1,
                'reviewer_user_id' => 9,
                'criterion_id' => $criterion_id,
                'score' => 4.0,
                'flagged' => 0,
                'status' => 'submitted',
            ]
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json_request(array $body): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_json_params($body);

        return $request;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $body
     */
    private function route_request(string $method, array $params, array $body = []): WP_REST_Request
    {
        $request = new WP_REST_Request();
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        if ($body !== []) {
            $request->set_json_params($body);
        }
        unset($method);

        return $request;
    }
}
