<?php

declare(strict_types=1);

namespace ProjectReviews\Emails;

use ProjectReviews\Services\PluginSettings;

final class SessionClosedEmail
{
    /**
     * @param array<string, mixed> $session
     * @param list<int> $disabled_user_ids
     */
    public static function send_for_session(array $session, array $disabled_user_ids = []): void
    {
        if (!PluginSettings::notify_session_closed() || !function_exists('wp_mail')) {
            return;
        }

        $title = (string) ($session['title'] ?? __('Review project', 'project-reviews'));
        $subject = sprintf(
            /* translators: 1: product short name, 2: project title */
            __('%1$s: Project closed — %2$s', 'project-reviews'),
            PluginSettings::app_short_name(),
            $title
        );

        $disabled_note = $disabled_user_ids !== []
            ? '<p>' . esc_html(
                sprintf(
                    /* translators: %d: number of accounts */
                    _n(
                        '%d provisioned reviewer account was disabled.',
                        '%d provisioned reviewer accounts were disabled.',
                        count($disabled_user_ids),
                        'project-reviews'
                    ),
                    count($disabled_user_ids)
                )
            ) . '</p>'
            : '';

        $message = '<div style="font-family:sans-serif;max-width:560px;color:#1a1a1a;">'
            . '<p style="font-size:18px;font-weight:600;">' . esc_html(PluginSettings::app_display_name()) . '</p>'
            . '<p>' . esc_html__('The following review project has been closed. No further marks can be submitted.', 'project-reviews') . '</p>'
            . '<p><strong>' . esc_html($title) . '</strong></p>'
            . $disabled_note
            . '</div>';

        $headers = array_merge(
            PluginSettings::mail_headers(),
            ['From: ' . PluginSettings::from_name() . ' <wordpress@' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>']
        );

        if (!function_exists('get_users')) {
            return;
        }

        foreach (get_users(['role' => 'administrator', 'number' => 50]) as $user) {
            $email = (string) ($user->user_email ?? '');
            if ($email !== '') {
                wp_mail($email, $subject, $message, $headers);
            }
        }
    }
}
