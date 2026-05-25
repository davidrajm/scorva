# david-sas theme: Reviews link via plugin filter bridge

Add to `header.php` in the logged-in desktop nav (and mirror in the mobile drawer), after timetable / before faculty links:

```php
if (function_exists('pr_theme_nav_items_for_display')) {
    foreach (pr_theme_nav_items_for_display() as $pr_nav_item) {
        $pr_url = (string) ($pr_nav_item['url'] ?? '');
        $pr_lbl = (string) ($pr_nav_item['label'] ?? '');
        $pr_cur = function_exists('sas_tt_is_current_nav_path') && sas_tt_is_current_nav_path($pr_url);
        ?>
        <a href="<?php echo esc_url($pr_url); ?>"
           class="<?php echo esc_attr($nav_link_base . ' ' . ($pr_cur ? $nav_link_current : $nav_link_idle)); ?>">
          <?php echo esc_html($pr_lbl); ?>
        </a>
        <?php
    }
}
```

Extend `sas_tt_is_current_nav_path()` (or equivalent) so paths under `/reviews` mark the link as current.
