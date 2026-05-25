<?php

declare(strict_types=1);

/**
 * Public theme navigation bridge (Path B) for custom PHP nav themes.
 */

use ProjectReviews\Services\PluginSettings;

if (!function_exists('pr_theme_nav_items_for_display')) {
    /**
     * @return list<array{url: string, label: string, slug: string}>
     */
    function pr_theme_nav_items_for_display(): array
    {
        $items = apply_filters('pr_theme_nav_items', []);
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = (string) ($item['url'] ?? '');
            $label = (string) ($item['label'] ?? '');
            $slug = (string) ($item['slug'] ?? '');
            if ($url === '' || $label === '') {
                continue;
            }
            if (!apply_filters('pr_theme_nav_show_item', true, $item)) {
                continue;
            }
            $out[] = [
                'url' => function_exists('esc_url') ? esc_url($url) : $url,
                'label' => function_exists('esc_html') ? esc_html($label) : $label,
                'slug' => $slug,
            ];
        }

        return $out;
    }
}

add_filter('pr_theme_nav_items', 'pr_register_default_theme_nav_items', 10, 1);

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function pr_register_default_theme_nav_items(array $items): array
{
    if (!PluginSettings::theme_nav_bridge_enabled()) {
        return $items;
    }

    $label = apply_filters(
        'pr_theme_nav_menu_label',
        PluginSettings::theme_nav_menu_label()
    );

    $items[] = [
        'url' => function_exists('home_url') ? home_url('/reviews/') : '/reviews/',
        'label' => is_string($label) ? $label : 'Reviews',
        'slug' => 'reviews',
    ];

    return $items;
}
