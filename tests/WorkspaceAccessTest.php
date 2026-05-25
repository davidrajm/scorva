<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\WorkspaceAccess;

final class WorkspaceAccessTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/workspace-access.php';
        require_once dirname(__DIR__) . '/includes/services/PluginSettings.php';

        $GLOBALS['pr_test_current_user_id'] = 0;
        $GLOBALS['pr_test_user_caps'] = [];
        $GLOBALS['pr_test_user_caps_by_user'] = [];
        $GLOBALS['pr_test_redirect_url'] = null;
        $GLOBALS['pr_test_doing_ajax'] = false;
    }

    public function test_login_redirect_sends_reviewer_only_to_landing(): void
    {
        $user = $this->makeUser(1, [\PR_CAP_ENTER_MARKS => true]);

        $redirect = WorkspaceAccess::filter_login_redirect(
            admin_url(),
            '',
            $user
        );

        $this->assertSame(home_url('/reviews/'), $redirect);
    }

    public function test_login_redirect_honors_plugin_app_redirect_for_reviewer(): void
    {
        $user = $this->makeUser(1, [\PR_CAP_ENTER_MARKS => true]);
        $target = home_url('/reviews/mark/#/mark/1/2/3');

        $redirect = WorkspaceAccess::filter_login_redirect(
            admin_url(),
            $target,
            $user
        );

        $this->assertSame($target, $redirect);
    }

    public function test_login_redirect_sends_coordinator_to_reviews_home(): void
    {
        $user = $this->makeUser(1, [\PR_CAP_MANAGE_SESSIONS => true]);
        $admin = admin_url();

        $redirect = WorkspaceAccess::filter_login_redirect($admin, '', $user);

        $this->assertSame(home_url('/reviews/'), $redirect);
    }

    public function test_block_reviewer_only_admin_redirects(): void
    {
        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_user_caps'] = [\PR_CAP_ENTER_MARKS => true];

        WorkspaceAccess::block_reviewer_only_admin();

        $this->assertSame(home_url('/reviews/mark/'), $GLOBALS['pr_test_redirect_url']);
    }

    public function test_login_url_with_redirect_includes_mark_home(): void
    {
        $url = PluginSettings::login_url_with_redirect(home_url('/reviews/mark/'));

        $this->assertStringContainsString('redirect_to=', $url);
        $this->assertStringContainsString(
            rawurlencode(home_url('/reviews/mark/')),
            $url
        );
    }

    public function test_show_admin_bar_hidden_for_reviewer_only(): void
    {
        $GLOBALS['pr_test_current_user_id'] = 1;
        $GLOBALS['pr_test_user_caps'] = [\PR_CAP_ENTER_MARKS => true];

        $this->assertFalse(WorkspaceAccess::filter_show_admin_bar(true));
    }

    /**
     * @param array<string, bool> $caps
     */
    private function makeUser(int $id, array $caps): \Pr_Test_User
    {
        $user = new \Pr_Test_User();
        $user->ID = $id;
        $GLOBALS['pr_test_user_caps_by_user'][$id] = $caps;

        return $user;
    }
}
