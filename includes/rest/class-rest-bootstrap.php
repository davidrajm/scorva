<?php

declare(strict_types=1);

namespace ProjectReviews;

final class Rest_Bootstrap
{
    public const NAMESPACE = 'scorva/v1';

    public static function register_routes(): void
    {
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-binary-response.php';
        Rest_Binary_Response::register();

        register_rest_route(
            self::NAMESPACE,
            '/health',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'handle_health'],
                'permission_callback' => Rest_Auth::require_any_pr_cap(),
            ]
        );

        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-programs.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-students.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-sessions.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-reviewers.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-reviews.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-review-assignments.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-marks.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-scores.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-progress.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-reviewer-assignments.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-reports.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-audit.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-session-close.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-unfreeze-requests.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-unfreeze-summary.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-reviewer-unfreeze-requests.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-panel-unfreeze-requests.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-reviewer-panel-unfreeze-mine.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-panel-reports.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-session-panel-report-settings.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-backup.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-smtp.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-portal.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-admin-roles.php';
        require_once PR_PLUGIN_DIR . 'includes/rest/class-rest-admin-reset.php';
        Rest_Programs::register_routes();
        Rest_Students::register_routes();
        Rest_Sessions::register_routes();
        Rest_Reviewers::register_routes();
        Rest_Reviews::register_routes();
        Rest_Review_Assignments::register_routes();
        Rest_Marks::register_routes();
        Rest_Scores::register_routes();
        Rest_Progress::register_routes();
        Rest_Reviewer_Assignments::register_routes();
        Rest_Reports::register_routes();
        Rest_Audit::register_routes();
        Rest_Session_Close::register_routes();
        Rest_Unfreeze_Requests::register_routes();
        Rest_Unfreeze_Summary::register_routes();
        Rest_Reviewer_Unfreeze_Requests::register_routes();
        Rest_Panel_Unfreeze_Requests::register_routes();
        Rest_Reviewer_Panel_Unfreeze_Mine::register_routes();
        Rest_Panel_Reports::register_routes();
        Rest_Session_Panel_Report_Settings::register_routes();
        Rest_Backup::register_routes();
        Rest_Smtp::register_routes();
        Rest_Portal::register_routes();
        Rest_Admin_Roles::register_routes();
        Rest_Admin_Reset::register_routes();
    }

    /**
     * @return array{status: string, version: string}
     */
    public static function handle_health(): array
    {
        return [
            'status' => 'ok',
            'version' => defined('PR_PLUGIN_VERSION') ? PR_PLUGIN_VERSION : '0.0.0',
        ];
    }
}
