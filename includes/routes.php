<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SmtpService;

final class Routes
{
    public const QUERY_VAR = 'pr_app';

    public static function register_hooks(): void
    {
        add_filter('query_vars', [self::class, 'add_query_vars']);
        add_action('template_redirect', [self::class, 'handle_template']);
    }

    public static function register_rewrites(): void
    {
        // Reviewer route must register before coordinator catch-all.
        add_rewrite_rule('^reviews/mark/?$', 'index.php?pr_app=reviewer', 'top');
        add_rewrite_rule('^reviews/?$', 'index.php?pr_app=coordinator', 'top');
        // Deep links like /reviews/registry still load the coordinator shell (HashRouter uses #/…).
        add_rewrite_rule('^reviews/.+', 'index.php?pr_app=coordinator', 'top');
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public static function add_query_vars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function handle_template(): void
    {
        $app = get_query_var(self::QUERY_VAR);
        if ($app === '' || $app === false) {
            return;
        }

        if (!in_array($app, ['coordinator', 'reviewer'], true)) {
            return;
        }

        if (!is_user_logged_in()) {
            if ($app === 'reviewer') {
                // Token-portal reviewers have no WordPress account; the
                // reviewer app handles token + password login client-side.
                self::render_reviewer_shell();

                return;
            }

            if (self::is_coordinator_root_request()) {
                self::render_landing_shell();

                return;
            }

            wp_safe_redirect(home_url('/reviews/'));
            self::end_request();

            return;
        }

        if ($app === 'coordinator' && self::is_coordinator_root_request()) {
            self::handle_logged_in_coordinator_root();

            return;
        }

        self::assert_workspace_access($app);

        if (self::workspace_access_blocked()) {
            return;
        }

        if ($app === 'coordinator') {
            self::render_coordinator_shell();

            return;
        }

        self::render_reviewer_shell();
    }

    public static function enqueue_app_assets(string $app): void
    {
        add_action('wp_enqueue_scripts', static function () use ($app): void {
            wp_enqueue_style(
                'scorva-app-shell',
                plugins_url('assets/css/app-shell.css', PR_PLUGIN_FILE),
                [],
                PR_PLUGIN_VERSION
            );

            if (!in_array($app, ['coordinator', 'reviewer', 'landing'], true)) {
                return;
            }

            $asset_path = PR_PLUGIN_DIR . 'build/' . $app . '.asset.php';
            /** @var array{dependencies?: list<string>, version?: string} $asset */
            $asset = file_exists($asset_path)
                ? require $asset_path
                : ['dependencies' => [], 'version' => PR_PLUGIN_VERSION];

            $handle = 'scorva-' . $app;
            $style_handle = $handle . '-styles';

            wp_enqueue_style(
                $style_handle,
                plugins_url('build/' . $app . '.css', PR_PLUGIN_FILE),
                ['scorva-app-shell'],
                $asset['version'] ?? PR_PLUGIN_VERSION
            );

            wp_enqueue_script(
                $handle,
                plugins_url('build/' . $app . '.js', PR_PLUGIN_FILE),
                $asset['dependencies'] ?? [],
                $asset['version'] ?? PR_PLUGIN_VERSION,
                true
            );

            if ($app === 'landing') {
                $pr_app_data = array_merge(self::landing_pr_app_data(), self::branding_pr_app_data());
                wp_localize_script($handle, 'prAppData', $pr_app_data);

                return;
            }

            $app_home = $app === 'reviewer'
                ? home_url('/reviews/mark/')
                : home_url('/reviews/');

            $portal_token = '';
            if ($app === 'reviewer' && isset($_GET['token'])) {
                $raw_token = function_exists('wp_unslash')
                    ? (string) wp_unslash((string) $_GET['token'])
                    : (string) $_GET['token'];
                $raw_token = trim($raw_token);
                if (preg_match('/^[a-f0-9]{64}$/i', $raw_token) === 1) {
                    $portal_token = $raw_token;
                }
            }

            $pr_app_data = [
                'portalToken' => $portal_token,
                'isWpUser' => is_user_logged_in(),
                'restUrl' => rest_url(Rest_Bootstrap::NAMESPACE . '/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'studentImportTemplateUrl' => plugins_url(
                    'assets/csv/students-import-template.csv',
                    PR_PLUGIN_FILE
                ),
                'sessionEnrolTemplateUrl' => plugins_url(
                    'assets/csv/session-enrol-template.csv',
                    PR_PLUGIN_FILE
                ),
                'reviewerImportTemplateUrl' => plugins_url(
                    'assets/csv/reviewers-import-template.csv',
                    PR_PLUGIN_FILE
                ),
                'canAssignReviewers' => current_user_can(PR_CAP_ASSIGN_REVIEWERS),
                'loginUrl' => PluginSettings::login_url_with_redirect($app_home),
                'logoutUrl' => wp_logout_url($app_home),
                'appHomeUrl' => $app_home,
                'coordinatorHomeUrl' => home_url('/reviews/'),
                'markingHomeUrl' => home_url('/reviews/mark/'),
                'canAccessCoordinator' => Capabilities::user_has_coordinator_workspace_access(),
                'canAccessMarking' => current_user_can(PR_CAP_ENTER_MARKS),
                'isPanelHead' => is_user_logged_in() && (new PanelRepository())->is_user_any_panel_head((int) get_current_user_id()),
                'canCloseProject' => current_user_can(PR_CAP_CLOSE_SESSION),
                'canManageProjects' => current_user_can(PR_CAP_MANAGE_SESSIONS),
                'smtpConfigured' => (new SmtpService())->is_configured(),
            ];

            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $pr_app_data['currentUser'] = [
                    'id' => (int) $user->ID,
                    'displayName' => (string) ($user->display_name !== '' ? $user->display_name : $user->user_login),
                    'email' => (string) $user->user_email,
                ];
            }

            $pr_app_data = array_merge($pr_app_data, self::branding_pr_app_data());
            wp_localize_script($handle, 'prAppData', $pr_app_data);
        }, 100000);
    }

