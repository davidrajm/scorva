<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Rest_Binary_Response;
use ProjectReviews\Rest_Reports;
use ProjectReviews\Services\ReportQueryService;
use WP_Error;
use WP_REST_Request;

final class RestReportsTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id = 1;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/tests/RestAuthTest.php';
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/repositories/SessionRepository.php';
        require_once dirname(__DIR__) . '/includes/services/ReportQueryService.php';
        require_once dirname(__DIR__) . '/includes/services/ExportService.php';
        require_once dirname(__DIR__) . '/includes/services/ScoreService.php';
        require_once dirname(__DIR__) . '/includes/services/MarkService.php';
        require_once dirname(__DIR__) . '/includes/services/ReportsViewService.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-binary-response.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reports.php';
        Rest_Binary_Response::register();

        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => $this->session_id,
            'title' => 'Committee Session',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_list_report_types_returns_five_canonical_catalog_entries(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);

        $catalog = Rest_Reports::list_report_types($request);

        $this->assertCount(5, $catalog);
        $keys = array_column($catalog, 'key');
        $this->assertContains(\ProjectReviews\Services\ReportsViewService::PANEL_ROSTER_CATALOG_KEY, $keys);
        $this->assertContains(\ProjectReviews\Services\ReportsViewService::CONSOLIDATED_STUDENT_CATALOG_KEY, $keys);
        $this->assertContains(\ProjectReviews\Services\ReportsViewService::OFFLINE_SCORING_SHEET_CATALOG_KEY, $keys);
        $this->assertContains(\ProjectReviews\Services\ReportsViewService::MARKS_MATRIX_CATALOG_KEY, $keys);
        $this->assertContains(\ProjectReviews\Services\ReportsViewService::SCORES_MATRIX_CATALOG_KEY, $keys);
        $this->assertNotContains(ReportQueryService::TYPE_STUDENT_MASTER, $keys);

        $panel_roster = array_values(
            array_filter(
                $catalog,
                static fn (array $item): bool => $item['key'] === \ProjectReviews\Services\ReportsViewService::PANEL_ROSTER_CATALOG_KEY
            )
        )[0];
        $this->assertSame('review', $panel_roster['scope'] ?? null);

        $consolidated = array_values(
            array_filter(
                $catalog,
                static fn (array $item): bool => $item['key'] === \ProjectReviews\Services\ReportsViewService::CONSOLIDATED_STUDENT_CATALOG_KEY
            )
        )[0];
        $this->assertSame('session', $consolidated['scope'] ?? null);
    }

    public function test_list_report_types_empty_when_session_missing(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $request = new WP_REST_Request();
        $request->set_param('id', 999);

        $this->assertSame([], Rest_Reports::list_report_types($request));
    }

    public function test_download_unknown_type_returns_400(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);
        $request->set_param('type', 'not_a_report');
        $request->set_param('format', 'csv');

        $result = Rest_Reports::download_report($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_invalid_report', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status'] ?? null);
    }

    public function test_download_legacy_report_type_returns_410(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);
        $request->set_param('type', ReportQueryService::TYPE_RUBRIC_SCORES);
        $request->set_param('format', 'csv');

        $result = Rest_Reports::download_report($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_report_deprecated', $result->get_error_code());
        $this->assertSame(410, $result->get_error_data()['status'] ?? null);
    }

    public function test_marks_grid_returns_students_and_criteria(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $prefix = $this->wpdb->prefix;
        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($panel_id, [
            'name' => 'Dr Smith',
            'email' => 'smith@example.com',
            'user_id' => 55,
        ]);
        $student_id = $students->insert(['reg_no' => 'G001', 'name' => 'Grid Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Grid Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            55,
            $criterion_id,
            8.0,
            'submitted'
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $grid = Rest_Reports::marks_grid($request);

        $this->assertSame($review_id, $grid['review_id']);
        $this->assertCount(1, $grid['criteria']);
        $this->assertCount(1, $grid['students']);
        $this->assertSame('G001', $grid['students'][0]['reg_no']);
        $this->assertArrayHasKey('attendance_status', $grid['students'][0]);
        $this->assertArrayHasKey('mark_status', $grid['students'][0]);
        $marks_for_criterion = $grid['students'][0]['marks'][(string) $criterion_id];
        $this->assertSame(8.0, $marks_for_criterion[0]['score']);
        $this->assertFalse($grid['coordinator_marks_locked']);
    }

    public function test_marks_grid_includes_coordinator_override_fields(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel Shuttle');
        $panels->add_reviewer($panel_id, [
            'name' => 'Dr Shuttle',
            'email' => 'shuttle@example.com',
            'user_id' => 88,
        ]);
        $student_id = $students->insert(['reg_no' => 'S001', 'name' => 'Shuttle Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Shuttle Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Criterion', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $mark_id = $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            88,
            $criterion_id,
            8.0,
            'submitted'
        );
        $marks->apply_coordinator_override($mark_id, 7.0);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $grid = Rest_Reports::marks_grid($request);
        $entry = $grid['students'][0]['marks'][(string) $criterion_id][0];

        $this->assertTrue($entry['coordinator_overridden']);
        $this->assertSame(8.0, $entry['overridden_from_score']);
        $this->assertFalse($entry['flagged']);
    }

    public function test_marks_grid_panel_slots_and_draft_scores(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_a = $panels->create($this->session_id, 'Panel A');
        $panel_b = $panels->create($this->session_id, 'Panel B');
        $panels->add_reviewer($panel_a, ['name' => 'Rev A1', 'email' => 'a1@example.com', 'user_id' => 61]);
        $panels->add_reviewer($panel_a, ['name' => 'Rev A2', 'email' => 'a2@example.com', 'user_id' => 62]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B1', 'email' => 'b1@example.com', 'user_id' => 71]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B2', 'email' => 'b2@example.com', 'user_id' => 72]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B3', 'email' => 'b3@example.com', 'user_id' => 73]);

        $student_a = $students->insert(['reg_no' => 'PA01', 'name' => 'Panel A Student']);
        $student_b = $students->insert(['reg_no' => 'PB01', 'name' => 'Panel B Student']);
        $sessions->enrol_student($this->session_id, $student_a, $panel_a);
        $sessions->enrol_student($this->session_id, $student_b, $panel_b);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Slots Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];

        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_a,
            61,
            $criterion_id,
            6.5,
            \ProjectReviews\Repositories\MarkRepository::STATUS_DRAFT
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $grid = Rest_Reports::marks_grid($request);

        $this->assertSame(3, $grid['max_panel_reviewer_slots']);
        $student_rows = [];
        foreach ($grid['students'] as $row) {
            $student_rows[(int) $row['student_id']] = $row;
        }
        $this->assertSame($panel_a, $student_rows[$student_a]['panel_id']);
        $this->assertCount(2, $student_rows[$student_a]['panel_reviewers']);
        $this->assertSame(0, $student_rows[$student_a]['panel_reviewers'][0]['slot_index']);
        $this->assertSame(61, $student_rows[$student_a]['panel_reviewers'][0]['user_id']);

        $mark_entries = $student_rows[$student_a]['marks'][(string) $criterion_id];
        $this->assertSame(6.5, $mark_entries[0]['score']);
        $this->assertSame('draft', $mark_entries[0]['status']);

        $export = (new \ProjectReviews\Services\ReportsViewService())->marks_grid_export(
            $this->session_id,
            $review_id,
            'rubric',
            'reg_no',
            'asc'
        );
        $this->assertIsArray($export);
        $this->assertGreaterThanOrEqual(3, count($export['rows']));
        $score_column = 7;
        $this->assertSame(6.5, $export['rows'][2][ $score_column ] );
    }

    public function test_marks_grid_export_empty_cells_for_absent_no_draft_label(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel Absent');
        $panels->add_reviewer($panel_id, ['name' => 'Rev A1', 'email' => 'a1@example.com', 'user_id' => 61]);

        $absent_id = $students->insert(['reg_no' => 'AB01', 'name' => 'Absent Student']);
        $present_id = $students->insert(['reg_no' => 'PR01', 'name' => 'Present Draft']);
        $sessions->enrol_student($this->session_id, $absent_id, $panel_id);
        $sessions->enrol_student($this->session_id, $present_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Absent Export',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $assignments->set_attendance_status(
            $review_id,
            $absent_id,
            \ProjectReviews\Repositories\ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );

        $marks->upsert(
            $this->session_id,
            $review_id,
            $absent_id,
            61,
            $criterion_id,
            null,
            \ProjectReviews\Repositories\MarkRepository::STATUS_DRAFT
        );
        $marks->upsert(
            $this->session_id,
            $review_id,
            $present_id,
            61,
            $criterion_id,
            6.5,
            \ProjectReviews\Repositories\MarkRepository::STATUS_DRAFT
        );

        $service = new \ProjectReviews\Services\ReportsViewService();
        $export = $service->marks_grid_export(
            $this->session_id,
            $review_id,
            'rubric',
            'reg_no',
            'asc'
        );
        $this->assertIsArray($export);

        $score_column = 7;
        $absent_row = null;
        $present_row = null;
        foreach (array_slice($export['rows'], 2) as $row) {
            if (($row[0] ?? '') === 'AB01') {
                $absent_row = $row;
            }
            if (($row[0] ?? '') === 'PR01') {
                $present_row = $row;
            }
        }

        $this->assertNotNull($absent_row);
        $this->assertNotNull($present_row);
        $this->assertSame('', $absent_row[$score_column]);
        $this->assertNotSame('draft', $absent_row[$score_column]);
        $this->assertSame(6.5, $present_row[$score_column]);

        $scores_export = $service->scores_matrix_export(
            $this->session_id,
            $review_id,
            'reg_no',
            'asc'
        );
        $this->assertIsArray($scores_export);
        $overall_column = 5;
        foreach (array_slice($scores_export['rows'], 2) as $row) {
            if (($row[0] ?? '') === 'AB01') {
                $this->assertSame('', $row[$overall_column]);
                $this->assertNotSame('draft', $row[$overall_column]);
                $this->assertNotSame(0, $row[$overall_column]);
                $this->assertNotSame(0.0, $row[$overall_column]);
            }
        }

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $matrix = Rest_Reports::scores_matrix($request);
        $absent_row = null;
        foreach ($matrix['students'] as $row) {
            if ((int) ($row['student_id'] ?? 0) === $absent_id) {
                $absent_row = $row;
                break;
            }
        }
        $this->assertNotNull($absent_row);
        $this->assertSame(
            \ProjectReviews\Repositories\ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            $absent_row['attendance_status']
        );
        $this->assertArrayHasKey('61', $absent_row['reviewer_totals']);
        $this->assertNull($absent_row['reviewer_totals']['61']);
        foreach ($absent_row['panel_reviewers'] as $reviewer) {
            if ((int) ($reviewer['user_id'] ?? 0) === 61) {
                $this->assertArrayHasKey('total', $reviewer);
                $this->assertNull($reviewer['total']);
            }
        }
    }

    public function test_lock_marks_sets_flag(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel Lock');
        $student_id = $students->insert(['reg_no' => 'L001', 'name' => 'Lock Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Lock Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $reviews->set_marking_active($review_id, true);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);
        (new \ProjectReviews\Repositories\PanelFreezeRepository($this->wpdb))->freeze(
            $review_id,
            $panel_id,
            1
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $result = Rest_Reports::lock_marks($request);
        $this->assertTrue($result['coordinator_marks_locked']);
        $this->assertTrue($reviews->is_coordinator_marks_locked($review_id));
        $this->assertFalse($reviews->is_marking_active($review_id));
    }

    public function test_unlock_marks_clears_flag(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel Unlock');
        $student_id = $students->insert(['reg_no' => 'U001', 'name' => 'Unlock Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Unlock Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);
        $reviews->set_coordinator_marks_locked($review_id, true);
        $reviews->set_marking_active($review_id, false);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $result = Rest_Reports::unlock_marks($request);
        $this->assertFalse($result['coordinator_marks_locked']);
        $this->assertTrue($result['marking_active']);
        $this->assertFalse($reviews->is_coordinator_marks_locked($review_id));
    }

    public function test_scores_matrix_panel_slots_and_extended_fields(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_a = $panels->create($this->session_id, 'Panel A');
        $panel_b = $panels->create($this->session_id, 'Panel B');
        $panels->add_reviewer($panel_a, ['name' => 'Rev A1', 'email' => 'a1@example.com', 'user_id' => 61]);
        $panels->add_reviewer($panel_a, ['name' => 'Rev A2', 'email' => 'a2@example.com', 'user_id' => 62]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B1', 'email' => 'b1@example.com', 'user_id' => 71]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B2', 'email' => 'b2@example.com', 'user_id' => 72]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B3', 'email' => 'b3@example.com', 'user_id' => 73]);

        $student_a = $students->insert(['reg_no' => 'SC01', 'name' => 'Scores A']);
        $student_b = $students->insert(['reg_no' => 'SC02', 'name' => 'Scores B']);
        $sessions->enrol_student($this->session_id, $student_a, $panel_a);
        $sessions->enrol_student($this->session_id, $student_b, $panel_b);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Scores Matrix',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_a,
            61,
            $criterion_id,
            8.0,
            \ProjectReviews\Repositories\MarkRepository::STATUS_SUBMITTED
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $matrix = Rest_Reports::scores_matrix($request);

        $this->assertSame(3, $matrix['max_panel_reviewer_slots']);
        $this->assertArrayHasKey('reviewers', $matrix);
        $student_rows = [];
        foreach ($matrix['students'] as $row) {
            $student_rows[(int) $row['student_id']] = $row;
        }
        $this->assertArrayHasKey('attendance_status', $student_rows[$student_a]);
        $this->assertArrayHasKey('mark_status', $student_rows[$student_a]);
        $this->assertArrayHasKey('panel_reviewers', $student_rows[$student_a]);
        $this->assertCount(2, $student_rows[$student_a]['panel_reviewers']);
        $total = $student_rows[$student_a]['panel_reviewers'][0]['total'];
        $this->assertIsArray($total);
        $this->assertSame(8.0, $total['score']);
        $this->assertFalse($total['draft']);
    }

    public function test_scores_matrix_download_xlsx(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($panel_id, [
            'name' => 'Dr Smith',
            'email' => 'smith@example.com',
            'user_id' => 55,
        ]);
        $student_id = $students->insert(['reg_no' => 'SX01', 'name' => 'Scores Export']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Scores Export Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            55,
            $criterion_id,
            8.0,
            'submitted'
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $request->set_param('format', 'xlsx');
        $request->set_param('sort_key', 'reg_no');
        $request->set_param('sort_dir', 'asc');

        $response = Rest_Reports::scores_matrix_download($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $disposition = (string) ($response->get_headers()['Content-Disposition'] ?? '');
        $this->assertStringContainsString('_scores.xlsx', $disposition);
        $this->assertSame('1', $response->get_headers()[Rest_Binary_Response::SERVE_RAW_HEADER] ?? '');

        $body = $this->serve_raw_body($response);
        $this->assertSame('PK', substr($body, 0, 2));
    }

    public function test_marks_grid_includes_lock_readiness(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel Ready');
        $student_id = $students->insert(['reg_no' => 'R001', 'name' => 'Ready Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Ready Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $reviews->replace_criteria($review_id, [
            ['label' => 'Criterion', 'max_marks' => 10],
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);
        (new \ProjectReviews\Repositories\PanelFreezeRepository($this->wpdb))->freeze(
            $review_id,
            $panel_id,
            1
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $grid = Rest_Reports::marks_grid($request);
        $this->assertIsArray($grid);
        $this->assertTrue($grid['review_lock_ready']);
        $this->assertSame([], $grid['unfrozen_panels']);
    }

    public function test_marks_grid_download_xlsx_with_rubric_layout(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $prefix = $this->wpdb->prefix;
        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($panel_id, [
            'name' => 'Dr Smith',
            'email' => 'smith@example.com',
            'user_id' => 55,
        ]);
        $student_id = $students->insert(['reg_no' => 'G001', 'name' => 'Export Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Export Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            55,
            $criterion_id,
            8.0,
            'submitted'
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $request->set_param('format', 'xlsx');
        $request->set_param('layout', 'rubric');
        $request->set_param('sort_key', 'reg_no');
        $request->set_param('sort_dir', 'asc');

        $response = Rest_Reports::marks_grid_download($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $headers = $response->get_headers();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) ($headers['Content-Type'] ?? '')
        );
        $disposition = (string) ($headers['Content-Disposition'] ?? '');
        $this->assertStringContainsString('marks_rubric.xlsx', $disposition);
        $this->assertNotSame('', (string) $response->get_data());
    }

    public function test_marks_grid_export_reviewer_layout_columns(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($panel_id, ['name' => 'Rev A1', 'email' => 'a1@example.com', 'user_id' => 61]);
        $panels->add_reviewer($panel_id, ['name' => 'Rev A2', 'email' => 'a2@example.com', 'user_id' => 62]);

        $student_id = $students->insert(['reg_no' => 'RF01', 'name' => 'Reviewer First Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Reviewer Layout',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
            ['label' => 'Build', 'max_marks' => 10],
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $design_id = (int) $criteria[0]['id'];
        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            61,
            $design_id,
            7.0,
            \ProjectReviews\Repositories\MarkRepository::STATUS_DRAFT
        );

        $export = (new \ProjectReviews\Services\ReportsViewService())->marks_grid_export(
            $this->session_id,
            $review_id,
            'reviewer',
            'reg_no',
            'asc'
        );

        $this->assertIsArray($export);
        $header1 = $export['rows'][0];
        $this->assertSame(
            [
                'Reg no',
                'Student',
                'Panel',
                'Panel coordinator',
                'Reviewers',
                'Attendance',
                'Status',
                'Reviewer 1',
                '',
            ],
            array_slice($header1, 0, 9)
        );
        $header2 = $export['rows'][1];
        $this->assertSame(['Design', 'Build', 'Design', 'Build'], array_slice($header2, 7, 4));
        $score_column = 7;
        $this->assertSame(7.0, $export['rows'][2][ $score_column ] );
    }

    public function test_marks_grid_download_rejects_invalid_layout(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Bad Layout',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $request->set_param('layout', 'sideways');

        $result = Rest_Reports::marks_grid_download($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_invalid_layout', $result->get_error_code());
    }

    public function test_consolidated_student_scores_csv_served_body_has_multiple_lines(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'CSV Panel');
        $student_one = $students->insert(['reg_no' => 'R1', 'name' => 'Student One']);
        $student_two = $students->insert(['reg_no' => 'R2', 'name' => 'Student Two']);
        $sessions->enrol_student($this->session_id, $student_one, $panel_id);
        $sessions->enrol_student($this->session_id, $student_two, $panel_id);
        $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);
        $request->set_param('format', 'csv');

        $response = Rest_Reports::consolidated_student_scores_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $body = $this->serve_raw_body($response);
        $lines = preg_split('/\r\n|\n|\r/', trim($body)) ?: [];
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have header plus data rows');
        $this->assertStringNotContainsString('{"', $body);
    }

    public function test_marks_grid_download_xlsx_served_body_has_zip_magic(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($panel_id, [
            'name' => 'Dr Smith',
            'email' => 'smith@example.com',
            'user_id' => 55,
        ]);
        $student_id = $students->insert(['reg_no' => 'G001', 'name' => 'Export Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Export Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            55,
            $criterion_id,
            8.0,
            'submitted'
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $request->set_param('format', 'xlsx');
        $request->set_param('layout', 'rubric');

        $response = Rest_Reports::marks_grid_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame('1', $response->get_headers()[Rest_Binary_Response::SERVE_RAW_HEADER] ?? '');

        $body = $this->serve_raw_body($response);
        $this->assertSame('PK', substr($body, 0, 2));
        $this->assertFalse(str_starts_with(ltrim($body), '{"'), 'Body must be raw XLSX bytes, not JSON-wrapped REST data');
    }

    public function test_binary_response_serves_pdf_magic_bytes(): void
    {
        $pdf = '%PDF-1.4 test';
        $response = Rest_Binary_Response::from_body($pdf, 'application/pdf', 'panel-report.pdf');

        $body = $this->serve_raw_body($response);
        $this->assertStringStartsWith('%PDF', $body);
    }

    public function test_marks_grid_includes_panel_context_and_guide_fields(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Panel Context');
        $head_reviewer_id = $panels->add_reviewer($panel_id, [
            'name' => 'Dr Head',
            'email' => 'head@example.com',
            'user_id' => 81,
        ]);
        $panels->add_reviewer($panel_id, [
            'name' => 'Dr Member',
            'email' => 'member@example.com',
            'user_id' => 82,
        ]);
        $panels->set_reviewer_panel_head($head_reviewer_id, true);

        $student_id = $students->insert([
            'reg_no' => 'PC01',
            'name' => 'Panel Context Student',
            'program' => 'B.Tech',
            'batch' => '2026',
        ]);
        $sessions->enrol_student(
            $this->session_id,
            $student_id,
            $panel_id,
            'Solar Project',
            'G-100',
            'Guide Name'
        );
        $assignments->sync_student_to_all_reviews($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Panel Context Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $reviews->replace_criteria($review_id, [
            ['label' => 'Design', 'max_marks' => 10],
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $grid = Rest_Reports::marks_grid($request);
        $student = $grid['students'][0];

        $this->assertSame('Panel Context', $student['panel_name']);
        $this->assertSame('Dr Head', $student['panel_coordinator_name']);
        $this->assertSame('Dr Head, Dr Member', $student['panel_reviewer_names']);
        $this->assertSame('G-100', $student['guide_emp_id']);
        $this->assertSame('Guide Name', $student['guide_name']);

        $scores_request = new WP_REST_Request();
        $scores_request->set_param('session_id', $this->session_id);
        $scores_request->set_param('review_id', $review_id);
        $matrix = Rest_Reports::scores_matrix($scores_request);
        $score_student = $matrix['students'][0];
        $this->assertSame('Panel Context', $score_student['panel_name']);
        $this->assertSame('Dr Head', $score_student['panel_coordinator_name']);
        $this->assertStringContainsString('Dr Member', $score_student['panel_reviewer_names']);
    }

    public function test_consolidated_scores_returns_student_grain_rows(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Consolidated Panel');
        $panels->add_reviewer($panel_id, [
            'name' => 'Consolidated Rev',
            'email' => 'consolidated@example.com',
            'user_id' => 91,
        ]);

        $student_id = $students->insert([
            'reg_no' => 'CS01',
            'name' => 'Consolidated Student',
            'program' => 'MBA',
            'batch' => '2025',
        ]);
        $sessions->enrol_student(
            $this->session_id,
            $student_id,
            $panel_id,
            'Thesis Title',
            'GE-01',
            'Guide One'
        );

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Review One',
            'sort_order' => 1,
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $assignments->seed_from_session_defaults($review_id, $this->session_id);
        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            91,
            $criterion_id,
            8.0,
            \ProjectReviews\Repositories\MarkRepository::STATUS_SUBMITTED
        );

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);

        $payload = Rest_Reports::consolidated_scores($request);

        $this->assertSame($this->session_id, $payload['session_id']);
        $this->assertCount(1, $payload['reviews']);
        $this->assertSame($review_id, $payload['reviews'][0]['review_id']);
        $this->assertCount(1, $payload['students']);

        $student = $payload['students'][0];
        $this->assertSame('CS01', $student['reg_no']);
        $this->assertSame('MBA', $student['program']);
        $this->assertSame('Thesis Title', $student['project_title']);
        $this->assertSame(8.0, $student['overall_score']);
        $this->assertCount(1, $student['reviews']);

        $review_block = $student['reviews'][0];
        $this->assertSame('Consolidated Panel', $review_block['panel_name']);
        $this->assertSame('Consolidated Rev', $review_block['panel_reviewer_names']);
        $this->assertSame(8.0, $review_block['review_score']);
    }

    public function test_consolidated_scores_download_xlsx(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Export Panel');
        $panels->add_reviewer($panel_id, [
            'name' => 'Export Rev',
            'email' => 'export@example.com',
            'user_id' => 95,
        ]);
        $student_id = $students->insert(['reg_no' => 'CE01', 'name' => 'Consolidated Export']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Export Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);
        $request->set_param('format', 'xlsx');
        $request->set_param('sort_key', 'reg_no');
        $request->set_param('sort_dir', 'asc');

        $response = Rest_Reports::consolidated_scores_download($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $disposition = (string) ($response->get_headers()['Content-Disposition'] ?? '');
        $this->assertStringContainsString('_consolidated_scores.xlsx', $disposition);
        $body = $this->serve_raw_body($response);
        $this->assertSame('PK', substr($body, 0, 2));
    }

    public function test_panel_roster_csv_two_panels_sorted_with_reviewer_slots_and_guide(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_a = $panels->create($this->session_id, 'Panel A');
        $panel_b = $panels->create($this->session_id, 'Panel B');
        $panels->add_reviewer($panel_a, ['name' => 'Rev A1', 'email' => 'a1@example.com', 'user_id' => 61]);
        $panels->add_reviewer($panel_a, ['name' => 'Rev A2', 'email' => 'a2@example.com', 'user_id' => 62]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B1', 'email' => 'b1@example.com', 'user_id' => 71]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B2', 'email' => 'b2@example.com', 'user_id' => 72]);
        $panels->add_reviewer($panel_b, ['name' => 'Rev B3', 'email' => 'b3@example.com', 'user_id' => 73]);

        $student_a = $students->insert([
            'reg_no' => 'PA01',
            'name' => 'Panel A Student',
            'program' => 'B.Tech',
            'batch' => '2026',
        ]);
        $student_b = $students->insert([
            'reg_no' => 'PB01',
            'name' => 'Panel B Student',
            'program' => 'MBA',
            'batch' => '2025',
        ]);
        $student_unassigned = $students->insert(['reg_no' => 'NO01', 'name' => 'No Panel']);
        $sessions->enrol_student(
            $this->session_id,
            $student_a,
            $panel_a,
            'Project Alpha',
            'G-A1',
            'Guide Alpha'
        );
        $sessions->enrol_student(
            $this->session_id,
            $student_b,
            $panel_b,
            'Project Beta',
            'G-B1',
            'Guide Beta'
        );
        $sessions->enrol_student($this->session_id, $student_unassigned, null);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Roster Review 1',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);
        $assignments->set_attendance_status(
            $review_id,
            $student_b,
            \ProjectReviews\Repositories\ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );

        $built = (new \ProjectReviews\Services\ReportsViewService())->panel_roster_export(
            $this->session_id,
            $review_id
        );
        $this->assertIsArray($built);
        $this->assertNotEmpty($built['merge_plan']);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $request->set_param('format', 'csv');

        $response = Rest_Reports::panel_roster_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $this->assertStringContainsString('text/csv', (string) $response->get_headers()['Content-Type']);

        $body = $this->serve_raw_body($response);
        $lines = preg_split('/\r\n|\n|\r/', trim($body)) ?: [];
        $this->assertCount(3, $lines, 'Header plus two panel-assigned students');

        $header = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertSame('Review number', $header[0]);
        $this->assertSame('Guide emp. ID', $header[8]);
        $this->assertSame('Reviewer 1', $header[11]);
        $this->assertSame('Reviewer 2', $header[12]);
        $this->assertSame('Reviewer 3', $header[13]);

        $row_a = str_getcsv($lines[1], ',', '"', '\\');
        $row_b = str_getcsv($lines[2], ',', '"', '\\');
        $this->assertSame('Roster Review 1', $row_a[0]);
        $this->assertSame('Panel A', $row_a[1]);
        $this->assertSame('PA01', $row_a[3]);
        $this->assertSame('G-A1', $row_a[8]);
        $this->assertSame('Guide Alpha', $row_a[9]);
        $this->assertSame('Present', $row_a[10]);
        $this->assertSame('Rev A1', $row_a[11]);
        $this->assertSame('Rev A2', $row_a[12]);
        $this->assertSame('', $row_a[13]);

        $this->assertSame('Panel B', $row_b[1]);
        $this->assertSame('PB01', $row_b[3]);
        $this->assertSame('G-B1', $row_b[8]);
        $this->assertSame('Absent', $row_b[10]);
        $this->assertSame('Rev B1', $row_b[11]);
        $this->assertSame('Rev B2', $row_b[12]);
        $this->assertSame('Rev B3', $row_b[13]);
    }

    public function test_panel_roster_download_allowed_for_unconfirmed_review(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Draft Panel');
        $panels->add_reviewer($panel_id, ['name' => 'Draft Rev', 'email' => 'draft@example.com', 'user_id' => 88]);
        $student_id = $students->insert(['reg_no' => 'DR01', 'name' => 'Draft Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Draft Roster Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_DRAFT,
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $request->set_param('format', 'csv');

        $response = Rest_Reports::panel_roster_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $body = $this->serve_raw_body($response);
        $this->assertStringContainsString('Draft Roster Review', $body);
        $this->assertStringContainsString('DR01', $body);
    }

    public function test_panel_roster_xlsx_download_has_zip_magic(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Xlsx Panel');
        $panels->add_reviewer($panel_id, ['name' => 'Xlsx Rev', 'email' => 'xlsx@example.com', 'user_id' => 77]);
        $student_id = $students->insert(['reg_no' => 'XL01', 'name' => 'Xlsx Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Xlsx Roster',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);
        $request->set_param('format', 'xlsx');

        $response = Rest_Reports::panel_roster_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $body = $this->serve_raw_body($response);
        $this->assertSame('PK', substr($body, 0, 2));
    }

    public function test_offline_scoring_sheet_pdf_returns_pdf_without_panel_head(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('dompdf not installed');
        }

        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Offline Panel');
        $panels->add_reviewer($panel_id, ['name' => 'Offline Rev', 'email' => 'off@example.com', 'user_id' => 501]);
        $student_id = $students->insert(['reg_no' => 'OF01', 'name' => 'Offline Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Offline Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
        ]);
        $criterion_id = (int) ($criteria[0]['id'] ?? 0);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $marks->upsert(
            $this->session_id,
            $review_id,
            $student_id,
            501,
            $criterion_id,
            8.5,
            \ProjectReviews\Repositories\MarkRepository::STATUS_SUBMITTED
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $review_id);

        $response = Rest_Reports::offline_scoring_sheet_review_pdf($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());
        $disposition = (string) ($response->get_headers()['Content-Disposition'] ?? '');
        $this->assertStringContainsString('offline-scoring-sheet', $disposition);

        $body = $this->serve_raw_body($response);
        $this->assertStringStartsWith('%PDF', $body);

        $report = (new \ProjectReviews\Services\ReportsViewService())->scores_matrix_for_panel(
            $this->session_id,
            $review_id,
            $panel_id
        );
        $this->assertIsArray($report);
        $report['offline_scoring'] = true;
        $context = (new \ProjectReviews\Services\PanelReportPdfContextBuilder())->build(
            $report,
            \ProjectReviews\Services\PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING,
            \ProjectReviews\Services\PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_OVERALL
        );
        $html = (new \ProjectReviews\Services\PanelReportPdfService())->build_html($context);
        $this->assertStringContainsString('Overall Review Report', $html);
        $this->assertStringContainsString('>R1</th>', $html);
        $this->assertStringNotContainsString('8.50', $html);
        $this->assertStringNotContainsString('Final Marks', $html);
    }

    public function test_consolidated_student_scores_csv_column_count_two_reviews_rubrics_slots(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $this->seed_consolidated_student_full_export_fixture();

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);
        $request->set_param('format', 'csv');

        $response = Rest_Reports::consolidated_student_scores_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $body = $this->serve_raw_body($response);
        $lines = preg_split('/\r\n|\n|\r/', trim($body));
        $this->assertNotFalse($lines);
        $header = str_getcsv($lines[0], ',', '"', '\\');
        $review_count = 2;
        $criteria_count = 2;
        $slot_count = 2;
        $expected = 6 + $review_count * (3 + $slot_count * (1 + $criteria_count) + 2) + 1;
        $this->assertSame($expected, count($header), 'Flattened CSV column count must match hierarchical formula');
        $this->assertStringContainsString(
            'Review Alpha | Reviewer 1 | Criterion A',
            $lines[0]
        );
        $this->assertStringContainsString('Combined score', $lines[0]);

        $data_row = str_getcsv($lines[1], ',', '"', '\\');
        $this->assertSame('FULL01', $data_row[0]);
        $this->assertNotSame('', $data_row[count($data_row) - 1], 'Combined score should be populated');
    }

    public function test_consolidated_student_scores_absent_student_empty_rubric_cells(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Absent Panel');
        $panels->add_reviewer($panel_id, ['name' => 'Absent Rev', 'email' => 'absent@example.com', 'user_id' => 301]);

        $student_id = $students->insert(['reg_no' => 'ABS01', 'name' => 'Absent Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        $review_id = $reviews->create($this->session_id, [
            'label' => 'Absent Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $reviews->replace_criteria($review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);
        $assignments->set_attendance_status(
            $review_id,
            $student_id,
            \ProjectReviews\Repositories\ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );

        $built = (new \ProjectReviews\Services\ReportsViewService())->consolidated_student_export($this->session_id);
        $this->assertIsArray($built);
        $header = $built['csv_rows'][0];
        $data_row = $built['csv_rows'][1];
        $slot_total_path = 'Absent Review | Reviewer 1 | Total';
        $slot_total_index = array_search($slot_total_path, $header, true);
        $this->assertNotFalse($slot_total_index, 'Expected CSV header for reviewer slot total');
        $this->assertSame('', $data_row[$slot_total_index] ?? null);
    }

    public function test_consolidated_student_export_sheet_header_data_column_parity(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $this->seed_consolidated_student_full_export_fixture();
        $built = (new \ProjectReviews\Services\ReportsViewService())->consolidated_student_export($this->session_id);
        $this->assertIsArray($built);

        $rows = $built['rows'];
        $preface_row_count = (int) ($built['styles']['preface_row_count'] ?? 0);
        $header_row_count = (int) ($built['styles']['header_row_count'] ?? 2);
        $this->assertGreaterThan(0, $preface_row_count, 'XLSX export should include a project metadata preface');

        $last_header_index = $preface_row_count + $header_row_count - 1;
        $expected_width = count($rows[$last_header_index]);

        foreach ($rows as $index => $row) {
            if ($index <= $last_header_index) {
                continue;
            }
            $this->assertSame(
                $expected_width,
                count($row),
                sprintf('Data row %d must have the same column count as the header row', $index + 1)
            );
        }
    }

    public function test_consolidated_student_export_mark_aligns_with_csv_header_path(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $this->seed_consolidated_student_full_export_fixture();
        $built = (new \ProjectReviews\Services\ReportsViewService())->consolidated_student_export($this->session_id);
        $this->assertIsArray($built);

        $header = $built['csv_rows'][0];
        $target = 'Review Alpha | Reviewer 1 | Criterion A';
        $column_index = array_search($target, $header, true);
        $this->assertNotFalse($column_index, 'Expected flattened CSV header path for Review Alpha reviewer 1 criterion A');

        $data_row = $built['csv_rows'][1];
        $this->assertSame('7', (string) ($data_row[$column_index] ?? ''), 'First review first slot first criterion mark should be 7.0');

        $header = $built['csv_rows'][0];
        $ix_a = array_search('Review Alpha | Reviewer 1 | Criterion A', $header, true);
        $ix_b = array_search('Review Alpha | Reviewer 1 | Criterion B', $header, true);
        $ix_total = array_search('Review Alpha | Reviewer 1 | Total', $header, true);
        $this->assertNotFalse($ix_total);
        $this->assertNotFalse($ix_a);
        $this->assertNotFalse($ix_b);
        $this->assertLessThan($ix_total, $ix_a, 'Rubric columns should precede slot total');
        $this->assertLessThan($ix_total, $ix_b, 'Rubric columns should precede slot total');
    }

    public function test_consolidated_student_scores_xlsx_download(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_VIEW_REPORTS);

        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Xlsx Panel');
        $panels->add_reviewer($panel_id, ['name' => 'Xlsx Rev', 'email' => 'xlsx@example.com', 'user_id' => 401]);
        $student_id = $students->insert(['reg_no' => 'XLS01', 'name' => 'Xlsx Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);
        $review_id = $reviews->create($this->session_id, [
            'label' => 'Xlsx Review',
            'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
        ]);
        $assignments->seed_from_session_defaults($review_id, $this->session_id);

        $request = new WP_REST_Request();
        $request->set_param('id', $this->session_id);
        $request->set_param('format', 'xlsx');

        $response = Rest_Reports::consolidated_student_scores_download($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $disposition = (string) ($response->get_headers()['Content-Disposition'] ?? '');
        $this->assertStringContainsString('_consolidated_student_scores.xlsx', $disposition);
        $body = $this->serve_raw_body($response);
        $this->assertSame('PK', substr($body, 0, 2));
    }

    private function seed_consolidated_student_full_export_fixture(): void
    {
        $reviews = new \ProjectReviews\Repositories\ReviewRepository($this->wpdb);
        $students = new \ProjectReviews\Repositories\StudentRepository($this->wpdb);
        $sessions = new \ProjectReviews\Repositories\SessionRepository($this->wpdb);
        $panels = new \ProjectReviews\Repositories\PanelRepository($this->wpdb);
        $marks = new \ProjectReviews\Repositories\MarkRepository($this->wpdb);
        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($this->wpdb);

        $panel_id = $panels->create($this->session_id, 'Full Export Panel');
        $panels->add_reviewer($panel_id, ['name' => 'Slot Rev 1', 'email' => 's1@example.com', 'user_id' => 201]);
        $panels->add_reviewer($panel_id, ['name' => 'Slot Rev 2', 'email' => 's2@example.com', 'user_id' => 202]);

        $student_id = $students->insert(['reg_no' => 'FULL01', 'name' => 'Full Export Student']);
        $sessions->enrol_student($this->session_id, $student_id, $panel_id);

        foreach (['Review Alpha', 'Review Beta'] as $index => $label) {
            $review_id = $reviews->create($this->session_id, [
                'label' => $label,
                'sort_order' => $index + 1,
                'status' => \ProjectReviews\Repositories\ReviewRepository::STATUS_CONFIRMED,
            ]);
            $criteria = $reviews->replace_criteria($review_id, [
                ['label' => 'Criterion A', 'max_marks' => 10],
                ['label' => 'Criterion B', 'max_marks' => 10],
            ]);
            $assignments->seed_from_session_defaults($review_id, $this->session_id);
            foreach ($criteria as $criterion) {
                $criterion_id = (int) ($criterion['id'] ?? 0);
                $marks->upsert(
                    $this->session_id,
                    $review_id,
                    $student_id,
                    201,
                    $criterion_id,
                    7.0 + $index,
                    \ProjectReviews\Repositories\MarkRepository::STATUS_SUBMITTED
                );
                $marks->upsert(
                    $this->session_id,
                    $review_id,
                    $student_id,
                    202,
                    $criterion_id,
                    6.0 + $index,
                    \ProjectReviews\Repositories\MarkRepository::STATUS_SUBMITTED
                );
            }
        }
    }

    /**
     * @return string Raw response body as sent to the client (not JSON-wrapped).
     */
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

        $this->assertTrue($served, 'rest_pre_serve_request should serve raw binary body');

        return $body;
    }
}
