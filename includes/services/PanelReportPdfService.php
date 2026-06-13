<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use ProjectReviews\Repositories\ReviewAssignmentRepository;

final class PanelReportPdfService
{
    private PanelReportPdfContextBuilder $context_builder;

    public function __construct(?PanelReportPdfContextBuilder $context_builder = null)
    {
        $this->context_builder = $context_builder ?? new PanelReportPdfContextBuilder();
    }

    /**
     * @param array<string, mixed> $report
     * @return array{pdf: string, filename: string}|\WP_Error
     */
    public function render(array $report): array|\WP_Error
    {
        return $this->render_from_contexts([$this->context_for_report($report)]);
    }

    /**
     * Offline scoring sheets for every panel in a review (one PDF, page break per panel).
     *
     * @param list<array<string, mixed>> $reports
     * @return array{pdf: string, filename: string}|\WP_Error
     */
    public function render_offline_scoring_multi(array $reports): array|\WP_Error
    {
        if ($reports === []) {
            return new \WP_Error(
                'offline_scoring_no_panels',
                __('No panels with enrolled students were found for this review.', 'scorva'),
                ['status' => 400]
            );
        }

        $contexts = [];
        foreach ($reports as $report) {
            $report['offline_scoring'] = true;
            $reviewers = is_array($report['reviewers'] ?? null) ? $report['reviewers'] : [];
            foreach ($reviewers as $reviewer) {
                if (!is_array($reviewer)) {
                    continue;
                }
                $ordinal = (int) ($reviewer['ordinal'] ?? $reviewer['reviewer_ordinal'] ?? 0);
                if ($ordinal <= 0) {
                    continue;
                }
                $contexts[] = $this->context_builder->build(
                    $report,
                    PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING,
                    PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_REVIEWER,
                    $ordinal
                );
            }
            $contexts[] = $this->context_builder->build(
                $report,
                PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING,
                PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_OVERALL
            );
        }

        return $this->render_from_contexts($contexts);
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function context_for_report(array $report): array
    {
        $mode = !empty($report['offline_scoring'])
            ? PanelReportPdfContextBuilder::MODE_OFFLINE_SCORING
            : PanelReportPdfContextBuilder::MODE_SIGNED;

        return $this->context_builder->build($report, $mode);
    }

    /**
     * @param list<array<string, mixed>> $contexts
     * @return array{pdf: string, filename: string}|\WP_Error
     */
    private function render_from_contexts(array $contexts): array|\WP_Error
    {
        if ($contexts === []) {
            return new \WP_Error(
                'pdf_empty',
                __('Nothing to render.', 'scorva'),
                ['status' => 400]
            );
        }

        if (!class_exists(Dompdf::class)) {
            return new \WP_Error(
                'pdf_unavailable',
                __('PDF generation is not available on this server.', 'scorva'),
                ['status' => 500]
            );
        }

        $primary = $contexts[0];
        $html = count($contexts) === 1
            ? $this->build_html($primary)
            : $this->build_multi_panel_html($contexts);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Times-Roman');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $this->add_page_footer($dompdf, $primary);

        $session_slug = sanitize_title((string) ($primary['session_title'] ?? 'project'));
        $review_slug = sanitize_title((string) ($primary['review_label'] ?? 'review'));
        $prefix = !empty($primary['offline_scoring']) ? 'offline-scoring-sheet' : 'panel-report';

        if (count($contexts) === 1) {
            $panel_slug = sanitize_title((string) ($primary['panel_name'] ?? 'panel'));
            $filename = sprintf('%s-%s-%s-%s.pdf', $prefix, $session_slug, $review_slug, $panel_slug);
        } else {
            $filename = sprintf('%s-%s-%s.pdf', $prefix, $session_slug, $review_slug);
        }

        return [
            'pdf' => $dompdf->output(),
            'filename' => $filename,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function build_html(array $context): string
    {
        $border_css = $this->table_border_css($context);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
{$this->document_styles($border_css)}
</style>
</head>
<body>
{$this->build_panel_body($context)}
</body>
</html>
HTML;
    }

    /**
     * @param list<array<string, mixed>> $contexts
     */
    public function build_multi_panel_html(array $contexts): string
    {
        if ($contexts === []) {
            return '';
        }

        $border_css = $this->table_border_css($contexts[0]);
        $sections = '';
        foreach ($contexts as $index => $context) {
            $class = $index === 0 ? 'panel-sheet panel-sheet-first' : 'panel-sheet';
            $sections .= '<div class="' . esc_attr($class) . '">'
                . $this->build_panel_body($context)
                . '</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
{$this->document_styles($border_css)}
.panel-sheet { page-break-before: always; }
.panel-sheet-first { page-break-before: auto; }
</style>
</head>
<body>
{$sections}
</body>
</html>
HTML;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function build_panel_body(array $context): string
    {
        $template = is_array($context['template'] ?? null)
            ? $context['template']
            : PluginSettings::default_panel_report_pdf();

        $letterhead = $this->render_letterhead($template, (string) ($context['logo_data_uri'] ?? ''));
        $metadata = $this->render_metadata($context, $template);
        $table = $this->render_table($context, $template);
        $legend = $this->render_reviewer_legend($context, $template);
        $signatures = $this->render_signatures($context, $template);

        return $letterhead . $metadata . $table . $legend . $signatures;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function table_border_css(array $context): string
    {
        $template = is_array($context['template'] ?? null)
            ? $context['template']
            : PluginSettings::default_panel_report_pdf();
        $styles = is_array($template['styles'] ?? null) ? $template['styles'] : [];
        $border_pt = (float) ($styles['table_border_pt'] ?? 1);
        $border_color = (string) ($styles['table_border_color'] ?? '#000000');

        return sprintf('border: %.2fpt solid %s;', $border_pt, $border_color);
    }

    private function document_styles(string $border_css): string
    {
        return <<<CSS
@page { margin: 48px 40px 56px 40px; }
body {
  font-family: "Times New Roman", Times-Roman, Times, serif;
  font-size: 11px;
  margin: 0;
  color: #000;
  line-height: 1.35;
}
.letterhead { text-align: center; margin-bottom: 14px; }
.letterhead-title { font-size: 14pt; font-weight: bold; margin: 3px 0; }
.letterhead-subtitle { font-size: 12pt; font-weight: bold; margin: 3px 0; }
.letterhead-body { font-size: 11pt; font-weight: bold; margin: 3px 0; }
.letterhead img { display: block; margin: 0 auto 6px; max-width: 100%; }
.report-title {
  font-size: 15pt;
  font-weight: bold;
  text-align: center;
  margin: 0 0 10px;
  letter-spacing: 0.02em;
}
.meta-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 16px;
  font-size: 10.5px;
}
.meta-table th,
.meta-table td {
  {$border_css}
  padding: 6px 10px;
  vertical-align: top;
}
.meta-table th {
  width: 22%;
  font-weight: bold;
  background: #f5f5f5;
  text-align: left;
}
.meta-table td { width: 28%; }
table.scores {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 8px;
  table-layout: auto;
  font-size: 10px;
}
table.scores .col-shrink {
  width: 1%;
  white-space: nowrap;
  padding-left: 4px;
  padding-right: 4px;
}
table.scores .col-wrap { word-wrap: break-word; overflow-wrap: break-word; }
table.scores .col-title,
table.scores .col-guide,
table.scores .col-student { width: auto; }
table.scores th,
table.scores td {
  {$border_css}
  padding: 5px 6px;
  text-align: left;
  word-wrap: break-word;
  overflow-wrap: break-word;
}
table.scores th { font-weight: bold; background: #f5f5f5; }
table.scores td.num { text-align: center; font-variant-numeric: tabular-nums; }
table.scores td.score { text-align: right; font-variant-numeric: tabular-nums; }
table.scores .col-att { text-align: center; }
table.scores .col-reviewer { text-align: right; }
table.scores th.col-reviewer,
table.scores td.col-reviewer,
table.scores th.col-overall,
table.scores td.col-overall { min-width: 3em; }
table.scores .col-final { text-align: right; }
table.scores .col-overall { text-align: right; }
.reviewer-legend {
  font-size: 9.5px;
  margin: 0 0 20px;
  color: #222;
}
thead { display: table-header-group; }
.sig-section { margin-top: 28px; page-break-inside: avoid; }
.sig-heading { font-weight: bold; margin-bottom: 12px; font-size: 11px; }
.sig-layout { display: table; width: 100%; }
.sig-left, .sig-right { display: table-cell; vertical-align: top; }
.sig-left { width: 55%; padding-right: 12px; }
.sig-right { width: 45%; text-align: right; }
.sig-row { margin-bottom: 18px; }
.sig-label { display: block; margin-top: 4px; font-weight: 600; }
.sig-name { display: block; margin-top: 2px; font-weight: normal; }
.sig-line {
  display: block;
  width: 85%;
  border-bottom: 1pt solid #000;
  height: 22px;
  margin-bottom: 2px;
}
.sig-right .sig-line { margin-left: auto; width: 70%; }
.sig-hod-name { margin: 4px 0 0; font-weight: normal; }
CSS;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function add_page_footer(Dompdf $dompdf, array $context): void
    {
        $template = is_array($context['template'] ?? null) ? $context['template'] : [];
        $footer_cfg = is_array($template['footer'] ?? null) ? $template['footer'] : [];
        $show_generated = !isset($footer_cfg['show_generated_datetime'])
            || !empty($footer_cfg['show_generated_datetime']);
        $generated_at = (string) ($context['generated_at'] ?? '');

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Times-Roman');
        $size = 9;
        $color = [0, 0, 0];

        $canvas->page_text(270, 820, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, $size, $color);

        if ($show_generated && $generated_at !== '') {
            $canvas->page_text(40, 820, 'Report generated: ' . $generated_at, $font, $size, $color);
        }
    }

    /**
     * @param array<string, mixed> $template
     */
    private function render_letterhead(array $template, string $logo_data_uri): string
    {
        $letterhead = is_array($template['letterhead'] ?? null) ? $template['letterhead'] : [];
        $blocks = is_array($letterhead['blocks'] ?? null) ? $letterhead['blocks'] : [];
        if ($blocks === []) {
            return '';
        }

        $html = '<div class="letterhead">';

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'image') {
                if ($logo_data_uri !== '') {
                    $width = (float) ($block['width_in'] ?? 4.0);
                    $html .= sprintf(
                        '<img src="%s" style="width: %.2fin;" alt="" />',
                        esc_attr($logo_data_uri),
                        $width
                    );
                }
                continue;
            }

            $value = trim((string) ($block['value'] ?? ''));
            $label = trim((string) ($block['label'] ?? ''));
            $text = $value;
            if ($label !== '' && $value !== '') {
                $text = $label . ': ' . $value;
            } elseif ($label !== '') {
                $text = $label;
            }
            if ($text === '') {
                continue;
            }

            $style = (string) ($block['style'] ?? 'body');
            $class = match ($style) {
                'title' => 'letterhead-title',
                'subtitle' => 'letterhead-subtitle',
                default => 'letterhead-body',
            };
            $html .= '<div class="' . esc_attr($class) . '">' . esc_html($text) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $template
     */
    private function render_metadata(array $context, array $template): string
    {
        $report_cfg = is_array($template['report'] ?? null) ? $template['report'] : [];
        $title_override = trim((string) ($context['report_title_override'] ?? ''));
        $title = esc_html(
            $title_override !== ''
                ? $title_override
                : (string) ($report_cfg['title'] ?? 'Review Report')
        );

        $rows = [];
        $program = trim((string) ($report_cfg['program_name'] ?? ''));
        $semester = trim((string) ($report_cfg['semester'] ?? ''));
        if ($program !== '' || $semester !== '') {
            $rows[] = [
                ['label' => 'Program Name', 'value' => $program],
                ['label' => 'Semester', 'value' => $semester],
            ];
        }

        $detail_row = [];
        if (!empty($report_cfg['show_review_number'])) {
            $detail_row[] = [
                'label' => 'Review Number',
                'value' => (string) ($context['review_label'] ?? ''),
            ];
        }
        if (!empty($report_cfg['show_panel_name'])) {
            $detail_row[] = [
                'label' => 'Panel Name',
                'value' => (string) ($context['panel_name'] ?? ''),
            ];
        }
        if ($detail_row !== []) {
            $rows[] = $detail_row;
        }

        if (!empty($report_cfg['show_reviewers_list'])) {
            $rows[] = [[
                'label' => 'Reviewers',
                'value' => (string) ($context['reviewer_names_line'] ?? ''),
                'full_width' => true,
            ]];
        }

        if ($rows === []) {
            return '<div class="report-title">' . $title . '</div>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            $cell_count = 0;
            foreach ($row as $cell) {
                if (!is_array($cell)) {
                    continue;
                }
                $label = esc_html((string) ($cell['label'] ?? ''));
                $value = esc_html((string) ($cell['value'] ?? ''));
                if (!empty($cell['full_width'])) {
                    $body .= '<th>' . $label . '</th><td colspan="3">' . $value . '</td>';
                    $cell_count = 4;
                    break;
                }
                $body .= '<th>' . $label . '</th><td>' . $value . '</td>';
                $cell_count += 2;
            }
            if ($cell_count === 2) {
                $body .= '<th></th><td></td>';
            }
            $body .= '</tr>';
        }

        return '<div class="report-title">' . $title . '</div>'
            . '<table class="meta-table"><tbody>' . $body . '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $template
     */
    private function render_table(array $context, array $template): string
    {
        $table_cfg = is_array($template['table'] ?? null) ? $template['table'] : [];
        $reviewers = is_array($context['reviewers'] ?? null) ? $context['reviewers'] : [];
        $students = is_array($context['students'] ?? null) ? $context['students'] : [];
        $reviewer_pattern = (string) ($table_cfg['reviewer_header_pattern'] ?? 'R{n}');
        $final_header = (string) ($table_cfg['final_marks_column_header'] ?? 'Final Marks');

        $render_scores = !isset($context['render_scores']) || !empty($context['render_scores']);
        $sheet_kind = (string) ($context['sheet_kind'] ?? PanelReportPdfContextBuilder::SHEET_KIND_SIGNED);
        $columns = $this->build_score_columns(
            $table_cfg,
            $reviewers,
            $final_header,
            $reviewer_pattern,
            $render_scores,
            $sheet_kind
        );
        if ($columns === []) {
            return '';
        }

        $header_cells = '';
        foreach ($columns as $index => $column) {
            $class = trim($column['class'] . ' ' . ($column['shrink'] ? 'col-shrink' : 'col-wrap'));
            $header_cells .= '<th class="' . esc_attr($class) . '">' . esc_html($column['header']) . '</th>';
        }

        $body_rows = '';
        foreach ($students as $student) {
            if (!is_array($student)) {
                continue;
            }
            $cells = $this->score_row_cells($student, $columns, $reviewers, $render_scores);
            $body_rows .= '<tr>';
            foreach ($cells as $index => $cell) {
                $column = $columns[$index];
                $class = trim($column['class'] . ' ' . ($column['shrink'] ? 'col-shrink' : 'col-wrap'));
                $body_rows .= '<td class="' . esc_attr($class) . '">' . esc_html($cell) . '</td>';
            }
            $body_rows .= '</tr>';
        }

        return '<table class="scores"><thead><tr>' . $header_cells . '</tr></thead><tbody>'
            . $body_rows . '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $table_cfg
     * @param list<array<string, mixed>> $reviewers
     * @return list<array{header: string, class: string, shrink: bool, min: int, max: int}>
     */
    private function build_score_columns(
        array $table_cfg,
        array $reviewers,
        string $final_header,
        string $reviewer_pattern,
        bool $render_scores = true,
        string $sheet_kind = PanelReportPdfContextBuilder::SHEET_KIND_SIGNED
    ): array {
        $columns = [];

        if (!empty($table_cfg['show_sr_no'])) {
            $columns[] = [
                'header' => (string) ($table_cfg['sr_no_column_header'] ?? 'Sr. No.'),
                'class' => 'col-sr num',
                'shrink' => true,
                'min' => 3,
                'max' => 8,
            ];
        }
        if (!empty($table_cfg['show_reg_no'])) {
            $columns[] = [
                'header' => (string) ($table_cfg['reg_no_column_header'] ?? 'Reg No'),
                'class' => 'col-reg num',
                'shrink' => false,
                'min' => 6,
                'max' => 14,
            ];
        }
        if (!empty($table_cfg['show_student_name'])) {
            $columns[] = [
                'header' => (string) ($table_cfg['student_column_header'] ?? 'Student'),
                'class' => 'col-student',
                'shrink' => false,
                'min' => 10,
                'max' => 22,
            ];
        }
        if (!empty($table_cfg['show_attendance'])) {
            $columns[] = [
                'header' => (string) ($table_cfg['attendance_column_header'] ?? 'At'),
                'class' => 'col-att num',
                'shrink' => true,
                'min' => 2,
                'max' => 4,
            ];
        }
        if (!empty($table_cfg['show_project_title'])) {
            $columns[] = [
                'header' => (string) ($table_cfg['project_title_column_header'] ?? 'Project title'),
                'class' => 'col-title',
                'shrink' => false,
                'min' => 14,
                'max' => 36,
            ];
        }
        if (!empty($table_cfg['show_guide_name'])) {
            $columns[] = [
                'header' => (string) ($table_cfg['guide_column_header'] ?? 'Guide'),
                'class' => 'col-guide',
                'shrink' => false,
                'min' => 10,
                'max' => 24,
            ];
        }
        if ($sheet_kind === PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_REVIEWER) {
            $columns[] = [
                'header' => 'Score',
                'class' => 'col-reviewer score',
                'shrink' => false,
                'min' => 4,
                'max' => 8,
            ];
        } elseif ($sheet_kind === PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_OVERALL) {
            foreach ($reviewers as $reviewer) {
                if (!is_array($reviewer)) {
                    continue;
                }
                $columns[] = [
                    'header' => $this->reviewer_header($reviewer_pattern, (int) ($reviewer['ordinal'] ?? 0)),
                    'class' => 'col-reviewer score',
                    'shrink' => false,
                    'min' => 4,
                    'max' => 8,
                ];
            }
            $columns[] = [
                'header' => 'Overall score',
                'class' => 'col-overall score',
                'shrink' => false,
                'min' => 4,
                'max' => 12,
            ];
        } else {
            foreach ($reviewers as $reviewer) {
                if (!is_array($reviewer)) {
                    continue;
                }
                $columns[] = [
                    'header' => $this->reviewer_header($reviewer_pattern, (int) ($reviewer['ordinal'] ?? 0)),
                    'class' => 'col-reviewer score',
                    'shrink' => true,
                    'min' => 4,
                    'max' => 8,
                ];
            }
            if ($render_scores) {
                $columns[] = [
                    'header' => $final_header,
                    'class' => 'col-final score',
                    'shrink' => true,
                    'min' => 6,
                    'max' => 12,
                ];
            }
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $student
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $reviewers
     * @return list<string>
     */
    private function score_row_cells(
        array $student,
        array $columns,
        array $reviewers,
        bool $render_scores = true
    ): array {
        $cells = [];
        $reviewer_index = 0;
        $attendance_status = (string) ($student['attendance_status'] ?? ReviewAssignmentRepository::ATTENDANCE_PRESENT);

        foreach ($columns as $column) {
            $class = (string) ($column['class'] ?? '');
            if (str_contains($class, 'col-sr')) {
                $cells[] = (string) ($student['sr_no'] ?? '');
            } elseif (str_contains($class, 'col-reg')) {
                $cells[] = (string) ($student['reg_no'] ?? '');
            } elseif (str_contains($class, 'col-student')) {
                $cells[] = (string) ($student['name'] ?? '');
            } elseif (str_contains($class, 'col-att')) {
                $cells[] = (string) ($student['attendance_label'] ?? 'P');
            } elseif (str_contains($class, 'col-title')) {
                $cells[] = (string) ($student['project_title'] ?? '');
            } elseif (str_contains($class, 'col-guide')) {
                $cells[] = (string) ($student['guide_name'] ?? '');
            } elseif (str_contains($class, 'col-reviewer')) {
                $reviewer = $reviewers[$reviewer_index] ?? null;
                ++$reviewer_index;
                if (!$render_scores) {
                    $cells[] = '';
                    continue;
                }
                if (!is_array($reviewer)) {
                    $cells[] = '—';
                    continue;
                }
                $user_id = (int) ($reviewer['user_id'] ?? 0);
                $totals = is_array($student['reviewer_totals'] ?? null) ? $student['reviewer_totals'] : [];
                $raw = $totals[(string) $user_id] ?? $totals[$user_id] ?? null;
                $value = is_array($raw) ? ($raw['score'] ?? null) : $raw;
                $is_draft = is_array($raw) && !empty($raw['draft']);
                if ($attendance_status === ReviewAssignmentRepository::ATTENDANCE_ABSENT || $value === null) {
                    $cells[] = '—';
                } else {
                    $display = number_format((float) $value, 2, '.', '');
                    if ($is_draft) {
                        $display .= ' (draft)';
                    }
                    $cells[] = $display;
                }
            } elseif (str_contains($class, 'col-overall')) {
                $cells[] = $render_scores
                    ? (($student['review_score'] ?? null) === null
                        ? '—'
                        : number_format((float) $student['review_score'], 2, '.', ''))
                    : '';
            } elseif (str_contains($class, 'col-final')) {
                $review_score = $student['review_score'] ?? null;
                $cells[] = $review_score === null
                    ? '—'
                    : number_format((float) $review_score, 2, '.', '');
            }
        }

        return $cells;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $template
     */
    private function render_reviewer_legend(array $context, array $template): string
    {
        $sheet_kind = (string) ($context['sheet_kind'] ?? PanelReportPdfContextBuilder::SHEET_KIND_SIGNED);
        if ($sheet_kind === PanelReportPdfContextBuilder::SHEET_KIND_OFFLINE_REVIEWER) {
            return '';
        }

        $table_cfg = is_array($template['table'] ?? null) ? $template['table'] : [];
        if (empty($table_cfg['show_reviewer_legend'])) {
            return '';
        }

        $reviewers = is_array($context['reviewers'] ?? null) ? $context['reviewers'] : [];
        if ($reviewers === []) {
            return '';
        }

        $pattern = (string) ($table_cfg['reviewer_header_pattern'] ?? 'R{n}');
        $signatures = is_array($template['signatures'] ?? null) ? $template['signatures'] : [];
        $sig_pattern = (string) ($signatures['reviewer_label_pattern'] ?? 'Reviewer {n}');

        $parts = [];
        foreach ($reviewers as $reviewer) {
            if (!is_array($reviewer)) {
                continue;
            }
            $ordinal = (int) ($reviewer['ordinal'] ?? 0);
            $short = $this->reviewer_header($pattern, $ordinal);
            $long = $this->reviewer_header($sig_pattern, $ordinal);
            $name = trim((string) ($reviewer['name'] ?? ''));
            $parts[] = $name !== ''
                ? esc_html($short) . ' = ' . esc_html($long) . ' (' . esc_html($name) . ')'
                : esc_html($short) . ' = ' . esc_html($long);
        }

        if ($parts === []) {
            return '';
        }

        return '<p class="reviewer-legend">' . implode('; ', $parts) . '</p>';
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $template
     */
    private function render_signatures(array $context, array $template): string
    {
        $signatures_cfg = is_array($template['signatures'] ?? null) ? $template['signatures'] : [];
        $heading = esc_html((string) ($signatures_cfg['section_heading'] ?? 'Signatures with date'));
        $lines = is_array($context['signature_lines'] ?? null) ? $context['signature_lines'] : [];

        $left_html = '';
        foreach ($lines as $line) {
            if (!is_array($line) || ($line['side'] ?? 'left') !== 'left') {
                continue;
            }
            $label = (string) ($line['label'] ?? '');
            $name = trim((string) ($line['name'] ?? ''));
            $caption = $name !== '' ? $label . ': ' . $name : $label;
            $left_html .= '<div class="sig-row"><span class="sig-line"></span><span class="sig-label">'
                . esc_html($caption) . '</span></div>';
        }

        $hod = is_array($signatures_cfg['hod'] ?? null) ? $signatures_cfg['hod'] : [];
        $right_html = '';
        if (!empty($hod['enabled'])) {
            $hod_label = (string) ($hod['label'] ?? 'Head of the Department');
            $hod_name = trim((string) ($hod['name'] ?? ''));
            $hod_caption = $hod_name !== '' ? $hod_label . ': ' . $hod_name : $hod_label;
            $right_html = '<div class="sig-row"><span class="sig-line"></span>'
                . '<span class="sig-label">' . esc_html($hod_caption) . '</span></div>';
        }

        return '<div class="sig-section">'
            . '<div class="sig-heading">' . $heading . '</div>'
            . '<div class="sig-layout">'
            . '<div class="sig-left">' . $left_html . '</div>'
            . '<div class="sig-right">' . $right_html . '</div>'
            . '</div></div>';
    }

    private function reviewer_header(string $pattern, int $ordinal): string
    {
        return str_replace('{n}', (string) $ordinal, $pattern);
    }
}
