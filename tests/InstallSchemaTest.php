<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Install;

final class InstallSchemaTest extends TestCase
{
    public function test_schema_includes_core_tables(): void
    {
        $sql = Install::get_schema_sql();
        foreach (['pr_students', 'pr_sessions', 'pr_panels', 'pr_reviews', 'pr_marks', 'pr_mark_audit', 'pr_unfreeze_requests'] as $table) {
            $this->assertStringContainsString($table, $sql);
        }
    }

    public function test_students_table_has_reg_no_unique_constraint(): void
    {
        $sql = Install::get_schema_sql();
        $this->assertStringContainsString('pr_students', $sql);
        $this->assertStringContainsString('UNIQUE KEY reg_no (reg_no)', $sql);
    }

    public function test_registry_tables_defined(): void
    {
        $sql = Install::get_schema_sql();
        foreach (['pr_students', 'pr_field_definitions', 'pr_student_meta'] as $table) {
            $this->assertStringContainsString($table, $sql);
        }
    }

    public function test_schema_includes_all_mvp_tables(): void
    {
        $sql = Install::get_schema_sql();
        $tables = [
            'pr_students', 'pr_field_definitions', 'pr_student_meta',
            'pr_sessions', 'pr_session_students', 'pr_panels',
            'pr_panel_reviewers', 'pr_review_reviewer_overrides',
            'pr_reviews', 'pr_review_student_panels', 'pr_review_student_attendance_by_reviewer',
            'pr_review_panel_reviewers',
            'pr_rubric_criteria', 'pr_review_weights',
            'pr_reviewer_weights', 'pr_marks', 'pr_mark_audit', 'pr_unfreeze_requests',
            'pr_session_reviewers',
        ];
        foreach ($tables as $table) {
            $this->assertStringContainsString($table, $sql, "Missing table: {$table}");
        }
    }

    public function test_rubric_scores_view_ddl_is_defined(): void
    {
        $ddl = Install::rubric_scores_view_ddl('wp_');
        $this->assertStringContainsString('wp_pr_rubric_scores', $ddl);
        $this->assertStringContainsString('AS project_id', $ddl);
        $this->assertStringContainsString('AS rubric_id', $ddl);
    }

    public function test_review_student_panels_includes_attendance_status(): void
    {
        $sql = Install::get_schema_sql();
        $this->assertStringContainsString('pr_review_student_panels', $sql);
        $this->assertStringContainsString('attendance_status', $sql);
    }

    public function test_unfreeze_requests_includes_reason(): void
    {
        $sql = Install::get_schema_sql();
        $this->assertStringContainsString('pr_unfreeze_requests', $sql);
        $this->assertStringContainsString('reason text', $sql);
    }

    public function test_review_student_attendance_by_reviewer_table_defined(): void
    {
        $sql = Install::get_schema_sql();
        $this->assertStringContainsString('pr_review_student_attendance_by_reviewer', $sql);
        $this->assertStringContainsString('review_student_reviewer', $sql);
    }
}
