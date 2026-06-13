<?php

declare(strict_types=1);

namespace ProjectReviews\Emails;

use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SmtpService;

final class RubricOpenEmail
{
    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed> $review
     */
    public static function send_for_review(array $session, array $review): void
    {
        if (!PluginSettings::notify_rubric_open() || !function_exists('wp_mail')) {
            return;
        }

        $session_title = (string) ($session['title'] ?? '');
        $review_label = (string) ($review['label'] ?? '');
        $brand = PluginSettings::app_short_name();
        $subject = sprintf(
            /* translators: 1: product short name, 2: review or session label */
            __('%1$s: Rubric confirmed — %2$s', 'scorva'),
            $brand,
            $review_label !== '' ? $review_label : $session_title
        );

        $message = self::wrap(
            '<p>' . esc_html__(
                'A rubric has been confirmed and marking is now open for reviewers.',
                'scorva'
            ) . '</p>'
            . '<p><strong>' . esc_html($session_title) . '</strong> — '
            . esc_html($review_label) . '</p>'
            . '<p><a href="' . esc_url(PluginSettings::login_url()) . '">'
            . esc_html(
                sprintf(
                    /* translators: %s: application display name */
                    __('Sign in to %s', 'scorva'),
                    PluginSettings::app_display_name()
                )
            ) . '</a></p>'
        );

        self::mail_coordinators($subject, $message);
    }

    private static function wrap(string $body): string
    {
        return '<div style="font-family:sans-serif;max-width:560px;color:#1a1a1a;">'
            . '<p style="font-size:18px;font-weight:600;">' . esc_html(PluginSettings::app_display_name()) . '</p>'
            . $body
            . '</div>';
    }

    private static function mail_coordinators(string $subject, string $message): void
    {
        if (!function_exists('get_users')) {
            return;
        }

        $headers = array_merge(
            PluginSettings::mail_headers(),
            ['From: ' . PluginSettings::from_name() . ' <wordpress@' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>']
        );

        $smtp = new SmtpService();
        $users = get_users(['role' => 'administrator', 'number' => 50]);
        foreach ($users as $user) {
            $email = (string) ($user->user_email ?? '');
            if ($email !== '') {
                $smtp->send_mail($email, $subject, $message, $headers);
            }
        }
    }
}
