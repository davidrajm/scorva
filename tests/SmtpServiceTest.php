<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\SmtpService;

final class SmtpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['pr_test_options'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        require_once dirname(__DIR__) . '/includes/services/PluginSettings.php';
        require_once dirname(__DIR__) . '/includes/services/SmtpService.php';
    }

    protected function tearDown(): void
    {
        $GLOBALS['pr_test_options'] = [];
        $GLOBALS['pr_test_sent_mail'] = [];
        parent::tearDown();
    }

    public function test_defaults_are_unconfigured_tls_587(): void
    {
        $service = new SmtpService();
        $settings = $service->get_settings();

        $this->assertFalse($service->is_configured());
        $this->assertSame('', $settings['host']);
        $this->assertSame(587, $settings['port']);
        $this->assertSame('tls', $settings['encryption']);
        $this->assertSame('', $settings['from_email']);
    }

    public function test_save_settings_round_trip(): void
    {
        $service = new SmtpService();
        $service->save_settings([
            'host' => 'smtp.example.com',
            'port' => 465,
            'username' => 'mailer@example.com',
            'password' => 'secret-pass',
            'encryption' => 'ssl',
            'from_email' => 'noreply@example.com',
        ]);

        $settings = $service->get_settings();
        $this->assertTrue($service->is_configured());
        $this->assertSame('smtp.example.com', $settings['host']);
        $this->assertSame(465, $settings['port']);
        $this->assertSame('mailer@example.com', $settings['username']);
        $this->assertSame('ssl', $settings['encryption']);
        $this->assertSame('noreply@example.com', $settings['from_email']);
    }

    public function test_sanitize_clamps_port_and_rejects_unknown_encryption(): void
    {
        $sanitized = SmtpService::sanitize([
            'host' => 'smtp.example.com',
            'port' => 999999,
            'encryption' => 'starttls-bogus',
        ]);

        $this->assertSame(65535, $sanitized['port']);
        $this->assertSame('tls', $sanitized['encryption']);

        $sanitized = SmtpService::sanitize(['port' => -5]);
        $this->assertSame(1, $sanitized['port']);
    }

    public function test_password_is_not_stored_in_plaintext(): void
    {
        (new SmtpService())->save_settings([
            'host' => 'smtp.example.com',
            'password' => 'secret-pass',
        ]);

        $stored = $GLOBALS['pr_test_options'][SmtpService::OPTION_KEY];
        $this->assertNotSame('secret-pass', $stored['password']);
        $this->assertNotSame('', $stored['password']);
    }

    public function test_blank_password_on_resave_keeps_existing(): void
    {
        $service = new SmtpService();
        $service->save_settings([
            'host' => 'smtp.example.com',
            'password' => 'secret-pass',
        ]);
        $first = $GLOBALS['pr_test_options'][SmtpService::OPTION_KEY]['password'];

        $service->save_settings([
            'host' => 'smtp.other.com',
            'password' => '',
        ]);
        $second = $GLOBALS['pr_test_options'][SmtpService::OPTION_KEY]['password'];

        $this->assertSame($first, $second);
        $this->assertSame('smtp.other.com', $service->get_settings()['host']);
    }

    public function test_sanitize_is_idempotent_for_password(): void
    {
        $first = SmtpService::sanitize([
            'host' => 'smtp.example.com',
            'password' => 'secret-pass',
        ]);

        // register_setting sanitize callbacks run twice when the option is
        // first created; the second pass must not re-encrypt the password.
        $second = SmtpService::sanitize($first);

        $this->assertSame($first['password'], $second['password']);

        $GLOBALS['pr_test_options'][SmtpService::OPTION_KEY] = $second;
        $phpmailer = new FakePhpMailer();
        (new SmtpService())->configure_smtp($phpmailer);
        $this->assertSame('secret-pass', $phpmailer->Password);
    }

    public function test_public_settings_never_expose_password(): void
    {
        $service = new SmtpService();
        $service->save_settings([
            'host' => 'smtp.example.com',
            'password' => 'secret-pass',
        ]);

        $public = $service->get_public_settings();
        $this->assertArrayNotHasKey('password', $public);
        $this->assertTrue($public['has_password']);
        $this->assertTrue($public['is_configured']);
    }

    public function test_send_mail_replaces_from_header_when_from_email_configured(): void
    {
        $service = new SmtpService();
        $service->save_settings([
            'host' => 'smtp.example.com',
            'from_email' => 'noreply@example.com',
        ]);

        $sent = $service->send_mail(
            'reviewer@example.com',
            'Subject',
            'Body',
            ['Content-Type: text/html; charset=UTF-8', 'From: Old <wordpress@localhost>']
        );

        $this->assertTrue($sent);
        $this->assertCount(1, $GLOBALS['pr_test_sent_mail']);
        $mail = $GLOBALS['pr_test_sent_mail'][0];
        $this->assertSame('reviewer@example.com', $mail['to']);

        $from_headers = array_values(array_filter(
            $mail['headers'],
            static fn (string $header): bool => stripos($header, 'from:') === 0
        ));
        $this->assertCount(1, $from_headers);
        $this->assertStringContainsString('noreply@example.com', $from_headers[0]);
        $this->assertStringNotContainsString('wordpress@localhost', $from_headers[0]);
    }

    public function test_send_mail_keeps_headers_when_from_email_empty(): void
    {
        $headers = ['Content-Type: text/html; charset=UTF-8', 'From: Old <wordpress@localhost>'];
        $sent = (new SmtpService())->send_mail('reviewer@example.com', 'Subject', 'Body', $headers);

        $this->assertTrue($sent);
        $this->assertSame($headers, $GLOBALS['pr_test_sent_mail'][0]['headers']);
    }

    public function test_send_test_email_sends_to_recipient(): void
    {
        $sent = (new SmtpService())->send_test_email('admin@example.com');

        $this->assertTrue($sent);
        $this->assertCount(1, $GLOBALS['pr_test_sent_mail']);
        $this->assertSame('admin@example.com', $GLOBALS['pr_test_sent_mail'][0]['to']);
        $this->assertStringContainsString('test email', strtolower($GLOBALS['pr_test_sent_mail'][0]['message']));
    }

    public function test_configure_smtp_applies_settings_to_phpmailer(): void
    {
        $service = new SmtpService();
        $service->save_settings([
            'host' => 'smtp.example.com',
            'port' => 465,
            'username' => 'mailer@example.com',
            'password' => 'secret-pass',
            'encryption' => 'ssl',
        ]);

        $phpmailer = new FakePhpMailer();
        $service->configure_smtp($phpmailer);

        $this->assertTrue($phpmailer->is_smtp_called);
        $this->assertSame('smtp.example.com', $phpmailer->Host);
        $this->assertSame(465, $phpmailer->Port);
        $this->assertTrue($phpmailer->SMTPAuth);
        $this->assertSame('mailer@example.com', $phpmailer->Username);
        $this->assertSame('secret-pass', $phpmailer->Password);
        $this->assertSame('ssl', $phpmailer->SMTPSecure);
    }

    public function test_configure_smtp_is_noop_without_host(): void
    {
        $phpmailer = new FakePhpMailer();
        (new SmtpService())->configure_smtp($phpmailer);

        $this->assertFalse($phpmailer->is_smtp_called);
    }
}

final class FakePhpMailer
{
    public bool $is_smtp_called = false;
    public string $Host = '';
    public int $Port = 0;
    public bool $SMTPAuth = false;
    public string $Username = '';
    public string $Password = '';
    public string $SMTPSecure = '';
    public bool $SMTPAutoTLS = true;

    public function isSMTP(): void
    {
        $this->is_smtp_called = true;
    }
}
