<?php

declare(strict_types=1);

namespace ProjectReviews\Emails;

use ProjectReviews\Services\PluginSettings;

final class ReviewerInviteEmail
{
    /**
     * @return array{subject: string, message: string, headers: list<string>}
     */
    public static function build(
        string $recipient_name,
        string $email,
        string $password,
        string $login_url,
        string $session_title
    ): array {
        $name = $recipient_name !== '' ? $recipient_name : $email;
        $brand = PluginSettings::app_short_name();
        $subject = sprintf(
            /* translators: 1: product short name, 2: review project title */
            __('%1$s: Your reviewer account for %2$s', 'project-reviews'),
            $brand,
            $session_title !== '' ? $session_title : __('a review project', 'project-reviews')
        );

        $message = '<div style="font-family:sans-serif;max-width:560px;color:#1a1a1a;">';
        $message .= '<p style="font-size:18px;font-weight:600;margin:0 0 16px;">';
        $message .= esc_html(PluginSettings::app_display_name());
        $message .= '</p>';
        $message .= '<p>';
        $message .= esc_html(
            sprintf(
                /* translators: %s: recipient display name */
                __('Hello %s,', 'project-reviews'),
                $name
            )
        );
        $message .= '</p>';
        $message .= '<p>';
        $message .= esc_html__(
            'You have been added as a reviewer for this project. Use the credentials below to sign in. These credentials remain valid until the project is closed. We recommend changing your password after your first login.',
            'project-reviews'
        );
        $message .= '</p>';
        $message .= '<table style="margin:16px 0;border-collapse:collapse;">';
        $message .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;">';
        $message .= esc_html__('Login URL', 'project-reviews') . '</td><td>';
        $message .= '<a href="' . esc_url($login_url) . '">' . esc_html($login_url) . '</a></td></tr>';
        $message .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;">';
        $message .= esc_html__('Email', 'project-reviews') . '</td><td>' . esc_html($email) . '</td></tr>';
        $message .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;">';
        $message .= esc_html__('Password', 'project-reviews') . '</td><td>' . esc_html($password) . '</td></tr>';
        $message .= '</table>';
        $message .= '<p style="color:#666;font-size:13px;">';
        $message .= esc_html(
            sprintf(
                /* translators: %s: application display name */
                __('This message was sent by %s. Do not share your password.', 'project-reviews'),
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

    /**
     * @return array{subject: string, message: string, headers: list<string>}
     */
    public static function build_login_reminder(
        string $recipient_name,
        string $email,
        string $login_url,
        string $session_title
    ): array {
        $name = $recipient_name !== '' ? $recipient_name : $email;
        $brand = PluginSettings::app_short_name();
        $subject = sprintf(
            /* translators: 1: product short name, 2: review project title */
            __('%1$s: Reviewer access for %2$s', 'project-reviews'),
            $brand,
            $session_title !== '' ? $session_title : __('a review project', 'project-reviews')
        );

        $message = '<div style="font-family:sans-serif;max-width:560px;color:#1a1a1a;">';
        $message .= '<p style="font-size:18px;font-weight:600;margin:0 0 16px;">';
        $message .= esc_html(PluginSettings::app_display_name());
        $message .= '</p>';
        $message .= '<p>';
        $message .= esc_html(
            sprintf(
                /* translators: %s: recipient display name */
                __('Hello %s,', 'project-reviews'),
                $name
            )
        );
        $message .= '</p>';
        $message .= '<p>';
        $message .= esc_html__(
            'You have been assigned as a reviewer for this project. Sign in with your existing WordPress account using the link below. No new password was issued.',
            'project-reviews'
        );
        $message .= '</p>';
        $message .= '<p><a href="' . esc_url($login_url) . '">' . esc_html($login_url) . '</a></p>';
        $message .= '<p style="color:#666;font-size:13px;">';
        $message .= esc_html__(
            'Reviewer access remains available until the project is closed.',
            'project-reviews'
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
        string $login_url,
        string $session_title
    ): bool {
        if (!function_exists('wp_mail')) {
            return false;
        }

        $login_url = $login_url !== '' ? $login_url : PluginSettings::login_url();
        $built = self::build($recipient_name, $to, $password, $login_url, $session_title);

        return (bool) wp_mail($to, $built['subject'], $built['message'], $built['headers']);
    }

    public static function send_login_reminder(
        string $to,
        string $recipient_name,
        string $login_url,
        string $session_title
    ): bool {
        if (!function_exists('wp_mail')) {
            return false;
        }

        $login_url = $login_url !== '' ? $login_url : PluginSettings::login_url_with_redirect(home_url('/reviews/mark/'));
        $built = self::build_login_reminder($recipient_name, $to, $login_url, $session_title);

        return (bool) wp_mail($to, $built['subject'], $built['message'], $built['headers']);
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
