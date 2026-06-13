<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class SmtpService
{
    public const OPTION_KEY = 'pr_smtp_settings';

    private const DEFAULT_PORT = 587;

    private const ENCRYPTION_MODES = ['none', 'tls', 'ssl'];

    /**
     * @return array<string, mixed>
     */
    public function get_settings(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge(self::defaults(), $stored);
    }

    /**
     * Settings safe to expose to the admin UI. Password is never included.
     *
     * @return array<string, mixed>
     */
    public function get_public_settings(): array
    {
        $settings = $this->get_settings();
        unset($settings['password']);
        $settings['has_password'] = $this->stored_password() !== '';
        $settings['is_configured'] = $this->is_configured();

        return $settings;
    }

    /**
     * Sanitize callback for register_setting. A blank password keeps the
     * previously stored one so admins can edit other fields without
     * re-entering credentials.
     *
     * @param mixed $input
     * @return array<string, mixed>
     */
    public static function sanitize($input): array
    {
        if (!is_array($input)) {
            $input = [];
        }

        $host = trim((string) ($input['host'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $from_email = trim((string) ($input['from_email'] ?? ''));
        if (function_exists('sanitize_text_field')) {
            $host = sanitize_text_field($host);
            $username = sanitize_text_field($username);
        }
        if (function_exists('sanitize_email')) {
            $from_email = sanitize_email($from_email);
        }

        $port = (int) ($input['port'] ?? self::DEFAULT_PORT);
        $port = max(1, min(65535, $port));

        $encryption = (string) ($input['encryption'] ?? 'tls');
        if (!in_array($encryption, self::ENCRYPTION_MODES, true)) {
            $encryption = 'tls';
        }

        $password = (string) ($input['password'] ?? '');
        if ($password !== '') {
            $password = self::encrypt_secret($password);
        } else {
            $existing = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];
            $password = is_array($existing) ? (string) ($existing['password'] ?? '') : '';
        }

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_email' => $from_email,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function save_settings(array $settings): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        update_option(self::OPTION_KEY, self::sanitize($settings));
    }

    public function is_configured(): bool
    {
        return trim((string) ($this->get_settings()['host'] ?? '')) !== '';
    }

    public function from_email(): string
    {
        return trim((string) ($this->get_settings()['from_email'] ?? ''));
    }

    /**
     * phpmailer_init hook callback. No-op when SMTP host is not configured,
     * leaving the WordPress default transport untouched.
     *
     * @param object $phpmailer
     */
    public function configure_smtp(object $phpmailer): void
    {
        $settings = $this->get_settings();
        $host = trim((string) ($settings['host'] ?? ''));
        if ($host === '') {
            return;
        }

        $port = (int) ($settings['port'] ?? self::DEFAULT_PORT);
        $username = (string) ($settings['username'] ?? '');
        $password = self::decrypt_secret((string) ($settings['password'] ?? ''));
        $encryption = (string) ($settings['encryption'] ?? 'tls');

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = $port > 0 ? $port : self::DEFAULT_PORT;
        $phpmailer->SMTPAuth = $username !== '';
        $phpmailer->Username = $username;
        $phpmailer->Password = $password;

        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }
    }

    /**
     * wp_mail wrapper that routes the message through the configured SMTP
     * server. When a from address is configured it replaces any From header
     * so the envelope matches the authenticated SMTP account.
     *
     * @param list<string> $headers
     */
    public function send_mail(string $to, string $subject, string $message, array $headers = []): bool
    {
        if (!function_exists('wp_mail')) {
            return false;
        }

        $headers = $this->apply_from_header($headers);

        $hooked = $this->is_configured() && function_exists('add_action');
        if ($hooked) {
            add_action('phpmailer_init', [$this, 'configure_smtp']);
        }

        $sent = (bool) wp_mail($to, $subject, $message, $headers);

        if ($hooked && function_exists('remove_action')) {
            remove_action('phpmailer_init', [$this, 'configure_smtp']);
        }

        return $sent;
    }

    public function send_test_email(string $to): bool
    {
        $brand = PluginSettings::app_short_name();
        $subject = sprintf(
            /* translators: %s: product short name */
            __('%s: SMTP test email', 'scorva'),
            $brand
        );
        $message = sprintf(
            /* translators: %s: application display name */
            __('This is a test email from %s. If you received this, your email settings are working correctly.', 'scorva'),
            PluginSettings::app_display_name()
        );

        return $this->send_mail($to, $subject, $message, ['Content-Type: text/plain; charset=UTF-8']);
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    private function apply_from_header(array $headers): array
    {
        $from_email = $this->from_email();
        if ($from_email === '') {
            return $headers;
        }

        $headers = array_values(array_filter(
            $headers,
            static fn (string $header): bool => stripos(trim($header), 'from:') !== 0
        ));
        $headers[] = sprintf('From: %s <%s>', PluginSettings::from_name(), $from_email);

        return $headers;
    }

    private function stored_password(): string
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];

        return is_array($stored) ? (string) ($stored['password'] ?? '') : '';
    }

    private static function encrypt_secret(string $plaintext): string
    {
        return Crypto::encrypt($plaintext);
    }

    private static function decrypt_secret(string $encrypted): string
    {
        return Crypto::decrypt($encrypted);
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'host' => '',
            'port' => self::DEFAULT_PORT,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_email' => '',
        ];
    }
}
