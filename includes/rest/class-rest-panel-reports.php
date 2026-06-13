<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\PanelReportService;

final class Rest_Panel_Reports
{
    public static function register_routes(): void
    {
        $read = Rest_Auth::allow_reviewer_session(Rest_Auth::require_cap(PR_CAP_ENTER_MARKS));
        $write = Rest_Auth::allow_reviewer_session(
            Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_ENTER_MARKS))
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/panel-reports/(?P<session_id>\d+)/(?P<review_id>\d+)/(?P<panel_id>\d+)',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'get_report'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/panel-reports/(?P<session_id>\d+)/(?P<review_id>\d+)/(?P<panel_id>\d+)/pdf',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'download_pdf'],
                'permission_callback' => $read,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/panel-reports/(?P<session_id>\d+)/(?P<review_id>\d+)/(?P<panel_id>\d+)/freeze',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'freeze_panel'],
                'permission_callback' => $write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/reviewer/panel-reports/(?P<session_id>\d+)/(?P<review_id>\d+)/(?P<panel_id>\d+)/unfreeze-request',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'request_panel_unfreeze'],
                'permission_callback' => $write,
            ]
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_report(\WP_REST_Request $request): array|\WP_Error
    {
        return (new PanelReportService())->get_report(
            (int) $request->get_param('session_id'),
            (int) $request->get_param('review_id'),
            (int) $request->get_param('panel_id'),
            Rest_Auth::current_actor_id()
        );
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public static function download_pdf(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $result = (new PanelReportService())->generate_pdf(
            (int) $request->get_param('session_id'),
            (int) $request->get_param('review_id'),
            (int) $request->get_param('panel_id'),
            Rest_Auth::current_actor_id()
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

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function freeze_panel(\WP_REST_Request $request): array|\WP_Error
    {
        return (new PanelReportService())->freeze_panel(
            (int) $request->get_param('session_id'),
            (int) $request->get_param('review_id'),
            (int) $request->get_param('panel_id'),
            Rest_Auth::current_actor_id()
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function request_panel_unfreeze(\WP_REST_Request $request): array|\WP_Error
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        return (new PanelReportService())->request_panel_unfreeze(
            (int) $request->get_param('session_id'),
            (int) $request->get_param('review_id'),
            (int) $request->get_param('panel_id'),
            Rest_Auth::current_actor_id(),
            (string) ($body['reason'] ?? '')
        );
    }
}
