<?php

declare(strict_types=1);

namespace ProjectReviews;

use ProjectReviews\Services\SmtpService;

final class Rest_Smtp
{
    public static function register_routes(): void
    {
        register_rest_route(
            Rest_Bootstrap::NAMESPACE,
            '/settings/smtp/test',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'send_test'],
                'permission_callback' => Rest_Auth::require_cap(PR_CAP_MANAGE_SETTINGS),
            ]
        );
    }

    public static function send_test(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        unset($request);

        $user = wp_get_current_user();
        $to = trim((string) ($user->user_email ?? ''));
        if ($to === '') {
            return new \WP_Error(
                'pr_smtp_no_recipient',
                __('Your account has no email address to send the test to.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $mail_error = null;
        $capture = static function ($error) use (&$mail_error): void {
            if ($error instanceof \WP_Error) {
                $mail_error = $error->get_error_message();
            }
        };
        if (function_exists('add_action')) {
            add_action('wp_mail_failed', $capture);
        }

        $sent = (new SmtpService())->send_test_email($to);

        if (function_exists('remove_action')) {
            remove_action('wp_mail_failed', $capture);
        }

        if (!$sent) {
            $message = __('Test email could not be sent. Check the SMTP settings and try again.', 'project-reviews');
            if (is_string($mail_error) && $mail_error !== '') {
                $message .= ' ' . sprintf(
                    /* translators: %s: mailer error detail */
                    __('Mailer error: %s', 'project-reviews'),
                    $mail_error
                );
            }

            return new \WP_Error('pr_smtp_test_failed', $message, ['status' => 500]);
        }

        return new \WP_REST_Response(['sent' => true, 'to' => $to], 200);
    }
}