    /**
     * @return array{appDisplayName: string, appShortName: string, pluginVersion: string, pluginUri: string}
     */
    private static function branding_pr_app_data(): array
    {
        return [
            'appDisplayName' => PluginSettings::app_display_name(),
            'appShortName' => PluginSettings::app_short_name(),
            'pluginVersion' => defined('PR_PLUGIN_VERSION') ? (string) PR_PLUGIN_VERSION : '',
            'pluginUri' => 'https://github.com/davidrajm/scorva',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function landing_pr_app_data(): array
    {
        $landing_home = home_url('/reviews/');
        $data = [
            'loginUrl' => PluginSettings::login_url_with_redirect($landing_home),
            'appHomeUrl' => $landing_home,
            'coordinatorHomeUrl' => $landing_home,
            'markingHomeUrl' => home_url('/reviews/mark/'),
            'canAccessCoordinator' => Capabilities::user_has_coordinator_workspace_access(),
            'canAccessMarking' => current_user_can(PR_CAP_ENTER_MARKS),
        ];

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $data['currentUser'] = [
                'id' => (int) $user->ID,
                'displayName' => (string) ($user->display_name !== '' ? $user->display_name : $user->user_login),
                'email' => (string) $user->user_email,
            ];
        }

        return $data;
    }

    public static function strip_theme_assets(): void
    {
        add_action('wp_enqueue_scripts', static function (): void {
            $styles = $GLOBALS['wp_styles'] ?? $GLOBALS['pr_test_wp_styles'] ?? null;
            if (is_object($styles) && isset($styles->queue) && is_array($styles->queue)) {
                foreach ($styles->queue as $handle) {
                    wp_dequeue_style((string) $handle);
                    wp_deregister_style((string) $handle);
                }
            }

            $scripts = $GLOBALS['wp_scripts'] ?? $GLOBALS['pr_test_wp_scripts'] ?? null;
            if (is_object($scripts) && isset($scripts->queue) && is_array($scripts->queue)) {
                foreach ($scripts->queue as $handle) {
                    wp_dequeue_script((string) $handle);
                    wp_deregister_script((string) $handle);
                }
            }
        }, 99999);
    }

    private static function handle_logged_in_coordinator_root(): void
    {
        $coord = Capabilities::user_has_coordinator_workspace_access();
        $mark = current_user_can(PR_CAP_ENTER_MARKS);

        if (!$coord && $mark) {
            wp_safe_redirect(home_url('/reviews/mark/'));
            self::end_request();

            return;
        }

        if (!$coord && !$mark) {
            self::deny_workspace_access();

            return;
        }

        self::render_coordinator_shell();
    }

    private static function render_landing_shell(): void
    {
        self::strip_theme_assets();
        self::enqueue_app_assets('landing');
        self::include_app_template('landing');
    }

    private static function render_coordinator_shell(): void
    {
        self::strip_theme_assets();
        self::enqueue_app_assets('coordinator');
        self::include_app_template('coordinator');
    }

    private static function render_reviewer_shell(): void
    {
        self::strip_theme_assets();
        self::enqueue_app_assets('reviewer');
        self::include_app_template('reviewer');
    }

    private static function include_app_template(string $app): void
    {
        $pr_app = $app;
        $template = PR_PLUGIN_DIR . 'templates/app-shell.php';

        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            $GLOBALS['pr_test_template_included'] = $template;
            $GLOBALS['pr_test_template_app'] = $pr_app;
            self::end_request();

            return;
        }

        /** @psalm-suppress UnresolvableInclude */
        include $template;
        self::end_request();
    }

