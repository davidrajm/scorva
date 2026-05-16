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
        foreach (['pr_students', 'pr_sessions', 'pr_panels', 'pr_reviews', 'pr_marks', 'pr_mark_audit'] as $table) {
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
            'pr_reviews', 'pr_rubric_criteria', 'pr_review_weights',
            'pr_reviewer_weights', 'pr_marks', 'pr_mark_audit', 'pr_session_reviewers',
        ];
        foreach ($tables as $table) {
            $this->assertStringContainsString($table, $sql, "Missing table: {$table}");
        }
    }
}
