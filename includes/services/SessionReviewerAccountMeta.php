<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class SessionReviewerAccountMeta
{
    public static function user_disabled_on_any_session(object $wpdb, int $user_id): bool
    {
        $table = $wpdb->prefix . 'pr_session_reviewers';
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND disabled_at IS NOT NULL",
            $user_id
        );
        $count = $wpdb->get_var($sql);

        return (int) $count > 0;
    }

    public static function clear_account_disabled_meta_if_unused(object $wpdb, int $user_id): void
    {
        if (
            !self::user_disabled_on_any_session($wpdb, $user_id)
            && function_exists('delete_user_meta')
        ) {
            delete_user_meta($user_id, 'pr_account_disabled');
        }
    }
}
