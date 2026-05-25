#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Opt-in teardown for E2E / staging data. Never run in CI as part of test suites.
 *
 *   composer test:teardown -- --dry-run
 *   composer test:teardown -- --confirm
 *   composer test:teardown -- --confirm --purge-options
 *   composer test:teardown -- --confirm --full-drop --force-local
 */

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$confirm = in_array('--confirm', $argv, true);
$purgeOptions = in_array('--purge-options', $argv, true);
$fullDrop = in_array('--full-drop', $argv, true);
$forceLocal = in_array('--force-local', $argv, true);

if (getenv('PR_TEST_TEARDOWN_CONFIRM') === '1') {
    $confirm = true;
}

$wpLoad = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wpLoad)) {
    fwrite(STDERR, "WordPress not found at {$wpLoad}. Run from plugin with standard wp-content layout.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/tests/e2e/bin/wp-local-db-bootstrap.php';
require_once $wpLoad;
require_once dirname(__DIR__) . '/includes/Install.php';
require_once dirname(__DIR__) . '/includes/testing/TestTeardown.php';

use ProjectReviews\Testing\TestTeardown;

$result = TestTeardown::run([
    'dry_run' => $dryRun,
    'confirm' => $confirm,
    'purge_options' => $purgeOptions,
    'full_drop' => $fullDrop,
    'force_local' => $forceLocal,
]);

foreach ($result['lines'] as $line) {
    echo $line . PHP_EOL;
}

exit($result['exit_code']);
