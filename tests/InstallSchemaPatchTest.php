<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Install;

final class InstallSchemaPatchTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->wpdb->register_table_columns(
            $this->wpdb->prefix . 'pr_panel_reviewers',
            ['id', 'panel_id', 'email', 'user_id', 'weight']
        );
        $prefix = $this->wpdb->prefix;
        $this->wpdb->register_table_columns($prefix . 'pr_marks', [
            'id', 'session_id', 'review_id', 'student_id', 'reviewer_user_id', 'criterion_id', 'score',
        ]);
        $this->wpdb->register_table_columns($prefix . 'pr_students', ['id', 'reg_no', 'name']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_ensure_panel_reviewer_name_column_adds_missing_column(): void
    {
        $table = $this->wpdb->prefix . 'pr_panel_reviewers';

        $this->assertTrue(Install::ensure_panel_reviewer_name_column($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($table, 'name'));

        $this->assertTrue(Install::ensure_panel_reviewer_name_column($this->wpdb));
    }

    public function test_ensure_schema_patches_creates_rubric_scores_view(): void
    {
        Install::ensure_schema_patches();
        $this->assertTrue($this->wpdb->has_view($this->wpdb->prefix . 'pr_rubric_scores'));
    }

    public function test_ensure_core_tables_runs_dbdelta_when_sessions_missing(): void
    {
        $GLOBALS['pr_test_dbdelta_calls'] = [];

        $this->assertFalse(Install::ensure_core_tables($this->wpdb));
        $this->assertGreaterThanOrEqual(1, count($GLOBALS['pr_test_dbdelta_calls']));
        $this->assertStringContainsString('pr_sessions', $GLOBALS['pr_test_dbdelta_calls'][0]);
    }

    public function test_ensure_core_tables_skips_dbdelta_when_sessions_exist(): void
    {
        $GLOBALS['pr_test_dbdelta_calls'] = [];
        $this->wpdb->register_table_columns($this->wpdb->prefix . 'pr_sessions', ['id', 'title', 'status']);

        $this->assertTrue(Install::ensure_core_tables($this->wpdb));
        $this->assertSame([], $GLOBALS['pr_test_dbdelta_calls']);
    }

    public function test_ensure_unfreeze_requests_table_runs_dbdelta_when_missing(): void
    {
        $GLOBALS['pr_test_dbdelta_calls'] = [];
        $this->assertFalse(Install::ensure_unfreeze_requests_table($this->wpdb));
        $this->assertGreaterThanOrEqual(1, count($GLOBALS['pr_test_dbdelta_calls']));
        $this->assertStringContainsString('pr_unfreeze_requests', $GLOBALS['pr_test_dbdelta_calls'][0]);
    }

    public function test_ensure_panel_head_columns_adds_missing_columns(): void
    {
        $session_table = $this->wpdb->prefix . 'pr_panel_reviewers';
        $review_table = $this->wpdb->prefix . 'pr_review_panel_reviewers';
        $this->wpdb->register_table_columns($session_table, [
            'id', 'panel_id', 'name', 'email', 'user_id', 'weight',
        ]);
        $this->wpdb->register_table_columns($review_table, [
            'id', 'review_id', 'panel_id', 'user_id', 'weight',
        ]);

        $this->assertTrue(Install::ensure_panel_head_columns($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($session_table, 'is_panel_head'));
        $this->assertTrue($this->wpdb->has_column($review_table, 'is_panel_head'));
    }

    public function test_ensure_coordinator_marks_locked_column_adds_missing_column(): void
    {
        $table = $this->wpdb->prefix . 'pr_reviews';
        $this->wpdb->register_table_columns($table, [
            'id', 'session_id', 'label', 'sort_order', 'status', 'marking_active',
        ]);

        $this->assertTrue(Install::ensure_coordinator_marks_locked_column($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($table, 'coordinator_marks_locked'));
        $this->assertTrue(Install::ensure_coordinator_marks_locked_column($this->wpdb));
    }

    public function test_ensure_unfreeze_request_reason_column_adds_missing_column(): void
    {
        $table = $this->wpdb->prefix . 'pr_unfreeze_requests';
        $this->wpdb->register_table_columns($table, [
            'id', 'session_id', 'review_id', 'panel_id', 'reviewer_user_id', 'status', 'requested_at',
        ]);

        $this->assertTrue(Install::ensure_unfreeze_request_reason_column($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($table, 'reason'));
    }

    public function test_ensure_attendance_status_column_adds_missing_column(): void
    {
        $table = $this->wpdb->prefix . 'pr_review_student_panels';
        $this->wpdb->register_table_columns($table, ['id', 'review_id', 'student_id', 'panel_id']);

        $this->assertTrue(Install::ensure_attendance_status_column($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($table, 'attendance_status'));

        $this->assertTrue(Install::ensure_attendance_status_column($this->wpdb));
    }

    public function test_ensure_project_title_columns_adds_missing_columns(): void
    {
        $enrolment = $this->wpdb->prefix . 'pr_session_students';
        $assignments = $this->wpdb->prefix . 'pr_review_student_panels';
        $this->wpdb->register_table_columns($enrolment, [
            'id', 'session_id', 'student_id', 'panel_id',
        ]);
        $this->wpdb->register_table_columns($assignments, [
            'id', 'review_id', 'student_id', 'panel_id', 'attendance_status',
        ]);

        $this->assertTrue(Install::ensure_project_title_columns($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($enrolment, 'project_title'));
        $this->assertTrue($this->wpdb->has_column($assignments, 'project_title'));
        $this->assertTrue(Install::ensure_project_title_columns($this->wpdb));
    }

    public function test_ensure_student_program_column_adds_missing_column(): void
    {
        $table = $this->wpdb->prefix . 'pr_students';
        $this->wpdb->register_table_columns($table, [
            'id', 'reg_no', 'name', 'batch', 'guide_name', 'created_at', 'updated_at',
        ]);

        $this->assertTrue(Install::ensure_student_program_column($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($table, 'program'));
        $this->assertTrue(Install::ensure_student_program_column($this->wpdb));
    }

    public function test_ensure_enrolment_guide_columns_adds_missing_columns(): void
    {
        $table = $this->wpdb->prefix . 'pr_session_students';
        $this->wpdb->register_table_columns($table, [
            'id', 'session_id', 'student_id', 'panel_id', 'project_title',
        ]);

        $this->assertTrue(Install::ensure_enrolment_guide_columns($this->wpdb));
        $this->assertTrue($this->wpdb->has_column($table, 'guide_emp_id'));
        $this->assertTrue($this->wpdb->has_column($table, 'guide_name'));
    }

    public function test_backfill_guide_name_to_enrolments_copies_registry_guide(): void
    {
        $prefix = $this->wpdb->prefix;
        $students = $prefix . 'pr_students';
        $enrolment = $prefix . 'pr_session_students';
        $this->wpdb->register_table_columns($students, [
            'id', 'reg_no', 'name', 'batch', 'guide_name',
        ]);
        $this->wpdb->register_table_columns($enrolment, [
            'id', 'session_id', 'student_id', 'panel_id', 'guide_name',
        ]);
        $this->wpdb->insert($students, [
            'id' => 1,
            'reg_no' => 'R1',
            'name' => 'Ada',
            'guide_name' => 'Dr. Legacy',
        ]);
        $this->wpdb->insert($enrolment, [
            'id' => 1,
            'session_id' => 1,
            'student_id' => 1,
            'panel_id' => 1,
            'guide_name' => '',
        ]);

        Install::backfill_guide_name_to_enrolments($this->wpdb);

        $row = $this->wpdb->get_row(
            "SELECT * FROM {$enrolment} WHERE id = 1",
            'ARRAY_A'
        );
        $this->assertIsArray($row);
        $this->assertSame('Dr. Legacy', $row['guide_name']);
    }

    public function test_drop_students_guide_name_column_removes_column(): void
    {
        $table = $this->wpdb->prefix . 'pr_students';
        $this->wpdb->register_table_columns($table, [
            'id', 'reg_no', 'name', 'batch', 'guide_name',
        ]);

        $this->assertTrue(Install::drop_students_guide_name_column($this->wpdb));
        $this->assertFalse($this->wpdb->has_column($table, 'guide_name'));
    }

    public function test_ensure_review_student_attendance_by_reviewer_table_runs_dbdelta_when_missing(): void
    {
        $GLOBALS['pr_test_dbdelta_calls'] = [];
        $this->assertFalse(Install::ensure_review_student_attendance_by_reviewer_table($this->wpdb));
        $this->assertGreaterThanOrEqual(1, count($GLOBALS['pr_test_dbdelta_calls']));
        $this->assertStringContainsString(
            'pr_review_student_attendance_by_reviewer',
            $GLOBALS['pr_test_dbdelta_calls'][0]
        );
    }
}
