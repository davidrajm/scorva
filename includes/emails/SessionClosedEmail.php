<?php

declare(strict_types=1);

namespace ProjectReviews\Emails;

use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SmtpService;

final class SessionClosedEmail
{
    /**
     * @param array<string, mixed> $session
     */
    public static function send_for_session(array $session): void
    {
        if (!PluginSettings::notify_session_closed() || !function_exists('wp_mail')) {
            return;
        }

        $title = (string) ($session['title'] ?? __('Review project', 'scorva'));
        $subject = sprintf(
            /* translators: 1: product short name, 2: project title */
            __('%1$s: Project closed — %2$s', 'scorva'),
            PluginSettings::app_short_name(),
            $title
        );

        $message = '<div style="font-family:sans-serif;max-width:560px;color:#1a1a1a;">'
            . '<p style="font-size:18px;font-weight:600;">' . esc_html(PluginSettings::app_display_name()) . '</p>'
            . '<p>' . esc_html__('The following review project has been closed. Reviewer portal access is suspended and no further marks can be submitted.', 'scorva') . '</p>'
            . '<p><strong>' . esc_html($title) . '</strong></p>'
            . '</div>';

        $headers = array_merge(
            PluginSettings::mail_headers(),
            ['From: ' . PluginSettings::from_name() . ' <wordpress@' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>']
        );

        if (!function_exists('get_users')) {
            return;
        }

        $smtp = new SmtpService();
        foreach (get_users(['role' => 'administrator', 'number' => 50]) as $user) {
            $email = (string) ($user->user_email ?? '');
            if ($email !== '') {
                $smtp->send_mail($email, $subject, $message, $headers);
            }
        }
    }
}
