-- Remove WordPress users created for E2E / automated tests only.
-- Users must have usermeta pr_test_fixture = 1 (set by tests/e2e/bin/seed-e2e-users.php).
--
-- Prefer: composer test:teardown -- --confirm
-- (truncates pr_* tables and deletes fixture users in one step)

-- Preview fixture users:
SELECT u.ID, u.user_login, u.user_email
FROM wp_users u
INNER JOIN wp_usermeta m ON m.user_id = u.ID AND m.meta_key = 'pr_test_fixture' AND m.meta_value = '1';

-- Manual delete (replace IDs from preview):
-- DELETE FROM wp_usermeta WHERE user_id IN (...);
-- DELETE FROM wp_users WHERE ID IN (...);
