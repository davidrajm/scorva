<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\SessionPanelReportSettings;

final class Rest_Session_Panel_Report_Settings
{
    public static function register_routes(): void
    {
        $manage_read = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));
        $manage_write = Rest_Auth::with_rest_nonce(Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS));

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/panel-report-settings',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'get_settings'],
                    'permission_callback' => $manage_read,
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [self::class, 'update_settings'],
                    'permission_callback' => $manage_write,
                ],
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/panel-report-settings/freeze',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'freeze_settings'],
                'permission_callback' => $manage_write,
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/panel-report-settings/unfreeze',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'unfreeze_settings'],
                'permission_callback' => $manage_write,
            ]
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function get_settings(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = (new SessionRepository())->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $settings = SessionPanelReportSettings::get($session_id);

        return [
            'session_id' => $session_id,
            'panel_report_pdf' => $settings,
            'settings_frozen' => SessionPanelReportSettings::is_settings_frozen($session_id),
            'settings_frozen_at' => (string) ($settings['settings_frozen_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function freeze_settings(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = (new SessionRepository())->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $input = isset($body['panel_report_pdf']) && is_array($body['panel_report_pdf'])
            ? $body['panel_report_pdf']
            : null;

        if ($input !== null) {
            $saved = SessionPanelReportSettings::save($session_id, $input);
            if ($saved instanceof \WP_Error) {
                return $saved;
            }
        }

        $saved = SessionPanelReportSettings::freeze_settings($session_id);
        if ($saved instanceof \WP_Error) {
            return $saved;
        }

        return [
            'session_id' => $session_id,
            'panel_report_pdf' => $saved,
            'settings_frozen' => true,
            'settings_frozen_at' => (string) ($saved['settings_frozen_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function unfreeze_settings(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = (new SessionRepository())->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $saved = SessionPanelReportSettings::unfreeze_settings($session_id);
        if ($saved instanceof \WP_Error) {
            return $saved;
        }

        return [
            'session_id' => $session_id,
            'panel_report_pdf' => $saved,
            'settings_frozen' => false,
            'settings_frozen_at' => '',
        ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function update_settings(\WP_REST_Request $request): array|\WP_Error
    {
        $session_id = (int) $request->get_param('id');
        $session = (new SessionRepository())->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error('not_found', __('Project not found.', 'project-reviews'), ['status' => 404]);
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $input = isset($body['panel_report_pdf']) && is_array($body['panel_report_pdf'])
            ? $body['panel_report_pdf']
            : $body;

        $saved = SessionPanelReportSettings::save($session_id, $input);
        if ($saved instanceof \WP_Error) {
            return $saved;
        }

        return [
            'session_id' => $session_id,
            'panel_report_pdf' => $saved,
            'settings_frozen' => SessionPanelReportSettings::is_settings_frozen($session_id),
            'settings_frozen_at' => (string) ($saved['settings_frozen_at'] ?? ''),
        ];
    }
}