    private static function is_coordinator_root_request(): bool
    {
        return self::current_request_path() === self::reviews_home_path();
    }

    private static function reviews_home_path(): string
    {
        $path = function_exists('wp_parse_url')
            ? wp_parse_url(home_url('/reviews/'), PHP_URL_PATH)
            : parse_url(home_url('/reviews/'), PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/reviews';
        }

        $normalized = rtrim($path, '/');

        return $normalized !== '' ? $normalized : '/reviews';
    }

    private static function current_request_path(): string
    {
        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            $path = (string) ($GLOBALS['pr_test_request_path'] ?? '/reviews/');
        } else {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $path = function_exists('wp_parse_url')
                ? wp_parse_url($uri, PHP_URL_PATH)
                : parse_url($uri, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return self::reviews_home_path();
            }
        }

        $normalized = rtrim($path, '/');

        return $normalized !== '' ? $normalized : '/';
    }

    private static function assert_workspace_access(string $app): void
    {
        if ($app === 'coordinator') {
            if (Capabilities::user_has_coordinator_workspace_access()) {
                return;
            }

            if (current_user_can(PR_CAP_ENTER_MARKS)) {
                wp_safe_redirect(home_url('/reviews/mark/'));
                self::end_request();

                return;
            }

            self::deny_workspace_access();

            return;
        }

        if ($app === 'reviewer') {
            if (current_user_can(PR_CAP_ENTER_MARKS)) {
                return;
            }

            if (Capabilities::user_has_coordinator_workspace_access()) {
                wp_safe_redirect(home_url('/reviews/'));
                self::end_request();

                return;
            }

            self::deny_workspace_access();

            return;
        }
    }

    private static function workspace_access_blocked(): bool
    {
        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            return !empty($GLOBALS['pr_test_redirect_url'])
                || !empty($GLOBALS['pr_test_workspace_denied']);
        }

        return false;
    }

    private static function deny_workspace_access(): void
    {
        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            $GLOBALS['pr_test_workspace_denied'] = true;
            self::end_request();

            return;
        }

        status_header(403);
        wp_die(
            esc_html__('You do not have permission to access this page.', 'scorva'),
            esc_html__('Forbidden', 'scorva'),
            ['response' => 403]
        );
    }

    private static function end_request(): void
    {
        if (defined('PR_UNIT_TEST') && PR_UNIT_TEST) {
            $GLOBALS['pr_test_exit_called'] = true;

            return;
        }

        exit;
    }
}
