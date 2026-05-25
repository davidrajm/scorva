<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\ExportService;
use ProjectReviews\Services\ReportQueryService;

final class ReportQueryServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_sessions", [
            'id' => $this->session_id,
            'title' => 'Export Session',
            'status' => 'active',
        ]);
        $this->wpdb->insert("{$prefix}pr_panels", [
            'id' => 1,
            'session_id' => $this->session_id,
            'name' => 'Panel A',
        ]);
        $this->wpdb->insert("{$prefix}pr_students", [
            'id' => 10,
            'reg_no' => 'R1',
            'name' => 'Student One',
            'program' => 'MSC-DS',
            'batch' => '2026',
        ]);
        $this->wpdb->insert("{$prefix}pr_session_students", [
            'session_id' => $this->session_id,
            'student_id' => 10,
            'panel_id' => 1,
            'guide_name' => 'Dr. Guide',
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_student_master_has_panel_merge_plan(): void
    {
        $service = new ReportQueryService($this->wpdb);
        $built = $service->build(ReportQueryService::TYPE_STUDENT_MASTER, $this->session_id);

        $this->assertSame('student_master', $built['type']);
        $this->assertCount(2, $built['rows']);
        $this->assertSame(
            ['Panel', 'Reg No', 'Name', 'Program', 'Batch', 'Guide'],
            $built['rows'][0]
        );
        $this->assertSame('Dr. Guide', $built['rows'][1][5]);
        $this->assertSame('MSC-DS', $built['rows'][1][3]);
    }

    public function test_marks_detail_defines_multi_column_merge(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_reviews", [
            'id' => 5,
            'session_id' => $this->session_id,
            'label' => 'Review 1',
        ]);
        $this->wpdb->insert("{$prefix}pr_rubric_criteria", [
            'id' => 50,
            'review_id' => 5,
            'label' => 'Quality',
            'max_marks' => 10,
        ]);
        $this->wpdb->insert("{$prefix}pr_rubric_criteria", [
            'id' => 51,
            'review_id' => 5,
            'label' => 'Delivery',
            'max_marks' => 10,
        ]);
        $this->wpdb->insert("{$prefix}pr_marks", [
            'session_id' => $this->session_id,
            'review_id' => 5,
            'student_id' => 10,
            'reviewer_user_id' => 99,
            'criterion_id' => 50,
            'score' => 8,
            'status' => 'submitted',
        ]);
        $this->wpdb->insert("{$prefix}pr_marks", [
            'session_id' => $this->session_id,
            'review_id' => 5,
            'student_id' => 10,
            'reviewer_user_id' => 99,
            'criterion_id' => 51,
            'score' => 9,
            'status' => 'submitted',
        ]);

        $service = new ReportQueryService($this->wpdb);
        $built = $service->build(ReportQueryService::TYPE_MARKS_DETAIL, $this->session_id);

        $this->assertGreaterThan(1, count($built['rows']));
        $this->assertNotEmpty($built['merge_plan']);
    }

    public function test_rubric_scores_returns_flat_id_columns(): void
    {
        $prefix = $this->wpdb->prefix;
        $this->wpdb->insert("{$prefix}pr_reviews", [
            'id' => 5,
            'session_id' => $this->session_id,
            'label' => 'Review 1',
        ]);
        $this->wpdb->insert("{$prefix}pr_rubric_criteria", [
            'id' => 50,
            'review_id' => 5,
            'label' => 'Quality',
            'max_marks' => 10,
        ]);
        $this->wpdb->insert("{$prefix}pr_rubric_criteria", [
            'id' => 51,
            'review_id' => 5,
            'label' => 'Delivery',
            'max_marks' => 10,
        ]);
        $this->wpdb->insert("{$prefix}pr_marks", [
            'session_id' => $this->session_id,
            'review_id' => 5,
            'student_id' => 10,
            'reviewer_user_id' => 99,
            'criterion_id' => 50,
            'score' => 8,
            'status' => 'submitted',
            'flagged' => 1,
        ]);
        $this->wpdb->insert("{$prefix}pr_marks", [
            'session_id' => $this->session_id,
            'review_id' => 5,
            'student_id' => 10,
            'reviewer_user_id' => 99,
            'criterion_id' => 51,
            'score' => 9,
            'status' => 'submitted',
        ]);

        $service = new ReportQueryService($this->wpdb);
        $built = $service->build(ReportQueryService::TYPE_RUBRIC_SCORES, $this->session_id);

        $this->assertSame('rubric_scores', $built['type']);
        $this->assertSame([], $built['merge_plan']);
        $this->assertSame(['freeze_row' => 1, 'numeric_columns' => [5]], $built['styles']);
        $this->assertCount(3, $built['rows']);
        $this->assertSame(
            [
                'Project ID',
                'Review ID',
                'Reg No',
                'Reviewer ID',
                'Rubric ID',
                'Score',
                'Status',
                'Flagged',
                'Coordinator override',
                'Previous score',
            ],
            $built['rows'][0]
        );
        $this->assertSame(
            [$this->session_id, 5, 'R1', 99, 50, 8.0, 'submitted', 'Yes', 'No', null],
            $built['rows'][1]
        );
        $this->assertSame(
            [$this->session_id, 5, 'R1', 99, 51, 9.0, 'submitted', 'No', 'No', null],
            $built['rows'][2]
        );
    }
}
