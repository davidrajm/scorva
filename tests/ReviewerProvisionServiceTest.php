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

    public function test_provision_creates_user_and_session_reviewer_row(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Provision test']);
        $panel_id = $panels->create($session_id, 'Panel A');
        $reviewer_id = $panels->add_reviewer($panel_id, [
            'email' => 'new@example.com',
            'name' => 'New Reviewer',
            'weight' => 1,
        ]);

        $service = new ReviewerProvisionService($sessions, $panels);
        $result = $service->provision_reviewer($session_id, $reviewer_id);

        $this->assertIsArray($result);
        $this->assertTrue($result['created']);
        $this->assertTrue($result['email_sent']);
        $this->assertGreaterThan(0, $result['user_id']);
        $this->assertNotEmpty($result['password']);
        $this->assertCount(1, $GLOBALS['pr_test_sent_mail']);

        $updated = $panels->find_reviewer($reviewer_id);
        $this->assertSame($result['user_id'], (int) ($updated['user_id'] ?? 0));
    }

    public function test_link_existing_user_without_duplicate_account(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Link test']);
        $panel_id = $panels->create($session_id, 'Panel B');
        $reviewer_id = $panels->add_reviewer($panel_id, [
            'email' => '',
            'name' => 'Linked Reviewer',
        ]);

        $existing_id = wp_create_user('existing', 'secret', 'existing@example.com');
        $this->assertIsInt($existing_id);

        $service = new ReviewerProvisionService($sessions, $panels);
        $result = $service->link_existing_user($session_id, $reviewer_id, (int) $existing_id);

        $this->assertTrue($result['linked']);
        $this->assertSame((int) $existing_id, $result['user_id']);
    }
}
