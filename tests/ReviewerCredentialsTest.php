<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\ReviewerProvisionService;
use ProjectReviews\Services\TokenService;
use WP_Error;

final class ReviewerCredentialsTest extends TestCase
{
    private FakeWpdb $wpdb;

    private SessionRepository $sessions;

    private PanelRepository $panels;

    private ReviewerProvisionService $service;

    private int $session_id;

    private int $panel_id;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/tests/RestAuthTest.php';
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_options'] = [];
        $GLOBALS['pr_test_current_user_id'] = 99;

        $this->sessions = new SessionRepository($this->wpdb);
        $this->panels = new PanelRepository($this->wpdb);
        $this->service = new ReviewerProvisionService($this->sessions, $this->panels);

        $this->session_id = $this->sessions->create(['title' => 'Credentials test']);
        $this->panel_id = $this->panels->create($this->session_id, 'Panel A');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        $GLOBALS['pr_test_sent_mail'] = [];
        $GLOBALS['pr_test_options'] = [];
        parent::tearDown();
    }

    private function add_reviewer(string $email = 'reviewer@example.com', string $name = 'Dr. Reviewer'): int
    {
        return $this->panels->add_reviewer($this->panel_id, [
            'email' => $email,
            'name' => $name,
            'weight' => 1,
        ]);
    }

    public function test_generate_creates_token_and_password_and_sends_email(): void
    {
        $reviewer_id = $this->add_reviewer();

        $result = $this->service->generate_reviewer_credentials($this->session_id, $reviewer_id);

        $this->assertIsArray($result);
        $this->assertTrue($result['token_created']);
        $this->assertTrue($result['email_sent']);
        $this->assertNotNull($result['credentials_sent_at']);

        $tokens = new TokenService();
        $this->assertTrue($tokens->is_valid_token_format($result['token']));

        $stored = $this->panels->find_reviewer($reviewer_id);
        $this->assertSame($result['token'], $stored['token']);
        $this->assertTrue($tokens->verify_password($result['password'], (string) $stored['password_hash']));
        $this->assertSame($result['password'], $tokens->decrypt_password((string) $stored['password_encrypted']));
        $this->assertNotEmpty($stored['credentials_sent_at']);

        $this->assertCount(1, $GLOBALS['pr_test_sent_mail']);
        $mail = $GLOBALS['pr_test_sent_mail'][0];
        $this->assertSame('reviewer@example.com', $mail['to']);
        $this->assertStringContainsString('token=' . $result['token'], $mail['message']);
        $this->assertStringContainsString($result['password'], $mail['message']);
    }

    public function test_resend_keeps_token_and_rotates_password(): void
    {
        $reviewer_id = $this->add_reviewer();

        $first = $this->service->generate_reviewer_credentials($this->session_id, $reviewer_id);
        $second = $this->service->resend_reviewer_credentials($this->session_id, $reviewer_id);

        $this->assertIsArray($second);
        $this->assertFalse($second['token_created']);
        $this->assertSame($first['token'], $second['token']);
        $this->assertNotSame($first['password'], $second['password']);

        $tokens = new TokenService();
        $stored = $this->panels->find_reviewer($reviewer_id);
        $this->assertFalse($tokens->verify_password($first['password'], (string) $stored['password_hash']));
        $this->assertTrue($tokens->verify_password($second['password'], (string) $stored['password_hash']));

        $this->assertCount(2, $GLOBALS['pr_test_sent_mail']);
    }

    public function test_generate_requires_email(): void
    {
        $reviewer_id = $this->add_reviewer('', 'No Email');

        $result = $this->service->generate_reviewer_credentials($this->session_id, $reviewer_id);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_reviewer_missing_email', $result->get_error_code());
        $this->assertSame([], $GLOBALS['pr_test_sent_mail']);
    }

    public function test_generate_rejects_reviewer_from_other_session(): void
    {
        $other_session = $this->sessions->create(['title' => 'Other']);
        $reviewer_id = $this->add_reviewer();

        $result = $this->service->generate_reviewer_credentials($other_session, $reviewer_id);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_reviewer_not_found', $result->get_error_code());
    }

    public function test_bulk_send_skips_already_sent_unless_forced(): void
    {
        $first = $this->add_reviewer('a@example.com', 'Reviewer A');
        $this->add_reviewer('b@example.com', 'Reviewer B');
        $this->add_reviewer('', 'No Email');

        $this->service->generate_reviewer_credentials($this->session_id, $first);
        $GLOBALS['pr_test_sent_mail'] = [];

        $result = $this->service->send_all_reviewer_credentials($this->session_id);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['sent']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertCount(1, $GLOBALS['pr_test_sent_mail']);
        $this->assertSame('b@example.com', $GLOBALS['pr_test_sent_mail'][0]['to']);

        $reasons = array_column(
            array_filter($result['details'], static fn (array $row): bool => $row['status'] === 'skipped'),
            'reason'
        );
        $this->assertContains('already_sent', $reasons);
        $this->assertContains('missing_email', $reasons);

        $GLOBALS['pr_test_sent_mail'] = [];
        $forced = $this->service->send_all_reviewer_credentials($this->session_id, true);
        $this->assertSame(2, $forced['sent']);
        $this->assertSame(1, $forced['skipped']);
        $this->assertCount(2, $GLOBALS['pr_test_sent_mail']);
    }
}
