<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\ExportService;
use ProjectReviews\Services\MarkService;
use ProjectReviews\Services\PanelReportService;
use ProjectReviews\Services\ReportQueryService;
use ProjectReviews\Services\ReportsViewService;
use ProjectReviews\Repositories\SessionRepository;

final class Rest_Reports
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::require_cap(PR_CAP_VIEW_REPORTS);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reports',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'list_report_types'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/reports/(?P<type>[a-z_]+)/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'download_report'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/marks-grid',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'marks_grid'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/marks-grid/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'marks_grid_download'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/scores-matrix',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'scores_matrix'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/scores-matrix/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'scores_matrix_download'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/panel-roster/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'panel_roster_download'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/offline-scoring-sheet/pdf',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'offline_scoring_sheet_review_pdf'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/panels/(?P<panel_id>\d+)/offline-scoring-sheet/pdf',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'offline_scoring_sheet_pdf'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/consolidated-scores',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'consolidated_scores'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/consolidated-scores/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'consolidated_scores_download'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/consolidated-student-scores/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'consolidated_student_scores_download'],
                'permission_callback' => $read,
            ]
        );

        $manage = Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS);

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/lock-marks',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'lock_marks'],
                'permission_callback' => $manage,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<session_id>\d+)/reviews/(?P<review_id>\d+)/unlock-marks',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'unlock_marks'],
                'permission_callback' => $manage,
            ]
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function marks_grid(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');

        return (new ReportsViewService())->marks_grid($session_id, $review_id);
    }

    public static function marks_grid_download(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $format = strtolower((string) ($request->get_param('format') ?? 'xlsx'));
        $layout = (string) ($request->get_param('layout') ?? 'rubric');
        $sort_key = (string) ($request->get_param('sort_key') ?? 'reg_no');
        $sort_dir = (string) ($request->get_param('sort_dir') ?? 'asc');

        if ($format !== 'xlsx') {
            return new \WP_Error(
                'pr_invalid_format',
                __('Only xlsx is supported for marks grid download.', 'scorva'),
                ['status' => 400]
            );
        }

        if (!in_array($layout, ['rubric', 'reviewer'], true)) {
            return new \WP_Error(
                'pr_invalid_layout',
                __('Layout must be rubric or reviewer.', 'scorva'),
                ['status' => 400]
            );
        }

        try {
            $built = (new ReportsViewService())->marks_grid_export(
                $session_id,
                $review_id,
                $layout,
                $sort_key,
                $sort_dir
            );
            if ($built instanceof \WP_Error) {
                return $built;
            }

            $export = new ExportService();
            $body = $export->to_xlsx(
                $built['rows'],
                $built['merge_plan'],
                $built['styles']
            );

            return Rest_Binary_Response::from_body(
                $body,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                (string) $built['filename']
            );
        } catch (\Throwable $e) {
            return new \WP_Error(
                'pr_export_failed',
                __('Report export failed.', 'scorva'),
                ['status' => 500]
            );
        }
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function scores_matrix(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');

        return (new ReportsViewService())->scores_matrix($session_id, $review_id);
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function consolidated_scores(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');

        return (new ReportsViewService())->consolidated_scores($session_id);
    }

    public static function consolidated_scores_download(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $format = strtolower((string) ($request->get_param('format') ?? 'xlsx'));
        $sort_key = (string) ($request->get_param('sort_key') ?? 'reg_no');
        $sort_dir = (string) ($request->get_param('sort_dir') ?? 'asc');

        if ($format !== 'xlsx') {
            return new \WP_Error(
                'pr_invalid_format',
                __('Only xlsx is supported for consolidated scores download.', 'scorva'),
                ['status' => 400]
            );
        }

        try {
            $built = (new ReportsViewService())->consolidated_scores_export(
                $session_id,
                $sort_key,
                $sort_dir
            );
            if ($built instanceof \WP_Error) {
                return $built;
            }

            $export = new ExportService();
            $body = $export->to_xlsx(
                $built['rows'],
                $built['merge_plan'],
                $built['styles']
            );

            return Rest_Binary_Response::from_body(
                $body,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                (string) $built['filename']
            );
        } catch (\Throwable $e) {
            return new \WP_Error(
                'pr_export_failed',
                __('Report export failed.', 'scorva'),
                ['status' => 500]
            );
        }
    }

    public static function consolidated_student_scores_download(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $format = strtolower((string) ($request->get_param('format') ?? 'xlsx'));

        if (!in_array($format, ['csv', 'xlsx'], true)) {
            return new \WP_Error(
                'pr_invalid_format',
                __('Format must be csv or xlsx.', 'scorva'),
                ['status' => 400]
            );
        }

        try {
            $built = (new ReportsViewService())->consolidated_student_export($session_id);
            if ($built instanceof \WP_Error) {
                return $built;
            }

            $export = new ExportService();
            $base_filename = (string) ($built['filename'] ?? 'consolidated_student_scores');

            if ($format === 'csv') {
                $body = $export->to_csv($built['csv_rows'] ?? $built['rows']);
                $filename = $base_filename . '.csv';
                $content_type = 'text/csv; charset=utf-8';
            } else {
                $body = $export->to_xlsx(
                    $built['rows'],
                    $built['merge_plan'],
                    $built['styles']
                );
                $filename = $base_filename . '.xlsx';
                $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }

            return Rest_Binary_Response::from_body($body, $content_type, $filename);
        } catch (\Throwable $e) {
            return new \WP_Error(
                'pr_export_failed',
                __('Report export failed.', 'scorva'),
                ['status' => 500]
            );
        }
    }

    public static function offline_scoring_sheet_review_pdf(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');

        $result = (new PanelReportService())->generate_offline_scoring_pdf_for_review(
            $session_id,
            $review_id
        );

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return Rest_Binary_Response::from_body(
            (string) $result['pdf'],
            'application/pdf',
            (string) $result['filename']
        );
    }

    public static function offline_scoring_sheet_pdf(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $panel_id = (int) $request->get_param('panel_id');

        $result = (new PanelReportService())->generate_offline_scoring_pdf(
            $session_id,
            $review_id,
            $panel_id
        );

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return Rest_Binary_Response::from_body(
            (string) $result['pdf'],
            'application/pdf',
            (string) $result['filename']
        );
    }

    public static function panel_roster_download(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $format = strtolower((string) ($request->get_param('format') ?? 'xlsx'));

        if (!in_array($format, ['csv', 'xlsx'], true)) {
            return new \WP_Error(
                'pr_invalid_format',
                __('Format must be csv or xlsx.', 'scorva'),
                ['status' => 400]
            );
        }

        try {
            $built = (new ReportsViewService())->panel_roster_export($session_id, $review_id);
            if ($built instanceof \WP_Error) {
                return $built;
            }

            $export = new ExportService();
            $base_filename = (string) ($built['filename'] ?? 'panel_roster');

            if ($format === 'csv') {
                $body = $export->to_csv($built['rows']);
                $filename = $base_filename . '.csv';
                $content_type = 'text/csv; charset=utf-8';
            } else {
                $body = $export->to_xlsx($built['rows'], $built['merge_plan'], $built['styles']);
                $filename = $base_filename . '.xlsx';
                $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }

            return Rest_Binary_Response::from_body($body, $content_type, $filename);
        } catch (\Throwable $e) {
            return new \WP_Error(
                'pr_export_failed',
                __('Report export failed.', 'scorva'),
                ['status' => 500]
            );
        }
    }

    public static function scores_matrix_download(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $format = strtolower((string) ($request->get_param('format') ?? 'xlsx'));
        $sort_key = (string) ($request->get_param('sort_key') ?? 'reg_no');
        $sort_dir = (string) ($request->get_param('sort_dir') ?? 'asc');

        if ($format !== 'xlsx') {
            return new \WP_Error(
                'pr_invalid_format',
                __('Only xlsx is supported for scores matrix download.', 'scorva'),
                ['status' => 400]
            );
        }

        try {
            $built = (new ReportsViewService())->scores_matrix_export(
                $session_id,
                $review_id,
                $sort_key,
                $sort_dir
            );
            if ($built instanceof \WP_Error) {
                return $built;
            }

            $export = new ExportService();
            $body = $export->to_xlsx(
                $built['rows'],
                $built['merge_plan'],
                $built['styles']
            );

            return Rest_Binary_Response::from_body(
                $body,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                (string) $built['filename']
            );
        } catch (\Throwable $e) {
            return new \WP_Error(
                'pr_export_failed',
                __('Report export failed.', 'scorva'),
                ['status' => 500]
            );
        }
    }

    /**
     * @return array{coordinator_marks_locked: bool}|\WP_Error
     */
    public static function lock_marks(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $actor = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        return (new MarkService())->lock_review_marks($session_id, $review_id, $actor);
    }

    /**
     * @return array{coordinator_marks_locked: bool, marking_active: bool}|\WP_Error
     */
    public static function unlock_marks(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('session_id');
        $review_id = (int) $request->get_param('review_id');
        $actor = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        return (new MarkService())->unlock_review_marks($session_id, $review_id, $actor);
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function list_report_types(\WP_REST_Request $request): array
    {
        $session_id = (int) $request->get_param('id');
        if (!self::session_exists($session_id)) {
            return [];
        }

        return self::report_catalog();
    }

    public static function download_report(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $type = (string) $request->get_param('type');
        $format = strtolower((string) ($request->get_param('format') ?? 'xlsx'));

        if (!self::session_exists($session_id)) {
            return new \WP_Error('pr_session_not_found', __('Project not found.', 'scorva'), ['status' => 404]);
        }

        if (in_array($type, ReportQueryService::ALL_TYPES, true)) {
            return new \WP_Error(
                'pr_report_deprecated',
                __('This report download is no longer available. Use the Reports downloads tab.', 'scorva'),
                ['status' => 410]
            );
        }

        return new \WP_Error('pr_invalid_report', __('Unknown report type.', 'scorva'), ['status' => 400]);
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    private static function report_catalog(): array
    {
        return [
            [
                'key' => ReportsViewService::PANEL_ROSTER_CATALOG_KEY,
                'label' => __('Panel roster', 'scorva'),
                'description' => __(
                    'One row per student: reg no, name, program, panel, guide, attendance, and reviewer slots for the selected review round.',
                    'scorva'
                ),
                'scope' => 'review',
            ],
            [
                'key' => ReportsViewService::CONSOLIDATED_STUDENT_CATALOG_KEY,
                'label' => __('Consolidated student scores', 'scorva'),
                'description' => __(
                    'One row per enrolled student with panel context, reviewer rubric marks, review totals, and combined score across all confirmed reviews.',
                    'scorva'
                ),
                'scope' => 'session',
            ],
            [
                'key' => ReportsViewService::OFFLINE_SCORING_SHEET_CATALOG_KEY,
                'label' => __('Offline scoring sheet', 'scorva'),
                'description' => __(
                    'Institutional Review Report PDF with blank reviewer score cells for handwriting before data entry.',
                    'scorva'
                ),
                'scope' => 'review',
                'formats' => ['pdf'],
            ],
            [
                'key' => ReportsViewService::MARKS_MATRIX_CATALOG_KEY,
                'label' => __('Rubric marks matrix', 'scorva'),
                'description' => __(
                    'Same columns as the Rubric marks live view: panel context, attendance, status, rubric scores by reviewer slot, weighted review score.',
                    'scorva'
                ),
                'scope' => 'review',
            ],
            [
                'key' => ReportsViewService::SCORES_MATRIX_CATALOG_KEY,
                'label' => __('Overall scores matrix', 'scorva'),
                'description' => __(
                    'Same columns as the Overall scores live view: panel context, reviewer overall totals by slot, weighted review score.',
                    'scorva'
                ),
                'scope' => 'review',
            ],
        ];
    }

    private static function session_exists(int $session_id): bool
    {
        return (new SessionRepository())->find_by_id($session_id) !== null;
    }
}
