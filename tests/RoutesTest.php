<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Routes;

final class RoutesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pr_test_rewrite_rules'] = [];
        $GLOBALS['pr_test_query_var'] = '';
        $GLOBALS['pr_test_current_user_id'] = 0;
        $GLOBALS['pr_test_users'] = [];
        $GLOBALS['pr_test_auth_redirect_called'] = false;
        $GLOBALS['pr_test_template_included'] = null;
        $GLOBALS['pr_test_dequeued_styles'] = [];
        $GLOBALS['pr_test_dequeued_scripts'] = [];
        $GLOBALS['pr_test_wp_enqueue_callbacks'] = [];
        $GLOBALS['pr_test_enqueued_styles'] = [];
        $GLOBALS['pr_test_enqueued_scripts'] = [];
        $GLOBALS['pr_test_localized_scripts'] = [];
        $GLOBALS['pr_test_exit_called'] = false;
        $GLOBALS['pr_test_redirect_url'] = null;
        $GLOBALS['pr_test_workspace_denied'] = false;
        $GLOBALS['pr_test_wp_die_message'] = null;
        $GLOBALS['pr_test_user_caps'] = [];
        $GLOBALS['pr_test_wp_styles'] = (object) ['queue' => ['david-sas-theme', 'wp-block-library']];
        $GLOBALS['pr_test_wp_scripts'] = (object) ['queue' => ['david-sas-build', 'jquery']];
        $GLOBALS['pr_test_request_path'] = '/reviews/';
    }

    public function test_register_rewrites_adds_coordinator_and_reviewer_rules(): void
    {
        Routes::register_rewrites();

        $rules = $GLOBALS['pr_test_rewrite_rules'];
        $this->assertSame('index.php?pr_app=reviewer', $rules['^reviews/mark/?$'] ?? null);
        $this->assertSame('index.php?pr_app=coordinator', $rules['^reviews/?$'] ?? null);
        $this->assertSame('index.php?pr_app=coordinator', $rules['^reviews/.+'] ?? null);
    }

    public function test_add_query_vars_includes_pr_app(): void
    {
        $vars = Routes::add_query_vars(['foo']);

        $this->assertContains('pr_app', $vars);
        $this->assertContains('foo', $vars);
    }

    public function test_handle_template_no_ops_when_query_var_empty(): void
    {
        $GLOBALS['pr_test_query_var'] = '';

        Routes::handle_template();

        $this->assertFalse($GLOBALS['pr_test_auth_redirect_called']);
        $this->assertNull($GLOBALS['pr_test_template_included']);
    }

    public function test_guest_coordinator_route_renders_landing_not_auth_redirect(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 0;
        $GLOBALS['pr_test_request_path'] = '/reviews/';

        Routes::handle_template();

        $this->assertFalse($GLOBALS['pr_test_auth_redirect_called']);
        $this->assertSame('landing', $GLOBALS['pr_test_template_app']);
        $this->assertStringContainsString('templates/app-shell.php', (string) $GLOBALS['pr_test_template_included']);
    }

    public function test_guest_reviewer_route_renders_reviewer_shell_for_token_portal(): void
    {
        $GLOBALS['pr_test_query_var'] = 'reviewer';
        $GLOBALS['pr_test_current_user_id'] = 0;

        Routes::handle_template();

        $this->assertFalse($GLOBALS['pr_test_auth_redirect_called']);
        $this->assertNull($GLOBALS['pr_test_redirect_url']);
        $this->assertSame('reviewer', $GLOBALS['pr_test_template_app']);
    }

    public function test_guest_coordinator_deep_path_redirects_to_landing(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 0;
        $GLOBALS['pr_test_request_path'] = '/reviews/registry';

        Routes::handle_template();

        $this->assertFalse($GLOBALS['pr_test_auth_redirect_called']);
        $this->assertSame(home_url('/reviews/'), $GLOBALS['pr_test_redirect_url']);
        $this->assertNull($GLOBALS['pr_test_template_included']);
    }

    public function test_handle_template_renders_coordinator_shell_when_logged_in(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS]);

        Routes::handle_template();

        $this->assertFalse($GLOBALS['pr_test_auth_redirect_called']);
        $this->assertSame('coordinator', $GLOBALS['pr_test_template_app']);
        $this->assertStringContainsString('templates/app-shell.php', (string) $GLOBALS['pr_test_template_included']);
        $this->assertTrue($GLOBALS['pr_test_exit_called']);
    }

    public function test_handle_template_renders_reviewer_shell_when_logged_in(): void
    {
        $GLOBALS['pr_test_query_var'] = 'reviewer';
        $GLOBALS['pr_test_current_user_id'] = 2;
        $this->grantCaps([\PR_CAP_ENTER_MARKS]);

        Routes::handle_template();

        $this->assertSame('reviewer', $GLOBALS['pr_test_template_app']);
    }

    public function test_strip_theme_assets_dequeues_registered_theme_handles(): void
    {
        Routes::strip_theme_assets();

        pr_test_run_wp_enqueue_scripts();

        $this->assertContains('david-sas-theme', $GLOBALS['pr_test_dequeued_styles']);
        $this->assertContains('wp-block-library', $GLOBALS['pr_test_dequeued_styles']);
        $this->assertContains('david-sas-build', $GLOBALS['pr_test_dequeued_scripts']);
    }

    public function test_handle_template_does_not_enqueue_styles_when_query_var_empty(): void
    {
        $GLOBALS['pr_test_query_var'] = '';

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertSame([], $GLOBALS['pr_test_enqueued_styles']);
    }

    public function test_handle_template_enqueues_app_shell_on_coordinator_route(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertAppShellStyleEnqueued();
    }

    public function test_handle_template_enqueues_app_shell_on_reviewer_route(): void
    {
        $GLOBALS['pr_test_query_var'] = 'reviewer';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->grantCaps([\PR_CAP_ENTER_MARKS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertAppShellStyleEnqueued();
    }

    public function test_app_shell_enqueue_runs_after_strip_theme_assets(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertAppShellStyleEnqueued();
        $this->assertContains('david-sas-theme', $GLOBALS['pr_test_dequeued_styles']);
    }

    public function test_handle_template_enqueues_coordinator_spa_assets(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->seedLoggedInReviewerUser();
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS]);
        $GLOBALS['pr_test_rest_nonce'] = 'coordinator-rest-nonce';

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertScriptEnqueued('project-reviews-coordinator', 'build/coordinator.js');
        $this->assertStyleEnqueued('project-reviews-coordinator-styles', 'build/coordinator.css');
        $this->assertPrAppDataLocalized('project-reviews-coordinator');
    }

    public function test_handle_template_enqueues_reviewer_spa_assets(): void
    {
        $GLOBALS['pr_test_query_var'] = 'reviewer';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->seedLoggedInReviewerUser();
        $this->grantCaps([\PR_CAP_ENTER_MARKS]);
        $GLOBALS['pr_test_rest_nonce'] = 'reviewer-rest-nonce';

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertScriptEnqueued('project-reviews-reviewer', 'build/reviewer.js');
        $this->assertStyleEnqueued('project-reviews-reviewer-styles', 'build/reviewer.css');
        $this->assertPrAppDataLocalized('project-reviews-reviewer');
    }

    public function test_handle_template_does_not_enqueue_spa_scripts_when_query_var_empty(): void
    {
        $GLOBALS['pr_test_query_var'] = '';

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertSame([], $GLOBALS['pr_test_enqueued_scripts']);
    }

    public function test_reviewer_only_logged_in_at_reviews_root_redirects_to_mark(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_request_path'] = '/reviews/';
        $this->grantCaps([\PR_CAP_ENTER_MARKS]);

        Routes::handle_template();

        $this->assertSame(home_url('/reviews/mark/'), $GLOBALS['pr_test_redirect_url']);
        $this->assertNull($GLOBALS['pr_test_template_included']);
        $this->assertFalse($GLOBALS['pr_test_workspace_denied']);
        $this->assertFalse($this->isScriptHandleEnqueued('project-reviews-coordinator'));
    }

    public function test_dual_access_logged_in_at_reviews_root_renders_coordinator(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_request_path'] = '/reviews/';
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS, \PR_CAP_ENTER_MARKS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertNull($GLOBALS['pr_test_redirect_url']);
        $this->assertSame('coordinator', $GLOBALS['pr_test_template_app']);
        $this->assertTrue($this->isScriptHandleEnqueued('project-reviews-coordinator'));
        $this->assertFalse($this->isScriptHandleEnqueued('project-reviews-landing'));
    }

    public function test_coordinator_only_logged_in_at_reviews_root_renders_coordinator(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_request_path'] = '/reviews/';
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertNull($GLOBALS['pr_test_redirect_url']);
        $this->assertSame('coordinator', $GLOBALS['pr_test_template_app']);
        $this->assertTrue($this->isScriptHandleEnqueued('project-reviews-coordinator'));
    }

    public function test_coordinator_user_can_load_coordinator_deep_route(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_request_path'] = '/reviews/registry';
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertNull($GLOBALS['pr_test_redirect_url']);
        $this->assertSame('coordinator', $GLOBALS['pr_test_template_app']);
        $this->assertTrue($this->isScriptHandleEnqueued('project-reviews-coordinator'));
    }

    public function test_reviewer_user_can_load_reviewer_route(): void
    {
        $GLOBALS['pr_test_query_var'] = 'reviewer';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->grantCaps([\PR_CAP_ENTER_MARKS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $this->assertNull($GLOBALS['pr_test_redirect_url']);
        $this->assertSame('reviewer', $GLOBALS['pr_test_template_app']);
        $this->assertTrue($this->isScriptHandleEnqueued('project-reviews-reviewer'));
    }

    public function test_pr_app_data_includes_login_logout_urls(): void
    {
        $GLOBALS['pr_test_query_var'] = 'reviewer';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->seedLoggedInReviewerUser();
        $this->grantCaps([\PR_CAP_ENTER_MARKS]);

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $data = $this->getLocalizedPrAppData('project-reviews-reviewer');
        $this->assertNotEmpty($data['loginUrl'] ?? '');
        $this->assertNotEmpty($data['logoutUrl'] ?? '');
        $this->assertSame(home_url('/reviews/mark/'), $data['appHomeUrl'] ?? '');
        $this->assertStringContainsString(
            rawurlencode(home_url('/reviews/mark/')),
            (string) ($data['logoutUrl'] ?? '')
        );
    }

    public function test_coordinator_without_enter_marks_redirected_from_reviewer_route(): void
    {
        $GLOBALS['pr_test_query_var'] = 'reviewer';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $this->grantCaps([\PR_CAP_MANAGE_SESSIONS]);

        Routes::handle_template();

        $this->assertSame(home_url('/reviews/'), $GLOBALS['pr_test_redirect_url']);
        $this->assertNull($GLOBALS['pr_test_template_included']);
    }

    public function test_user_without_workspace_caps_denied_at_reviews_root(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_request_path'] = '/reviews/';

        Routes::handle_template();

        $this->assertTrue($GLOBALS['pr_test_workspace_denied']);
        $this->assertNull($GLOBALS['pr_test_template_included']);
        $this->assertNull($GLOBALS['pr_test_redirect_url']);
    }

    public function test_landing_pr_app_data_excludes_rest_nonce_for_guest(): void
    {
        $GLOBALS['pr_test_query_var'] = 'coordinator';
        $GLOBALS['pr_test_current_user_id'] = 0;
        $GLOBALS['pr_test_request_path'] = '/reviews/';

        Routes::handle_template();
        pr_test_run_wp_enqueue_scripts();

        $data = $this->getLocalizedPrAppData('project-reviews-landing');
        $this->assertNotEmpty($data['loginUrl'] ?? '');
        $this->assertSame(home_url('/reviews/'), $data['appHomeUrl'] ?? '');
        $this->assertSame(
            \ProjectReviews\Services\PluginSettings::DEFAULT_APP_DISPLAY_NAME,
            $data['appDisplayName'] ?? null
        );
        $this->assertSame('Scorva', $data['appShortName'] ?? null);
        $this->assertArrayNotHasKey('nonce', $data);
        $this->assertArrayNotHasKey('restUrl', $data);
    }

    private function assertScriptEnqueued(string $handle, string $srcFragment): void
    {
        $handles = array_column($GLOBALS['pr_test_enqueued_scripts'], 'handle');
        $this->assertContains($handle, $handles);

        $match = null;
        foreach ($GLOBALS['pr_test_enqueued_scripts'] as $script) {
            if ($script['handle'] === $handle) {
                $match = $script;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertStringContainsString($srcFragment, (string) $match['src']);
        $this->assertTrue($match['in_footer']);
    }

    private function assertStyleEnqueued(string $handle, string $srcFragment): void
    {
        $handles = array_column($GLOBALS['pr_test_enqueued_styles'], 'handle');
        $this->assertContains($handle, $handles);

        $match = null;
        foreach ($GLOBALS['pr_test_enqueued_styles'] as $style) {
            if ($style['handle'] === $handle) {
                $match = $style;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertStringContainsString($srcFragment, (string) $match['src']);
    }

    private function assertPrAppDataLocalized(string $handle): void
    {
        $match = null;
        foreach ($GLOBALS['pr_test_localized_scripts'] as $localized) {
            if ($localized['handle'] === $handle && $localized['object_name'] === 'prAppData') {
                $match = $localized;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertStringContainsString('project-reviews/v1/', (string) ($match['data']['restUrl'] ?? ''));
        $this->assertNotEmpty($match['data']['nonce'] ?? '');
        $this->assertSame(
            \ProjectReviews\Services\PluginSettings::DEFAULT_APP_DISPLAY_NAME,
            $match['data']['appDisplayName'] ?? null
        );
        $this->assertSame('Scorva', $match['data']['appShortName'] ?? null);

        $current_user = $match['data']['currentUser'] ?? null;
        $this->assertIsArray($current_user);
        $this->assertSame(1, $current_user['id'] ?? null);
        $this->assertSame('Test Reviewer', $current_user['displayName'] ?? null);
        $this->assertSame('reviewer@example.test', $current_user['email'] ?? null);
    }

    /**
     * @param list<string> $caps
     */
    private function grantCaps(array $caps): void
    {
        $GLOBALS['pr_test_user_caps'] = [];
        foreach ($caps as $cap) {
            $GLOBALS['pr_test_user_caps'][$cap] = true;
        }
    }

    private function isScriptHandleEnqueued(string $handle): bool
    {
        foreach ($GLOBALS['pr_test_enqueued_scripts'] as $script) {
            if ($script['handle'] === $handle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function getLocalizedPrAppData(string $handle): array
    {
        foreach ($GLOBALS['pr_test_localized_scripts'] as $localized) {
            if ($localized['handle'] === $handle && $localized['object_name'] === 'prAppData') {
                return $localized['data'];
            }
        }

        return [];
    }

    private function seedLoggedInReviewerUser(): void
    {
        $user = new \Pr_Test_User();
        $user->ID = 1;
        $user->user_login = 'reviewer1';
        $user->user_email = 'reviewer@example.test';
        $user->display_name = 'Test Reviewer';
        $GLOBALS['pr_test_users'][1] = $user;
    }

    private function assertAppShellStyleEnqueued(): void
    {
        $handles = array_column($GLOBALS['pr_test_enqueued_styles'], 'handle');
        $this->assertContains('project-reviews-app-shell', $handles);

        $match = null;
        foreach ($GLOBALS['pr_test_enqueued_styles'] as $style) {
            if ($style['handle'] === 'project-reviews-app-shell') {
                $match = $style;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertStringContainsString('assets/css/app-shell.css', (string) $match['src']);
    }
}
