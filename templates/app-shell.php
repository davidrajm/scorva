<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $pr_app */
$pr_app = $pr_app ?? '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(\ProjectReviews\Services\PluginSettings::app_display_name()); ?></title>
    <?php wp_head(); ?>
</head>
<body class="pr-app-shell pr-app-<?php echo esc_attr($pr_app); ?>">
    <div id="pr-root" data-app="<?php echo esc_attr($pr_app); ?>"></div>
    <?php wp_footer(); ?>
</body>
</html>
