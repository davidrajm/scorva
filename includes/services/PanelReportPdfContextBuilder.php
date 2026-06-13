<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\StudentRepository;

final class PanelReportPdfContextBuilder
{
    public const MODE_SIGNED = 'signed';

    public const MODE_OFFLINE_SCORING = 'offline_scoring';

    public const SHEET_KIND_SIGNED = 'signed';

    public const SHEET_KIND_OFFLINE_REVIEWER = 'offline_reviewer';

    public const SHEET_KIND_OFFLINE_OVERALL = 'offline_overall';

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function build(
        array $report,
        string $mode = self::MODE_SIGNED,
        string $sheet_kind = self::SHEET_KIND_SIGNED,
        int $active_reviewer_ordinal = 0
    ): array {
        $render_scores = $mode !== self::MODE_OFFLINE_SCORING;
        if ($mode === self::MODE_SIGNED) {
            $sheet_kind = self::SHEET_KIND_SIGNED;
        }
        $session_id = (int) ($report['session_id'] ?? 0);
        $template = SessionPanelReportSettings::get($session_id);
        $coordinator_user_id = (int) ($report['coordinator_user_id'] ?? 0);
        if ($coordinator_user_id <= 0) {
            $coordinator_user_id = $this->resolve_coordinator_user_id($report);
        }

        $reviewers = $this->enrich_reviewers($report, $coordinator_user_id);
        $students = $this->enrich_students($report, $template);
        $reviewer_names_line = $this->reviewer_names_line($reviewers);
        $signature_lines = $this->build_signature_lines($reviewers, $coordinator_user_id, $template);
        if ($sheet_kind === self::SHEET_KIND_OFFLINE_REVIEWER && $active_reviewer_ordinal > 0) {
            $signature_lines = $this->filter_signature_lines_for_reviewer(
                $signature_lines,
                $active_reviewer_ordinal,
                $template
            );
        }
        $logo_data_uri = $this->resolve_logo_data_uri($template);

        $generated_at = function_exists('wp_date')
            ? wp_date('d M Y, H:i')
            : gmdate('d M Y, H:i');

        $report_title_override = $this->report_title_override($sheet_kind, $active_reviewer_ordinal, $template);

        return array_merge($report, [
            'template' => $template,
            'reviewers' => $reviewers,
            'students' => $students,
            'reviewer_names_line' => $reviewer_names_line,
            'signature_lines' => $signature_lines,
            'coordinator_user_id' => $coordinator_user_id,
            'logo_data_uri' => $logo_data_uri,
            'generated_at' => $generated_at,
            'render_scores' => $render_scores,
            'offline_scoring' => !$render_scores,
            'sheet_kind' => $sheet_kind,
            'active_reviewer_ordinal' => $active_reviewer_ordinal,
            'report_title_override' => $report_title_override,
        ]);
    }

    /**
     * @param array<string, mixed> $template
     */
    private function report_title_override(string $sheet_kind, int $active_reviewer_ordinal, array $template): string
    {
        if ($sheet_kind === self::SHEET_KIND_OFFLINE_OVERALL) {
            return 'Overall Review Report';
        }

        if ($sheet_kind !== self::SHEET_KIND_OFFLINE_REVIEWER || $active_reviewer_ordinal <= 0) {
            return '';
        }

        $signatures = is_array($template['signatures'] ?? null) ? $template['signatures'] : [];
        $pattern = (string) ($signatures['reviewer_label_pattern'] ?? 'Reviewer {n}');
        $reviewer_label = str_replace('{n}', (string) $active_reviewer_ordinal, $pattern);

        return $reviewer_label . ' — Scoring Sheet';
    }

