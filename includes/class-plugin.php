<?php

declare(strict_types=1);

namespace ProjectReviews;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init(): void
    {
    }

    public static function activate(): void
    {
        Install::maybe_upgrade();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
