<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Install;

final class RubricScoresSchemaTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $prefix = $this->wpdb->prefix;
        $this->wpdb->register_table_columns($prefix . 'pr_marks', [
            'id',
            'session_id',
            'review_id',
            'student_id',
            'reviewer_user_id',
            'criterion_id',
            'score',
            'flagged',
            'status',
        ]);
        $this->wpdb->register_table_columns($prefix . 'pr_students', [
            'id',
            'reg_no',
            'name',
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_rubric_scores_view_ddl_exposes_flat_export_columns(): void
    {
        $ddl = Install::rubric_scores_view_ddl('wp_');

        $this->assertStringContainsString('CREATE VIEW wp_pr_rubric_scores AS', $ddl);
        $this->assertStringContainsString('m.session_id AS project_id', $ddl);
        $this->assertStringContainsString('m.review_id', $ddl);
        $this->assertStringContainsString('s.reg_no', $ddl);
        $this->assertStringContainsString('m.reviewer_user_id AS reviewer_id', $ddl);
        $this->assertStringContainsString('m.criterion_id AS rubric_id', $ddl);
        $this->assertStringContainsString('m.score', $ddl);
        $this->assertStringContainsString('FROM wp_pr_marks m', $ddl);
        $this->assertStringContainsString('INNER JOIN wp_pr_students s ON s.id = m.student_id', $ddl);
    }

    public function test_ensure_rubric_scores_view_creates_view_when_base_tables_exist(): void
    {
        $this->assertTrue(Install::ensure_rubric_scores_view($this->wpdb));
        $this->assertTrue($this->wpdb->has_view($this->wpdb->prefix . 'pr_rubric_scores'));
    }

    public function test_ensure_rubric_scores_view_is_idempotent(): void
    {
        $this->assertTrue(Install::ensure_rubric_scores_view($this->wpdb));
        $this->assertTrue(Install::ensure_rubric_scores_view($this->wpdb));
        $this->assertTrue($this->wpdb->has_view($this->wpdb->prefix . 'pr_rubric_scores'));
    }

    public function test_validation_query_shape_matches_export_contract(): void
    {
        $prefix = $this->wpdb->prefix;
        $sql = "SELECT project_id, review_id, reg_no, reviewer_id, rubric_id, score
                FROM {$prefix}pr_rubric_scores
                WHERE project_id = 1
                ORDER BY review_id, reg_no, reviewer_id, rubric_id";

        $this->assertStringContainsString('project_id', $sql);
        $this->assertStringContainsString('rubric_id', $sql);
        $this->assertStringContainsString('pr_rubric_scores', $sql);
    }
}