    /**
     * @param list<array{label: string, side: string, name?: string}> $lines
     * @param array<string, mixed> $template
     * @return list<array{label: string, side: string, name?: string}>
     */
    private function filter_signature_lines_for_reviewer(
        array $lines,
        int $active_reviewer_ordinal,
        array $template
    ): array {
        $signatures = is_array($template['signatures'] ?? null) ? $template['signatures'] : [];
        $pattern = (string) ($signatures['reviewer_label_pattern'] ?? 'Reviewer {n}');
        $target_label = str_replace('{n}', (string) $active_reviewer_ordinal, $pattern);

        foreach ($lines as $line) {
            if (($line['side'] ?? 'left') === 'left' && ($line['label'] ?? '') === $target_label) {
                return [$line];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function resolve_coordinator_user_id(array $report): int
    {
        $review_id = (int) ($report['review_id'] ?? 0);
        $panel_id = (int) ($report['panel_id'] ?? 0);
        if ($review_id <= 0 || $panel_id <= 0) {
            return 0;
        }

        foreach ($report['reviewers'] ?? [] as $reviewer) {
            if (!is_array($reviewer)) {
                continue;
            }
            if (!empty($reviewer['is_panel_coordinator'])) {
                return (int) ($reviewer['user_id'] ?? 0);
            }
        }

        $assignments = new ReviewAssignmentRepository();
        foreach ($assignments->list_panel_reviewers_for_panel($review_id, $panel_id) as $row) {
            if (!empty($row['is_panel_head'])) {
                return (int) ($row['user_id'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $report
     * @return list<array<string, mixed>>
     */
    private function enrich_reviewers(array $report, int $coordinator_user_id): array
    {
        $reviewers = is_array($report['reviewers'] ?? null) ? $report['reviewers'] : [];
        $enriched = [];
        $ordinal = 1;

        foreach ($reviewers as $reviewer) {
            if (!is_array($reviewer)) {
                continue;
            }
            $user_id = (int) ($reviewer['user_id'] ?? 0);
            $enriched[] = array_merge($reviewer, [
                'ordinal' => $ordinal,
                'reviewer_ordinal' => $ordinal,
                'is_panel_coordinator' => $user_id > 0 && $user_id === $coordinator_user_id,
            ]);
            ++$ordinal;
        }

        return $enriched;
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $template
     * @return list<array<string, mixed>>
     */
    private function enrich_students(array $report, array $template): array
    {
        $students = is_array($report['students'] ?? null) ? $report['students'] : [];
        $table = is_array($template['table'] ?? null) ? $template['table'] : [];
        $field_key = (string) ($table['project_title_field_key'] ?? 'project_title');
        $enriched = [];
        $sr_no = 1;

        foreach ($students as $student) {
            if (!is_array($student)) {
                continue;
            }

            $student_id = (int) ($student['student_id'] ?? 0);
            $attendance_status = (string) ($student['attendance_status'] ?? ReviewAssignmentRepository::ATTENDANCE_PRESENT);
            $project_title = trim((string) ($student['project_title'] ?? ''));
            if ($project_title === '' && $student_id > 0 && $field_key !== '') {
                $student_repo = new StudentRepository();
                $meta = $student_repo->get_meta($student_id);
                $project_title = trim((string) ($meta[$field_key] ?? ''));
            }

            $enriched[] = array_merge($student, [
                'sr_no' => $sr_no,
                'attendance_label' => $this->attendance_abbrev($attendance_status),
                'project_title' => $project_title,
                'guide_name' => trim((string) ($student['guide_name'] ?? '')),
            ]);
            ++$sr_no;
        }

        return $enriched;
    }

    private function attendance_abbrev(string $status): string
    {
        return $status === ReviewAssignmentRepository::ATTENDANCE_ABSENT ? 'A' : 'P';
    }

    /**
     * @param list<array<string, mixed>> $reviewers
     */
    private function reviewer_names_line(array $reviewers): string
    {
        $names = [];
        foreach ($reviewers as $reviewer) {
            $name = trim((string) ($reviewer['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    /**
     * @param list<array<string, mixed>> $reviewers
     * @param array<string, mixed> $template
     * @return list<array{label: string, side: string}>
     */
    private function build_signature_lines(array $reviewers, int $coordinator_user_id, array $template): array
    {
        $signatures = is_array($template['signatures'] ?? null) ? $template['signatures'] : [];
        $show_coordinator = !empty($signatures['show_panel_coordinator_line']);
        $coordinator_label = (string) ($signatures['panel_coordinator_label'] ?? 'Panel coordinator');
        $pattern = (string) ($signatures['reviewer_label_pattern'] ?? 'Reviewer {n}');

        $coordinator_in_roster = false;
        foreach ($reviewers as $reviewer) {
            if ((int) ($reviewer['user_id'] ?? 0) === $coordinator_user_id) {
                $coordinator_in_roster = true;
                break;
            }
        }

        $lines = [];
        if ($show_coordinator && $coordinator_user_id > 0 && !$coordinator_in_roster) {
            $lines[] = [
                'label' => $coordinator_label,
                'name' => $this->user_display_name($coordinator_user_id),
                'side' => 'left',
            ];
        }

        foreach ($reviewers as $reviewer) {
            $ordinal = (int) ($reviewer['ordinal'] ?? 0);
            $label = str_replace('{n}', (string) $ordinal, $pattern);
            $lines[] = [
                'label' => $label,
                'name' => trim((string) ($reviewer['name'] ?? '')),
                'side' => 'left',
            ];
        }

        return $lines;
    }

    private function user_display_name(int $user_id): string
    {
        if ($user_id <= 0 || !function_exists('get_userdata')) {
            return '';
        }

        $user = get_userdata($user_id);
        if ($user === false) {
            return '';
        }

        return trim((string) ($user->display_name ?? ''));
    }

    /**
     * @param array<string, mixed> $template
     */
    private function resolve_logo_data_uri(array $template): string
    {
        $letterhead = is_array($template['letterhead'] ?? null) ? $template['letterhead'] : [];
        $blocks = is_array($letterhead['blocks'] ?? null) ? $letterhead['blocks'] : [];

        foreach ($blocks as $block) {
            if (!is_array($block) || ($block['type'] ?? '') !== 'image') {
                continue;
            }
            $attachment_id = (int) ($block['attachment_id'] ?? 0);
            if ($attachment_id <= 0 || !function_exists('get_attached_file')) {
                return '';
            }

            $data_uri = $this->attachment_sized_to_data_uri($attachment_id);
            if ($data_uri !== '') {
                return $data_uri;
            }
        }

        return '';
    }

    /**
     * Prefer a downsized attachment (large → medium_large) to reduce PDF data-URI size,
     * falling back to the original file.
     */
    private function attachment_sized_to_data_uri(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        foreach (['large', 'medium_large'] as $size) {
            if (!function_exists('wp_get_attachment_image_src')) {
                break;
            }
            $src = wp_get_attachment_image_src($attachment_id, $size);
            if (!is_array($src) || empty($src[0])) {
                continue;
            }
            $url = (string) $src[0];
            // Resolve URL to a filesystem path via the uploads directory mapping.
            $path = $this->resolve_path_from_upload_url($url);
            if ($path !== '' && is_readable($path)) {
                $uri = $this->path_to_data_uri($path);
                if ($uri !== '') {
                    return $uri;
                }
            }
        }

        return $this->attachment_to_data_uri($attachment_id);
    }

    private function resolve_path_from_upload_url(string $url): string
    {
        if ($url === '' || !function_exists('wp_upload_dir')) {
            return '';
        }
        $upload = wp_upload_dir();
        $base_url = rtrim((string) ($upload['baseurl'] ?? ''), '/');
        $base_dir = rtrim((string) ($upload['basedir'] ?? ''), '/');
        if ($base_url === '' || $base_dir === '' || strpos($url, $base_url) !== 0) {
            return '';
        }
        return $base_dir . substr($url, strlen($base_url));
    }

    private function path_to_data_uri(string $path): string
    {
        if ($path === '' || !is_readable($path)) {
            return '';
        }
        $mime = function_exists('wp_check_filetype') ? (wp_check_filetype($path)['type'] ?? '') : '';
        if ($mime === '' && function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            $mime = is_string($detected) ? $detected : '';
        }
        if ($mime === '') {
            $mime = 'image/png';
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return '';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function attachment_to_data_uri(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        $path = function_exists('get_attached_file') ? get_attached_file($attachment_id) : false;
        if ($path === false || $path === '' || !is_readable($path)) {
            $path = $this->resolve_attachment_path_from_url($attachment_id);
        }

        if ($path === '' || !is_readable($path)) {
            return '';
        }

        $mime = function_exists('wp_check_filetype') ? (wp_check_filetype($path)['type'] ?? '') : '';
        if ($mime === '' && function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            $mime = is_string($detected) ? $detected : '';
        }
        if ($mime === '') {
            $mime = 'image/png';
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private function resolve_attachment_path_from_url(int $attachment_id): string
    {
        if (!function_exists('wp_get_attachment_url')) {
            return '';
        }

        $url = wp_get_attachment_url($attachment_id);
        if (!is_string($url) || $url === '') {
            return '';
        }

        if (!function_exists('wp_upload_dir')) {
            return '';
        }

        $upload = wp_upload_dir();
        $baseurl = $upload['baseurl'] ?? '';
        $basedir = $upload['basedir'] ?? '';
        if ($baseurl === '' || $basedir === '' || !str_starts_with($url, $baseurl)) {
            return '';
        }

        $relative = substr($url, strlen($baseurl));
        $local = $basedir . $relative;

        return is_readable($local) ? $local : '';
    }
}
