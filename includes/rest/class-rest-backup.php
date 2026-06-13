<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\BackupService;

final class Rest_Backup
{
    private const THROTTLE_SECONDS = 60;

    public static function register_routes(): void
    {
        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/backup/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'download_full'],
                'permission_callback' => Rest_Auth::require_cap(PR_CAP_MANAGE_SETTINGS),
            ]
        );

        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/sessions/(?P<id>\d+)/backup/download',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'download_project'],
                'permission_callback' => Rest_Auth::require_cap(PR_CAP_MANAGE_SESSIONS),
            ]
        );
    }

    public static function download_full(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        unset($request);

        $throttle = self::check_throttle();
        if ($throttle instanceof \WP_Error) {
            return $throttle;
        }

        $built = (new BackupService())->build_full_backup_zip();
        if ($built instanceof \WP_Error) {
            return $built;
        }

        return self::zip_response($built);
    }

    public static function download_project(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session_id = (int) $request->get_param('id');

        $throttle = self::check_throttle();
        if ($throttle instanceof \WP_Error) {
            return $throttle;
        }

        $built = (new BackupService())->build_project_backup_zip($session_id);
        if ($built instanceof \WP_Error) {
            return $built;
        }

        return self::zip_response($built);
    }

    /**
     * @param array{path: string, filename: string, temp_dir: string} $built
     */
    private static function zip_response(array $built): \WP_REST_Response|\WP_Error
    {
        $path = (string) ($built['path'] ?? '');
        $filename = (string) ($built['filename'] ?? 'scorva-backup.zip');
        $temp_dir = (string) ($built['temp_dir'] ?? '');

        if ($path === '' || !is_readable($path)) {
            if ($temp_dir !== '') {
                (new BackupService())->cleanup_temp($temp_dir);
            }

            return new \WP_Error(
                'pr_backup_failed',
                __('Backup file could not be read.', 'scorva'),
                ['status' => 500]
            );
        }

        $body = file_get_contents($path);
        if ($temp_dir !== '') {
            (new BackupService())->cleanup_temp($temp_dir);
        }

        if ($body === false || $body === '') {
            return new \WP_Error(
                'pr_backup_failed',
                __('Backup file is empty.', 'scorva'),
                ['status' => 500]
            );
        }

        return Rest_Binary_Response::from_body(
            $body,
            'application/zip',
            $filename
        );
    }

    private static function check_throttle(): bool|\WP_Error
    {
        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        if ($user_id <= 0) {
            return true;
        }

        $key = 'pr_backup_throttle_' . $user_id;
        if (get_transient($key)) {
            return new \WP_Error(
                'pr_backup_throttled',
                __('Please wait before requesting another backup.', 'scorva'),
                ['status' => 429]
            );
        }

        set_transient($key, '1', self::THROTTLE_SECONDS);

        return true;
    }
}
