<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\PluginSettings;

final class PluginSettingsBrandingTest extends TestCase
{
    protected function tearDown(): void
    {
        if (function_exists('delete_option')) {
            delete_option(PluginSettings::OPTION_KEY);
        }
        parent::tearDown();
    }

    public function test_app_display_name_defaults_when_option_missing(): void
    {
        if (function_exists('delete_option')) {
            delete_option(PluginSettings::OPTION_KEY);
        }

        $this->assertSame(
            PluginSettings::DEFAULT_APP_DISPLAY_NAME,
            PluginSettings::app_display_name()
        );
    }

    public function test_app_short_name_parses_colon_suffix(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress options unavailable.');
        }

        update_option(PluginSettings::OPTION_KEY, [
            'app_display_name' => 'Acme College: Review Portal',
        ]);

        $this->assertSame('Acme College', PluginSettings::app_short_name());
    }

    public function test_app_short_name_returns_full_name_without_colon(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress options unavailable.');
        }

        update_option(PluginSettings::OPTION_KEY, [
            'app_display_name' => 'Campus Reviews',
        ]);

        $this->assertSame('Campus Reviews', PluginSettings::app_short_name());
    }

    public function test_from_name_falls_back_when_legacy_project_reviews(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress options unavailable.');
        }

        update_option(PluginSettings::OPTION_KEY, [
            'from_name' => PluginSettings::LEGACY_FROM_NAME,
        ]);

        $this->assertSame('Scorva', PluginSettings::from_name());
    }

    public function test_sanitize_persists_default_when_display_name_empty(): void
    {
        $sanitized = PluginSettings::sanitize(['app_display_name' => '   ']);

        $this->assertSame(
            PluginSettings::DEFAULT_APP_DISPLAY_NAME,
            $sanitized['app_display_name']
        );
    }

    public function test_default_scorva_short_name(): void
    {
        if (function_exists('delete_option')) {
            delete_option(PluginSettings::OPTION_KEY);
        }

        $this->assertSame('Scorva', PluginSettings::app_short_name());
    }
}
