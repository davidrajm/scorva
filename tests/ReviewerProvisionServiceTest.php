<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Capabilities;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\ReviewerProvisionService;

final class ReviewerProvisionServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_users'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_current_user_id'] = 99;

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        add_role(Capabilities::ROLE_REVIEWER, 'Reviewer');
    }

    public function test_generate_reviewer_credentials_sends_email_and_sets_token(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Credentials test']);
        $panel_id = $panels->create($session_id, 'Panel A');
        $reviewer_id = $panels->add_reviewer($panel_id, [
            'email' => 'rev@example.com',
            'name' => 'Rev Reviewer',
            'weight' => 1,
        ]);

        $service = new ReviewerProvisionService($sessions, $panels);
        $result = $service->generate_reviewer_credentials($session_id, $reviewer_id);

        $this->assertIsArray($result);
        $this->assertTrue($result['token_created']);
        $this->assertTrue($result['email_sent']);
        $this->assertNotEmpty($result['password']);
        $this->assertNotEmpty($result['token']);
        $this->assertCount(1, $GLOBALS['pr_test_sent_mail']);

        $updated = $panels->find_reviewer($reviewer_id);
        $this->assertSame($reviewer_id, (int) ($updated['user_id'] ?? 0));
        $this->assertNotEmpty($updated['token'] ?? '');
    }

    public function test_send_all_reviewer_credentials_skips_already_sent(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Bulk send test']);
        $panel_id = $panels->create($session_id, 'Panel A');

        $r1 = $panels->add_reviewer($panel_id, ['email' => 'one@example.com', 'name' => 'One']);
        $r2 = $panels->add_reviewer($panel_id, ['email' => 'two@example.com', 'name' => 'Two']);
        $panels->update_reviewer($r2, ['credentials_sent_at' => '2026-01-01 00:00:00']);

        $service = new ReviewerProvisionService($sessions, $panels);
        $result = $service->send_all_reviewer_credentials($session_id);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['skipped']);
    }

}
