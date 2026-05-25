<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Capabilities;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\FacultyAccountService;
use ProjectReviews\Services\FacultyBridgeService;
use ProjectReviews\Services\ReviewerProvisionService;

final class FacultyAccountServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_users'] = [];
        $GLOBALS['pr_test_user_meta'] = [];
        $GLOBALS['pr_test_user_passwords'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        add_role(Capabilities::ROLE_REVIEWER, 'Reviewer');
    }

    public function test_import_csv_creates_reviewer_with_emp_id_meta(): void
    {
        $service = new FacultyAccountService();
        $result = $service->import_csv([
            [
                'empId' => 'EMP001',
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ],
        ]);

        $this->assertSame(1, $result['imported']);
        $user = get_user_by('email', 'ada@example.com');
        $this->assertNotFalse($user);
        $this->assertContains(Capabilities::ROLE_REVIEWER, $user->roles);
        $this->assertSame('EMP001', get_user_meta((int) $user->ID, 'pr_faculty_emp_id', true));
        $this->assertCount(0, $GLOBALS['pr_test_sent_mail']);
    }

    public function test_import_csv_does_not_change_existing_user_password(): void
    {
        $existing_id = wp_create_user('existing', 'secret', 'existing@example.com');
        $this->assertIsInt($existing_id);
        unset($GLOBALS['pr_test_user_passwords'][$existing_id]);

        $service = new FacultyAccountService();
        $result = $service->import_csv([
            [
                'empId' => 'EMP002',
                'name' => 'Existing Faculty',
                'email' => 'existing@example.com',
            ],
        ], 'update');

        $this->assertSame(1, $result['updated']);
        $this->assertFalse(isset($GLOBALS['pr_test_user_passwords'][$existing_id]));
    }

    public function test_sync_from_directory_skips_inactive_and_empty_email(): void
    {
        $GLOBALS['pr_test_filters']['pr_faculty_list_active'] = [
            [
                'callback' => static function ($rows) {
                    unset($rows);

                    return [
                        [
                            'empId' => 'A1',
                            'name' => 'Active One',
                            'email' => 'active@example.com',
                            'status' => 'Active',
                        ],
                        [
                            'empId' => 'I1',
                            'name' => 'Inactive',
                            'email' => 'inactive@example.com',
                            'status' => 'Inactive',
                        ],
                        [
                            'empId' => 'E1',
                            'name' => 'No Email',
                            'email' => '',
                            'status' => 'Active',
                        ],
                    ];
                },
                'priority' => 10,
                'accepted_args' => 1,
            ],
        ];

        $service = new FacultyAccountService(
            new ReviewerProvisionService(),
            new FacultyBridgeService()
        );
        $result = $service->sync_from_directory();

        $this->assertIsArray($result);
        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertNotFalse(get_user_by('email', 'active@example.com'));
    }

    public function test_bulk_invite_sends_two_emails_for_three_roster_rows(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Bulk invite test']);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');

        $panels->add_reviewer($panel_a, [
            'email' => 'one@example.com',
            'name' => 'Reviewer One',
        ]);
        $panels->add_reviewer($panel_a, [
            'email' => 'two@example.com',
            'name' => 'Reviewer Two',
        ]);
        $panels->add_reviewer($panel_b, [
            'email' => 'one@example.com',
            'name' => 'Reviewer One Duplicate',
        ]);

        $service = new ReviewerProvisionService($sessions, $panels);
        $result = $service->invite_all_session_reviewers($session_id);

        $this->assertIsArray($result);
        $this->assertSame(2, $result['sent']);
        $this->assertCount(2, $GLOBALS['pr_test_sent_mail']);
    }

    public function test_bulk_invite_linked_user_gets_reminder_without_password_reset(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Linked invite']);
        $panel_id = $panels->create($session_id, 'Panel A');
        $existing_id = wp_create_user('linked', 'keep-me', 'linked@example.com');
        $this->assertIsInt($existing_id);
        unset($GLOBALS['pr_test_user_passwords'][$existing_id]);

        $panels->add_reviewer($panel_id, [
            'email' => 'linked@example.com',
            'name' => 'Linked Reviewer',
            'user_id' => $existing_id,
        ]);

        $service = new ReviewerProvisionService($sessions, $panels);
        $result = $service->invite_all_session_reviewers($session_id);

        $this->assertSame(1, $result['sent']);
        $this->assertFalse(isset($GLOBALS['pr_test_user_passwords'][$existing_id]));
        $this->assertStringContainsString(
            'existing WordPress account',
            (string) ($GLOBALS['pr_test_sent_mail'][0]['message'] ?? '')
        );
    }
}
