<?php

declare(strict_types=1);

/**
 * Resolve DB_HOST for WP Local when running PHP CLI (Homebrew php, Terminal, etc.).
 *
 * Local serves the site over php-fpm with the correct MySQL socket; CLI often only
 * has DB_HOST=localhost in wp-config.php, which does not reach Local's MySQL.
 *
 * Include this before wp-load.php. Safe to include multiple times.
 */

if (defined('DB_HOST')) {
    return;
}

$override = getenv('PR_DB_HOST');
if (is_string($override) && $override !== '') {
    define('DB_HOST', $override);

    return;
}

$home = getenv('HOME') ?: '';
if ($home === '') {
    return;
}

$pattern = $home . '/Library/Application Support/Local/run/*/mysql/mysqld.sock';
$sockets = glob($pattern);
if ($sockets === false || $sockets === []) {
    return;
}

// localhost:/path/to/mysqld.sock — WordPress mysqli format for socket connections.
define('DB_HOST', 'localhost:' . $sockets[0]);
