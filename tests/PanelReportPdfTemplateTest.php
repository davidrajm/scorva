<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Services\PanelReportPdfContextBuilder;
use ProjectReviews\Services\PanelReportPdfService;
use ProjectReviews\Services\SessionPanelReportSettings;

final class PanelReportPdfTemplateTest extends TestCase
{
    protected function tearDown(): void
    {
        if (function_exists('delete_option')) {
            delete_option(SessionPanelReportSettings::option_key(1));
        }
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function sample_report(): array
    {
        return [
            'session_id' => 1,
            'session_title' => 'Demo Project',
            'review_id' => 2,
            'review_label' => 'Review 1',
            'panel_id' => 3,
            'panel_name' => 'Panel A',
            'reviewers' => [
                [
                    'user_id' => 10,
                    'name' => 'Head Reviewer',
                    'ordinal' => 1,
                    'is_panel_coordinator' => true,
                ],
                [
                    'user_id' => 11,
                    'name' => 'Peer Reviewer',
                    'ordinal' => 2,
                    'is_panel_coordinator' => false,
                ],
            ],
            'students' => [
                [
                    'student_id' => 100,
                    'sr_no' => 1,
                    'reg_no' => 'S001',
                    'name' => 'Alice',
                    'guide_name' => 'Dr. Guide',
                    'project_title' => 'Smart Campus',
                    'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_PRESENT,
                    'attendance_label' => 'Present',
                    'reviewer_totals' => [
                        '10' => ['score' => 8.5, 'draft' => false],
                        '11' => ['score' => 7.0, 'draft' => false],
                    ],
                    'review_score' => 7.75,
                ],
                [
                    'student_id' => 101,
                    'sr_no' => 2,
                    'reg_no' => 'S002',
                    'name' => 'Bob',
                    'guide_name' => 'Dr. Other',
                    'project_title' => 'IoT Lab',
                    'attendance_status' => ReviewAssignmentRepository::ATTENDANCE_ABSENT,
                    'attendance_label' => 'Absent',
                    'reviewer_totals' => [
                        '10' => null,
                        '11' => null,
                    ],
                    'review_score' => null,
                ],
            ],
        ];
    }

    public function test_html_includes_borders_reviewer_headers_and_attendance(): void
    {
        $report = $this->sample_report();
        $context = (new PanelReportPdfContextBuilder())->build($report);
        $html = (new PanelReportPdfService())->build_html($context);

        $this->assertStringContainsString('border: 1.00pt solid #000000', $html);
        $this->assertStringContainsString('>R1</th>', $html);
        $this->assertStringContainsString('>R2</th>', $html);
        $this->assertStringNotContainsString('<th>Head Reviewer</th>', $html);
        $this->assertStringContainsString('Review Report', $html);
        $this->assertStringContainsString('Review Number', $html);
        $this->assertStringContainsString('>P<', $html);
        $this->assertStringContainsString('>A<', $html);
        $this->assertStringContainsString('Final Marks', $html);
        $this->assertStringContainsString('Sr. No.', $html);
        $this->assertStringContainsString('class="meta-table"', $html);
        $this->assertStringNotContainsString('Legend:', $html);
        $this->assertStringContainsString('R1 = Reviewer 1', $html);
    }

    public function test_signature_dedupes_coordinator_in_roster(): void
    {
        $report = $this->sample_report();
        $context = (new PanelReportPdfContextBuilder())->build($report);
        $html = (new PanelReportPdfService())->build_html($context);

        $this->assertStringContainsString('Signatures with date', $html);
        $this->assertStringNotContainsString('Panel coordinator', $html);
        $this->assertSame(3, substr_count($html, 'class="sig-row"'));
        $this->assertStringContainsString('Reviewer 1', $html);
        $this->assertStringContainsString('Reviewer 2', $html);
        $this->assertStringContainsString('Reviewer 1: Head Reviewer', $html);
        $this->assertStringContainsString('Reviewer 2: Peer Reviewer', $html);
        $this->assertStringNotContainsString('class="sig-name"', $html);
        $this->assertStringContainsString('Head of the Department', $html);
    }

    public function test_coordinator_line_when_not_in_roster(): void
    {
        $report = $this->sample_report();
        $report['coordinator_user_id'] = 10;
        $report['reviewers'] = [
            [
                'user_id' => 11,
                'name' => 'Peer Reviewer',
                'ordinal' => 1,
                'is_panel_coordinator' => false,
            ],
        ];
        $report['students'][0]['reviewer_totals'] = ['11' => ['score' => 7.0, 'draft' => false]];
        unset($report['students'][0]['reviewer_totals']['10']);
        $report['students'][1]['reviewer_totals'] = ['11' => null];

        $context = (new PanelReportPdfContextBuilder())->build($report);
        $lines = $context['signature_lines'];
        $labels = array_map(static fn (array $line): string => (string) $line['label'], $lines);

        $this->assertContains('Panel coordinator', $labels);
        $this->assertContains('Reviewer 1', $labels);
        $this->assertNotContains('Reviewer 2', $labels);
    }

    public function test_offline_reviewer_sheet_has_single_score_column_and_one_signature_line(): void
    {
        $report = $this->sample_report();
        $builder = new PanelReportPdfContextBuilder();
        $context = $builder->build(
            $report,
            PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING,
            PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_REVIEWER,
            1
        );
        $html = (new PanelReportPdfService())->build_html($context);

        $this->assertFalse($context['render_scores']);
        $this->assertStringContainsString('Reviewer 1 — Scoring Sheet', $html);
        $this->assertStringContainsString('>Score</th>', $html);
        $this->assertStringNotContainsString('>R1</th>', $html);
        $this->assertStringNotContainsString('>R2</th>', $html);
        $this->assertStringNotContainsString('Final Marks', $html);
        $this->assertStringNotContainsString('Overall score', $html);
        $this->assertStringNotContainsString('8.50', $html);
        $this->assertStringNotContainsString('>—<', $html);
        $this->assertStringNotContainsString('R1 = Reviewer 1', $html);
        $this->assertStringContainsString('Reviewer 1: Head Reviewer', $html);
        $this->assertStringNotContainsString('Reviewer 2', $html);
        $this->assertSame(2, substr_count($html, 'class="sig-row"'));
        $this->assertStringContainsString('min-width: 3em', $html);
    }

    public function test_offline_overall_sheet_has_reviewer_columns_and_overall_score(): void
    {
        $report = $this->sample_report();
        $context = (new PanelReportPdfContextBuilder())->build(
            $report,
            PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING,
            PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_OVERALL
        );
        $html = (new PanelReportPdfService())->build_html($context);

        $this->assertStringContainsString('Overall Review Report', $html);
        $this->assertStringContainsString('>R1</th>', $html);
        $this->assertStringContainsString('>R2</th>', $html);
        $this->assertStringContainsString('Overall score', $html);
        $this->assertStringNotContainsString('Final Marks', $html);
        $this->assertStringNotContainsString('8.50', $html);
        $this->assertStringNotContainsString('>—<', $html);
        $this->assertStringContainsString('min-width: 3em', $html);
        $this->assertStringContainsString('Reviewer 1: Head Reviewer', $html);
        $this->assertStringContainsString('Reviewer 2: Peer Reviewer', $html);
    }

    public function test_offline_scoring_multi_panel_html_orders_reviewer_then_overall_per_panel(): void
    {
        $report_a = $this->sample_report();
        $report_b = $this->sample_report();
        $report_b['panel_id'] = 4;
        $report_b['panel_name'] = 'Panel B';

        $service = new PanelReportPdfService();
        $result = $service->render_offline_scoring_multi([$report_a, $report_b]);
        $this->assertIsArray($result);

        $builder = new PanelReportPdfContextBuilder();
        $contexts = [];
        foreach ([$report_a, $report_b] as $report) {
            $report['offline_scoring'] = true;
            foreach ($report['reviewers'] as $reviewer) {
                $ordinal = (int) ($reviewer['ordinal'] ?? 0);
                $contexts[] = $builder->build(
                    $report,
                    PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING,
                    PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_REVIEWER,
                    $ordinal
                );
            }
            $contexts[] = $builder->build(
                $report,
                PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING,
                PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_OVERALL
            );
        }

        $html = $service->build_multi_panel_html($contexts);

        $this->assertStringContainsString('panel-sheet-first', $html);
        $this->assertStringContainsString('page-break-before: always', $html);
        $this->assertStringContainsString('Panel A', $html);
        $this->assertStringContainsString('Panel B', $html);
        $this->assertEquals(6, substr_count($html, 'class="panel-sheet'));
        $this->assertEquals(2, substr_count($html, 'Overall Review Report'));
        $this->assertEquals(4, substr_count($html, '— Scoring Sheet'));

        preg_match_all('/<div class="report-title">([^<]+)<\/div>/', $html, $title_matches);
        $this->assertSame(
            [
                'Reviewer 1 — Scoring Sheet',
                'Reviewer 2 — Scoring Sheet',
                'Overall Review Report',
                'Reviewer 1 — Scoring Sheet',
                'Reviewer 2 — Scoring Sheet',
                'Overall Review Report',
            ],
            $title_matches[1] ?? []
        );
    }

    public function test_signed_report_still_renders_scores_and_final_column(): void
    {
        $report = $this->sample_report();
        $context = (new PanelReportPdfContextBuilder())->build($report);
        $html = (new PanelReportPdfService())->build_html($context);

        $this->assertTrue($context['render_scores']);
        $this->assertStringContainsString('Final Marks', $html);
        $this->assertStringContainsString('8.50', $html);
    }

    public function test_letterhead_block_order(): void
    {
        if (function_exists('update_option')) {
            update_option(SessionPanelReportSettings::option_key(1), [
                'letterhead' => [
                    'blocks' => [
                        ['type' => 'text', 'value' => 'First Line', 'style' => 'title'],
                        ['type' => 'text', 'value' => 'Second Line', 'style' => 'subtitle'],
                    ],
                ],
            ]);
        }

        $report = $this->sample_report();
        $context = (new PanelReportPdfContextBuilder())->build($report);
        $html = (new PanelReportPdfService())->build_html($context);

        $pos_first = strpos($html, 'First Line');
        $pos_second = strpos($html, 'Second Line');
        $this->assertNotFalse($pos_first);
        $this->assertNotFalse($pos_second);
        $this->assertLessThan($pos_second, $pos_first);
    }
}
