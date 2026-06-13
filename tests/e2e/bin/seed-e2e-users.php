<?php
/**
 * Seed E2E WordPress users for Playwright UI tests.
 *
 * From WordPress root (e.g. .../app/public):
 *   php wp-content/plugins/scorva/tests/e2e/bin/seed-e2e-users.php
 *
 * Or with WP-CLI:
 *   wp eval-file wp-content/plugins/scorva/tests/e2e/bin/seed-e2e-users.php
 *
 * Creates pr_e2e_coordinator and pr_e2e_reviewer with pr_test_fixture user meta.
 */

if (!defined('ABSPATH')) {
    $wpLoad = dirname(__DIR__, 6) . '/wp-load.php';
    if (!is_readable($wpLoad)) {
        fwrite(STDERR, "WordPress not found at {$wpLoad}.\n");
        fwrite(STDERR, "cd to your WordPress root (folder with wp-load.php), then run:\n");
        fwrite(STDERR, "  php wp-content/plugins/scorva/tests/e2e/bin/seed-e2e-users.php\n");
        exit(1);
    }
    require_once __DIR__ . '/wp-local-db-bootstrap.php';
    require_once $wpLoad;
}

require_once ABSPATH . 'wp-content/plugins/scorva/includes/capabilities.php';

use ProjectReviews\Capabilities;

$fixtures = [
    [
        'login' => getenv('PR_E2E_COORD_USER') ?: 'pr_e2e_coordinator',
        'email' => (getenv('PR_E2E_COORD_EMAIL') ?: 'pr_e2e_coordinator') . '@example.test',
        'role' => Capabilities::ROLE_COORDINATOR,
        'display' => 'E2E Coordinator',
    ],
    [
        'login' => getenv('PR_E2E_REVIEWER_USER') ?: 'pr_e2e_reviewer',
        'email' => getenv('PR_E2E_REVIEWER_EMAIL') ?: 'pr_e2e_reviewer@example.test',
        'role' => Capabilities::ROLE_REVIEWER,
        'display' => 'E2E Reviewer',
    ],
];

$password = getenv('PR_E2E_DEFAULT_PASSWORD') ?: 'pr-e2e-change-me';

$log = static function (string $message): void {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
    } else {
        echo $message . PHP_EOL;
    }
};

foreach ($fixtures as $fixture) {
    $existing = get_user_by('login', $fixture['login']);
    if ($existing) {
        update_user_meta($existing->ID, 'pr_test_fixture', '1');
        delete_user_meta($existing->ID, 'pr_account_disabled');
        $log("Updated fixture meta: {$fixture['login']} (ID {$existing->ID})");
        continue;
    }

    $userId = wp_insert_user([
        'user_login' => $fixture['login'],
        'user_pass' => $password,
        'user_email' => $fixture['email'],
        'display_name' => $fixture['display'],
        'role' => $fixture['role'],
    ]);

    if (is_wp_error($userId)) {
        $msg = $userId->get_error_message();
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::error($msg);
        }
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }

    update_user_meta($userId, 'pr_test_fixture', '1');
    delete_user_meta($userId, 'pr_account_disabled');
    $log("Created {$fixture['login']} (ID {$userId})");
}

$log('Set PR_E2E_COORD_PASS and PR_E2E_REVIEWER_PASS to PR_E2E_DEFAULT_PASSWORD (or your chosen password).');
