<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

/**
 * Legacy settings page — all settings have migrated to the Scorva admin sub-pages.
 * This stub keeps the old slug alive so that bookmarks and external links redirect
 * cleanly to the new General Settings page rather than showing a 404.
 */
final class Admin_Settings
{
    public static function register(): void
    {
        // Re-register the old slug so WordPress resolves it (needed for the redirect).
        add_options_page(
            'Scorva Settings',
            'Scorva',
            PR_CAP_MANAGE_SETTINGS,
            'scorva-settings',
            [self::class, 'render_page']
        );

        // Redirect any direct access before the page renders.
        add_action('admin_init', [self::class, 'redirect_legacy_url']);
    }

    public static function redirect_legacy_url(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'scorva-settings') { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=scorva-general'));
        exit;
    }

    public static function render_page(): void
    {
        // Fallback in case admin_init redirect did not fire.
        wp_safe_redirect(admin_url('admin.php?page=scorva-general'));
        exit;
    }
}
