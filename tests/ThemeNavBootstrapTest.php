<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\ThemeNavBootstrap;

final class ThemeNavBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetNavState();
        delete_option(PluginSettings::OPTION_KEY);
        delete_option(ThemeNavBootstrap::OPTION_BOOTSTRAP);
        delete_option(ThemeNavBootstrap::OPTION_STATUS);
    }

    protected function tearDown(): void
    {
        $this->resetNavState();
        delete_option(PluginSettings::OPTION_KEY);
        delete_option(ThemeNavBootstrap::OPTION_BOOTSTRAP);
        delete_option(ThemeNavBootstrap::OPTION_STATUS);
        parent::tearDown();
    }

    public function test_activate_creates_menu_item_once(): void
    {
        require_once dirname(__DIR__) . '/includes/services/PluginSettings.php';
        require_once dirname(__DIR__) . '/includes/services/ThemeNavBootstrap.php';

        $GLOBALS['pr_test_registered_nav_menus'] = ['primary' => 'Primary Menu'];

        ThemeNavBootstrap::on_activate();
        $first = get_option(ThemeNavBootstrap::OPTION_BOOTSTRAP, []);
        $this->assertIsArray($first);
        $menu_id = (int) ($first['menu_id'] ?? 0);
        $item_id = (int) ($first['menu_item_id'] ?? 0);
        $this->assertGreaterThan(0, $menu_id);
        $this->assertGreaterThan(0, $item_id);
        $this->assertSame('ok', get_option(ThemeNavBootstrap::OPTION_STATUS));

        $items_after_first = wp_get_nav_menu_items($menu_id);
        $this->assertIsArray($items_after_first);
        $this->assertCount(1, $items_after_first);

        ThemeNavBootstrap::on_activate();
        $items_after_second = wp_get_nav_menu_items($menu_id);
        $this->assertIsArray($items_after_second);
        $this->assertCount(1, $items_after_second);
        $this->assertSame($item_id, (int) ($items_after_second[0]->ID ?? 0));
    }

    public function test_activate_skips_when_disabled(): void
    {
        require_once dirname(__DIR__) . '/includes/services/PluginSettings.php';
        require_once dirname(__DIR__) . '/includes/services/ThemeNavBootstrap.php';

        update_option(
            PluginSettings::OPTION_KEY,
            array_merge(PluginSettings::get(), ['theme_nav_auto_bootstrap_enabled' => false])
        );

        ThemeNavBootstrap::on_activate();

        $this->assertSame('disabled', get_option(ThemeNavBootstrap::OPTION_STATUS));
        $this->assertFalse(get_option(ThemeNavBootstrap::OPTION_BOOTSTRAP));
    }

    public function test_sync_updates_label_when_settings_change(): void
    {
        require_once dirname(__DIR__) . '/includes/services/PluginSettings.php';
        require_once dirname(__DIR__) . '/includes/services/ThemeNavBootstrap.php';

        $GLOBALS['pr_test_registered_nav_menus'] = ['primary' => 'Primary Menu'];

        ThemeNavBootstrap::on_activate();
        $stored = get_option(ThemeNavBootstrap::OPTION_BOOTSTRAP, []);
        $menu_id = (int) ($stored['menu_id'] ?? 0);

        update_option(
            PluginSettings::OPTION_KEY,
            array_merge(PluginSettings::get(), ['theme_nav_menu_label' => 'Peer Review'])
        );

        ThemeNavBootstrap::sync_menu_item();

        $items = wp_get_nav_menu_items($menu_id);
        $this->assertIsArray($items);
        $this->assertSame('Peer Review', (string) ($items[0]->title ?? ''));
    }

    public function test_filter_returns_reviews_item(): void
    {
        require_once dirname(__DIR__) . '/includes/services/PluginSettings.php';
        require_once dirname(__DIR__) . '/includes/theme-nav.php';

        $items = pr_theme_nav_items_for_display();
        $this->assertNotEmpty($items);
        $reviews = null;
        foreach ($items as $item) {
            if (($item['slug'] ?? '') === 'reviews') {
                $reviews = $item;
                break;
            }
        }

        $this->assertIsArray($reviews);
        $this->assertSame('reviews', $reviews['slug']);
        $this->assertStringContainsString('/reviews', $reviews['url']);
        $this->assertSame('Reviews', $reviews['label']);
    }

    private function resetNavState(): void
    {
        $GLOBALS['pr_test_nav_menus'] = [];
        $GLOBALS['pr_test_nav_menu_items'] = [];
        $GLOBALS['pr_test_nav_menu_locations'] = [];
        $GLOBALS['pr_test_registered_nav_menus'] = [];
        $GLOBALS['pr_test_nav_menu_id_seq'] = 1;
        $GLOBALS['pr_test_nav_menu_item_id_seq'] = 1;
        $GLOBALS['pr_test_theme_mods'] = [];
    }
}
