<?php

declare(strict_types=1);

namespace ProjectReviews\Emails;

use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SmtpService;

/**
 * Token-portal access email: a personal review link plus a password.
 * No WordPress account is involved.
 */
final class ReviewerCredentialsEmail
{
    /**
     * @return array{subject: string, message: string, headers: list<string>}
     */
    public static function build(
        string $recipient_name,
        string $email,
        string $password,
        string $portal_url,
        string $session_title
    ): array {
        $name = $recipient_name !== '' ? $recipient_name : $email;
        $brand = PluginSettings::app_short_name();
        $subject = sprintf(
            /* translators: 1: product short name, 2: review project title */
            __('%1$s: Your reviewer access for %2$s', 'scorva'),
            $brand,
            $session_title !== '' ? $session_title : __('a review project', 'scorva')
        );

        $message = '<div style="font-family:sans-serif;max-width:560px;color:#1a1a1a;">';
        $message .= '<p style="font-size:18px;font-weight:600;margin:0 0 16px;">';
        $message .= esc_html(PluginSettings::app_display_name());
        $message .= '</p>';
        $message .= '<p>';
        $message .= esc_html(
            sprintf(
                /* translators: %s: recipient display name */
                __('Hello %s,', 'scorva'),
                $name
            )
        );
        $message .= '</p>';
        $message .= '<p>';
        $message .= esc_html__(
            'You have been added as a reviewer for this project. Open your personal review link below and enter the password to start marking. No account is required.',
            'scorva'
        );
        $message .= '</p>';
        $message .= '<p style="margin:20px 0;">';
        $message .= '<a href="' . esc_url($portal_url) . '" '
            . 'style="background:#2271b1;color:#ffffff;padding:10px 20px;border-radius:4px;'
            . 'text-decoration:none;font-weight:600;display:inline-block;">';
        $message .= esc_html__('Open review portal', 'scorva');
        $message .= '</a></p>';
        $message .= '<table style="margin:16px 0;border-collapse:collapse;">';
        $message .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;">';
        $message .= esc_html__('Review link', 'scorva') . '</td><td>';
        $message .= '<a href="' . esc_url($portal_url) . '">' . esc_html($portal_url) . '</a></td></tr>';
        $message .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;">';
        $message .= esc_html__('Password', 'scorva') . '</td><td>' . esc_html($password) . '</td></tr>';
        $message .= '</table>';
        $message .= '<p style="color:#666;font-size:13px;">';
        $message .= esc_html(
            sprintf(
                /* translators: %s: application display name */
                __('This link and password are unique to you — do not share them. Sent by %s.', 'scorva'),
                PluginSettings::app_display_name()
            )
        );
        $message .= '</p></div>';

        return [
            'subject' => $subject,
            'message' => $message,
            'headers' => self::headers(),
        ];
    }

    public static function send(
        string $to,
        string $recipient_name,
        string $password,
        string $portal_url,
        string $session_title
    ): bool {
        if (!function_exists('wp_mail')) {
            return false;
        }

        $built = self::build($recipient_name, $to, $password, $portal_url, $session_title);

        return (new SmtpService())->send_mail($to, $built['subject'], $built['message'], $built['headers']);
    }

    /**
     * @return list<string>
     */
    private static function headers(): array
    {
        $headers = PluginSettings::mail_headers();
        $from = PluginSettings::from_name();
        if ($from !== '') {
            $headers[] = 'From: ' . $from . ' <wordpress@' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>';
        }

        return $headers;
    }
}
