<?php

declare(strict_types=1);

namespace ProjectReviews;

final class Install
{
    public static function get_schema_sql(string $prefix = '', string $charset_collate = ''): string
    {
        $tables = [
            self::table_programs($prefix, $charset_collate),
            self::table_students($prefix, $charset_collate),
            self::table_field_definitions($prefix, $charset_collate),
            self::table_student_meta($prefix, $charset_collate),
            self::table_sessions($prefix, $charset_collate),
            self::table_session_students($prefix, $charset_collate),
            self::table_panels($prefix, $charset_collate),
            self::table_panel_reviewers($prefix, $charset_collate),
            self::table_review_reviewer_overrides($prefix, $charset_collate),
            self::table_review_student_panels($prefix, $charset_collate),
            self::table_review_student_attendance_by_reviewer($prefix, $charset_collate),
            self::table_review_panel_reviewers($prefix, $charset_collate),
            self::table_reviews($prefix, $charset_collate),
            self::table_rubric_criteria($prefix, $charset_collate),
            self::table_review_weights($prefix, $charset_collate),
            self::table_reviewer_weights($prefix, $charset_collate),
            self::table_marks($prefix, $charset_collate),
            self::table_mark_audit($prefix, $charset_collate),
            self::table_unfreeze_requests($prefix, $charset_collate),
        ];

        return implode("\n", $tables);
    }

    public static function maybe_upgrade(): void
    {
        if (!function_exists('get_option') || !defined('PR_PLUGIN_VERSION')) {
            return;
        }

        $installed_version = (string) get_option('pr_db_version', '0');
        if (version_compare($installed_version, PR_PLUGIN_VERSION, '>=')) {
            return;
        }

        global $wpdb;
        if (!isset($wpdb)) {
            return;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $sql = self::get_schema_sql($wpdb->prefix, $wpdb->get_charset_collate());
        dbDelta($sql);

        self::ensure_schema_patches();

        update_option('pr_db_version', PR_PLUGIN_VERSION);
    }

    /**
     * Apply incremental schema fixes that dbDelta may skip on existing tables.
     */
    public static function ensure_schema_patches(): void
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return;
        }

        self::ensure_core_tables($wpdb);
        self::ensure_panel_reviewer_name_column($wpdb);
        self::ensure_rubric_scores_view($wpdb);
        self::ensure_marking_active_column($wpdb);
        self::ensure_review_assignment_tables($wpdb);
        self::ensure_attendance_status_column($wpdb);
        self::ensure_project_title_columns($wpdb);
        self::ensure_student_program_column($wpdb);
        self::ensure_enrolment_guide_columns($wpdb);
        self::backfill_guide_name_to_enrolments($wpdb);
        self::drop_students_guide_name_column($wpdb);
        self::ensure_review_student_attendance_by_reviewer_table($wpdb);
        self::ensure_unfreeze_requests_table($wpdb);
        self::ensure_unfreeze_request_reason_column($wpdb);
        self::ensure_coordinator_marks_locked_column($wpdb);
        self::ensure_coordinator_override_columns($wpdb);
        self::ensure_panel_head_columns($wpdb);
        self::ensure_review_panel_freezes_table($wpdb);
        self::ensure_panel_unfreeze_requests_table($wpdb);
        self::backfill_review_assignments($wpdb);
        self::backfill_missing_review_panel_reviewers($wpdb);
        self::ensure_reviewer_credentials_columns($wpdb);
        self::ensure_programs_table($wpdb);
        self::backfill_programs_from_students($wpdb);
    }

    /**
     * Token-portal credentials on panel reviewers (token, bcrypt hash,
     * encrypted copy for resend, sent timestamp).
     *
     * @return bool True when all credential columns exist after this call.
     */
    public static function ensure_reviewer_credentials_columns(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_panel_reviewers';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        $columns = [
            'token' => "varchar(64) DEFAULT NULL",
            'password_hash' => "varchar(255) DEFAULT NULL",
            'password_encrypted' => "text DEFAULT NULL",
            'credentials_sent_at' => "datetime DEFAULT NULL",
        ];

        $ok = true;
        $previous = 'is_panel_head';
        foreach ($columns as $column => $definition) {
            if (!self::column_exists($wpdb, $table, $column)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition} AFTER {$previous}");
                $ok = self::column_exists($wpdb, $table, $column) && $ok;
            }
            $previous = $column;
        }

        if (self::column_exists($wpdb, $table, 'token') && !self::index_exists($wpdb, $table, 'token')) {
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY token (token)");
        }

        return $ok;
    }

    /**
     * DDL for the flat rubric-scores export view (one row per mark × criterion).
     *
     * Canonical storage remains {@see table_marks()} (`pr_marks`). This view exposes
     * project (session), review, reg_no, reviewer, rubric (criterion), and score
     * for SQL exports and downstream report queries.
     */
    public static function rubric_scores_view_ddl(string $prefix): string
    {
        $view = $prefix . 'pr_rubric_scores';
        $marks = $prefix . 'pr_marks';
        $students = $prefix . 'pr_students';

        return "CREATE VIEW {$view} AS
SELECT
    m.session_id AS project_id,
    m.review_id,
    s.reg_no,
    m.reviewer_user_id AS reviewer_id,
    m.criterion_id AS rubric_id,
    m.score,
    m.status,
    m.flagged,
    m.coordinator_overridden,
    m.overridden_from_score,
    m.id AS mark_id
FROM {$marks} m
INNER JOIN {$students} s ON s.id = m.student_id";
    }

    /**
     * @return bool True when the view exists (or was created successfully).
     */
    public static function ensure_rubric_scores_view(object $wpdb): bool
    {
        $view = $wpdb->prefix . 'pr_rubric_scores';
        $marks = $wpdb->prefix . 'pr_marks';
        $students = $wpdb->prefix . 'pr_students';

        if (!self::table_exists($wpdb, $marks) || !self::table_exists($wpdb, $students)) {
            return false;
        }

        $needs_refresh = !self::view_exists($wpdb, $view);
        if (
            !$needs_refresh
            && self::column_exists($wpdb, $marks, 'coordinator_overridden')
            && !self::column_exists($wpdb, $view, 'coordinator_overridden')
        ) {
            $needs_refresh = true;
        }

        if ($needs_refresh) {
            return self::refresh_rubric_scores_view($wpdb);
        }

        return true;
    }

    public static function refresh_rubric_scores_view(object $wpdb): bool
    {
        $view = $wpdb->prefix . 'pr_rubric_scores';
        $marks = $wpdb->prefix . 'pr_marks';
        $students = $wpdb->prefix . 'pr_students';

        if (!self::table_exists($wpdb, $marks) || !self::table_exists($wpdb, $students)) {
            return false;
        }

        $wpdb->query("DROP VIEW IF EXISTS {$view}");
        $wpdb->query(self::rubric_scores_view_ddl($wpdb->prefix));

        return self::view_exists($wpdb, $view);
    }

    /**
     * Create base plugin tables when missing (e.g. plugin copied without re-activation).
     *
     * @return bool True when pr_sessions exists after this call.
     */
    public static function ensure_core_tables(object $wpdb): bool
    {
        $sessions = $wpdb->prefix . 'pr_sessions';
        if (self::table_exists($wpdb, $sessions)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'utf8mb4_unicode_ci';
        dbDelta(self::get_schema_sql($wpdb->prefix, $charset));

        if (function_exists('update_option') && defined('PR_PLUGIN_VERSION')) {
            update_option('pr_db_version', PR_PLUGIN_VERSION);
        }

        return self::table_exists($wpdb, $sessions);
    }

    /**
     * @return bool True when the name column exists (or was added successfully).
     */
    public static function ensure_panel_reviewer_name_column(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_panel_reviewers';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        if (self::column_exists($wpdb, $table, 'name')) {
            return true;
        }

        $sql = "ALTER TABLE {$table} ADD COLUMN name varchar(255) NOT NULL DEFAULT '' AFTER panel_id";
        $wpdb->query($sql);

        return self::column_exists($wpdb, $table, 'name');
    }

    private static function table_exists(object $wpdb, string $table): bool
    {
        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return is_string($found) && $found === $table;
    }

    private static function column_exists(object $wpdb, string $table, string $column): bool
    {
        $row = $wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column)
        );

        return is_array($row) && $row !== [];
    }

    private static function index_exists(object $wpdb, string $table, string $index): bool
    {
        $rows = $wpdb->get_results(
            $wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index)
        );

        return is_array($rows) && $rows !== [];
    }

    private static function view_exists(object $wpdb, string $view): bool
    {
        $rows = $wpdb->get_results(
            $wpdb->prepare('SHOW FULL TABLES LIKE %s', $view),
            'ARRAY_A'
        );

        if (!is_array($rows) || $rows === []) {
            return false;
        }

        $row = $rows[0];
        $type = $row['Table_type'] ?? $row['table_type'] ?? '';

        return strtoupper((string) $type) === 'VIEW';
    }

    private static function table_programs(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_programs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            code varchar(50) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name_ci (name)
        ) {$charset_collate};";
    }

    public static function ensure_programs_table(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_programs';
        if (self::table_exists($wpdb, $table)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'utf8mb4_unicode_ci';

        dbDelta(self::table_programs($wpdb->prefix, $charset));

        return self::table_exists($wpdb, $table);
    }

    /**
     * Seed pr_programs from distinct existing students.program values (case-insensitive de-dup, first-seen wins).
     * Rewrites student rows to the canonical name from the catalog.
     */
    public static function backfill_programs_from_students(object $wpdb): void
    {
        $programs_table = $wpdb->prefix . 'pr_programs';
        $students_table = $wpdb->prefix . 'pr_students';

        if (!self::table_exists($wpdb, $programs_table) || !self::table_exists($wpdb, $students_table)) {
            return;
        }

        $option_key = 'pr_programs_backfill_v1';
        if (function_exists('get_option') && get_option($option_key, false)) {
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT DISTINCT program FROM {$students_table} WHERE program != '' ORDER BY program ASC",
            'ARRAY_A'
        );

        if (!is_array($rows) || $rows === []) {
            if (function_exists('update_option')) {
                update_option($option_key, true, false);
            }

            return;
        }

        /** @var array<string, string> $seen lowercase => canonical name */
        $seen = [];

        foreach ($rows as $row) {
            $raw = (string) ($row['program'] ?? '');
            if ($raw === '') {
                continue;
            }

            $lower = strtolower($raw);
            if (!isset($seen[$lower])) {
                $wpdb->insert(
                    $programs_table,
                    ['name' => $raw, 'code' => ''],
                    ['%s', '%s']
                );
                $seen[$lower] = $raw;
            }

            $canonical = $seen[$lower];
            if ($canonical !== $raw) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$students_table} SET program = %s WHERE program = %s",
                        $canonical,
                        $raw
                    )
                );
            }
        }

        if (function_exists('update_option')) {
            update_option($option_key, true, false);
        }
    }

    private static function table_students(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_students (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reg_no varchar(64) NOT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            program varchar(64) NOT NULL DEFAULT '',
            batch varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reg_no (reg_no)
        ) {$charset_collate};";
    }

    private static function table_field_definitions(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_field_definitions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            field_key varchar(64) NOT NULL,
            label varchar(255) NOT NULL DEFAULT '',
            field_type varchar(32) NOT NULL DEFAULT 'text',
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY field_key (field_key)
        ) {$charset_collate};";
    }

    private static function table_student_meta(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_student_meta (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            student_id bigint(20) unsigned NOT NULL,
            field_key varchar(64) NOT NULL,
            meta_value longtext NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_field (student_id, field_key),
            KEY student_id (student_id)
        ) {$charset_collate};";
    }

    private static function table_sessions(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";
    }

    private static function table_session_students(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_session_students (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            panel_id bigint(20) unsigned DEFAULT NULL,
            project_title varchar(500) DEFAULT NULL,
            guide_emp_id varchar(64) NOT NULL DEFAULT '',
            guide_name varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            UNIQUE KEY session_student (session_id, student_id),
            KEY session_id (session_id),
            KEY student_id (student_id),
            KEY panel_id (panel_id)
        ) {$charset_collate};";
    }

    private static function table_panels(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_panels (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) {$charset_collate};";
    }

    private static function table_panel_reviewers(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_panel_reviewers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            panel_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned DEFAULT NULL,
            weight decimal(10,4) NOT NULL DEFAULT 1.0000,
            is_panel_head tinyint(1) NOT NULL DEFAULT 0,
            token varchar(64) DEFAULT NULL,
            password_hash varchar(255) DEFAULT NULL,
            password_encrypted text DEFAULT NULL,
            credentials_sent_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY panel_id (panel_id),
            KEY user_id (user_id)
        ) {$charset_collate};";
    }

    private static function table_review_reviewer_overrides(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_review_reviewer_overrides (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            weight decimal(10,4) NOT NULL DEFAULT 1.0000,
            PRIMARY KEY  (id),
            UNIQUE KEY review_user (review_id, user_id),
            KEY review_id (review_id)
        ) {$charset_collate};";
    }

    private static function table_reviews(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_reviews (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            label varchar(255) NOT NULL DEFAULT '',
            sort_order int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'draft',
            marking_active tinyint(1) NOT NULL DEFAULT 0,
            coordinator_marks_locked tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) {$charset_collate};";
    }

    private static function table_review_student_panels(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_review_student_panels (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            panel_id bigint(20) unsigned NOT NULL,
            attendance_status varchar(16) NOT NULL DEFAULT 'present',
            project_title varchar(500) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY review_student (review_id, student_id),
            KEY review_id (review_id),
            KEY student_id (student_id),
            KEY panel_id (panel_id)
        ) {$charset_collate};";
    }

    private static function table_review_student_attendance_by_reviewer(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_review_student_attendance_by_reviewer (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            reviewer_user_id bigint(20) unsigned NOT NULL,
            attendance_status varchar(16) NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY review_student_reviewer (review_id, student_id, reviewer_user_id),
            KEY review_id (review_id),
            KEY student_id (student_id)
        ) {$charset_collate};";
    }

    public static function ensure_review_student_attendance_by_reviewer_table(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_review_student_attendance_by_reviewer';
        if (self::table_exists($wpdb, $table)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'utf8mb4_unicode_ci';

        dbDelta(self::table_review_student_attendance_by_reviewer($wpdb->prefix, $charset));

        return self::table_exists($wpdb, $table);
    }

    private static function table_review_panel_reviewers(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_review_panel_reviewers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            panel_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            weight decimal(10,4) NOT NULL DEFAULT 1.0000,
            is_panel_head tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY review_panel_user (review_id, panel_id, user_id),
            KEY review_id (review_id),
            KEY panel_id (panel_id),
            KEY user_id (user_id)
        ) {$charset_collate};";
    }

    private static function table_review_panel_freezes(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_review_panel_freezes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            panel_id bigint(20) unsigned NOT NULL,
            frozen_by_user_id bigint(20) unsigned NOT NULL,
            frozen_at datetime NOT NULL,
            pdf_sha256 char(64) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY review_panel (review_id, panel_id),
            KEY review_id (review_id),
            KEY panel_id (panel_id)
        ) {$charset_collate};";
    }

    public static function ensure_marking_active_column(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_reviews';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        if (self::column_exists($wpdb, $table, 'marking_active')) {
            return true;
        }

        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN marking_active tinyint(1) NOT NULL DEFAULT 0 AFTER status"
        );

        return self::column_exists($wpdb, $table, 'marking_active');
    }

    public static function ensure_coordinator_marks_locked_column(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_reviews';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        if (self::column_exists($wpdb, $table, 'coordinator_marks_locked')) {
            return true;
        }

        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN coordinator_marks_locked tinyint(1) NOT NULL DEFAULT 0 AFTER marking_active"
        );

        return self::column_exists($wpdb, $table, 'coordinator_marks_locked');
    }

    public static function ensure_coordinator_override_columns(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_marks';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        $ok = true;
        if (!self::column_exists($wpdb, $table, 'coordinator_overridden')) {
            $wpdb->query(
                "ALTER TABLE {$table} ADD COLUMN coordinator_overridden tinyint(1) NOT NULL DEFAULT 0 AFTER flagged"
            );
            $ok = self::column_exists($wpdb, $table, 'coordinator_overridden') && $ok;
        }

        if (!self::column_exists($wpdb, $table, 'overridden_from_score')) {
            $wpdb->query(
                "ALTER TABLE {$table} ADD COLUMN overridden_from_score decimal(10,4) DEFAULT NULL AFTER coordinator_overridden"
            );
            $ok = self::column_exists($wpdb, $table, 'overridden_from_score') && $ok;
        }

        if ($ok) {
            self::refresh_rubric_scores_view($wpdb);
        }

        return $ok;
    }

    public static function ensure_panel_head_columns(object $wpdb): bool
    {
        $session_table = $wpdb->prefix . 'pr_panel_reviewers';
        $review_table = $wpdb->prefix . 'pr_review_panel_reviewers';
        $ok = true;

        if (self::table_exists($wpdb, $session_table) && !self::column_exists($wpdb, $session_table, 'is_panel_head')) {
            $wpdb->query(
                "ALTER TABLE {$session_table} ADD COLUMN is_panel_head tinyint(1) NOT NULL DEFAULT 0 AFTER weight"
            );
            $ok = self::column_exists($wpdb, $session_table, 'is_panel_head') && $ok;
        }

        if (self::table_exists($wpdb, $review_table) && !self::column_exists($wpdb, $review_table, 'is_panel_head')) {
            $wpdb->query(
                "ALTER TABLE {$review_table} ADD COLUMN is_panel_head tinyint(1) NOT NULL DEFAULT 0 AFTER weight"
            );
            $ok = self::column_exists($wpdb, $review_table, 'is_panel_head') && $ok;
        }

        return $ok;
    }

    public static function ensure_review_panel_freezes_table(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_review_panel_freezes';
        if (self::table_exists($wpdb, $table)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'utf8mb4_unicode_ci';

        dbDelta(self::table_review_panel_freezes($wpdb->prefix, $charset));

        return self::table_exists($wpdb, $table);
    }

    private static function table_panel_unfreeze_requests(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_panel_unfreeze_requests (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            review_id bigint(20) unsigned NOT NULL,
            panel_id bigint(20) unsigned NOT NULL,
            requested_by_user_id bigint(20) unsigned NOT NULL,
            reason text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            requested_at datetime NOT NULL,
            resolved_at datetime DEFAULT NULL,
            resolved_by_user_id bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY status (status),
            KEY panel_pending (session_id, review_id, panel_id, status)
        ) {$charset_collate};";
    }

    public static function ensure_panel_unfreeze_requests_table(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_panel_unfreeze_requests';
        if (self::table_exists($wpdb, $table)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'utf8mb4_unicode_ci';

        dbDelta(self::table_panel_unfreeze_requests($wpdb->prefix, $charset));

        return self::table_exists($wpdb, $table);
    }

    public static function ensure_review_assignment_tables(object $wpdb): bool
    {
        $student_table = $wpdb->prefix . 'pr_review_student_panels';
        $reviewer_table = $wpdb->prefix . 'pr_review_panel_reviewers';
        if (self::table_exists($wpdb, $student_table) && self::table_exists($wpdb, $reviewer_table)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'utf8mb4_unicode_ci';

        dbDelta(self::table_review_student_panels($wpdb->prefix, $charset));
        dbDelta(self::table_review_panel_reviewers($wpdb->prefix, $charset));

        return self::table_exists($wpdb, $student_table) && self::table_exists($wpdb, $reviewer_table);
    }

    public static function ensure_attendance_status_column(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_review_student_panels';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        if (self::column_exists($wpdb, $table, 'attendance_status')) {
            return true;
        }

        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN attendance_status varchar(16) NOT NULL DEFAULT 'present' AFTER panel_id"
        );

        return self::column_exists($wpdb, $table, 'attendance_status');
    }

    public static function ensure_project_title_columns(object $wpdb): bool
    {
        $enrolment = $wpdb->prefix . 'pr_session_students';
        $assignments = $wpdb->prefix . 'pr_review_student_panels';
        $ok = true;

        if (self::table_exists($wpdb, $enrolment) && !self::column_exists($wpdb, $enrolment, 'project_title')) {
            $wpdb->query(
                "ALTER TABLE {$enrolment} ADD COLUMN project_title varchar(500) DEFAULT NULL AFTER panel_id"
            );
            $ok = self::column_exists($wpdb, $enrolment, 'project_title') && $ok;
        }

        if (self::table_exists($wpdb, $assignments) && !self::column_exists($wpdb, $assignments, 'project_title')) {
            $wpdb->query(
                "ALTER TABLE {$assignments} ADD COLUMN project_title varchar(500) DEFAULT NULL AFTER attendance_status"
            );
            $ok = self::column_exists($wpdb, $assignments, 'project_title') && $ok;
        }

        return $ok;
    }

    public static function ensure_student_program_column(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_students';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        if (self::column_exists($wpdb, $table, 'program')) {
            return true;
        }

        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN program varchar(64) NOT NULL DEFAULT '' AFTER name"
        );

        return self::column_exists($wpdb, $table, 'program');
    }

    public static function ensure_enrolment_guide_columns(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_session_students';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        $ok = true;

        if (!self::column_exists($wpdb, $table, 'guide_emp_id')) {
            $wpdb->query(
                "ALTER TABLE {$table} ADD COLUMN guide_emp_id varchar(64) NOT NULL DEFAULT '' AFTER project_title"
            );
            $ok = self::column_exists($wpdb, $table, 'guide_emp_id') && $ok;
        }

        if (!self::column_exists($wpdb, $table, 'guide_name')) {
            $wpdb->query(
                "ALTER TABLE {$table} ADD COLUMN guide_name varchar(255) NOT NULL DEFAULT '' AFTER guide_emp_id"
            );
            $ok = self::column_exists($wpdb, $table, 'guide_name') && $ok;
        }

        return $ok;
    }

    public static function backfill_guide_name_to_enrolments(object $wpdb): void
    {
        $students = $wpdb->prefix . 'pr_students';
        $enrolment = $wpdb->prefix . 'pr_session_students';

        if (!self::table_exists($wpdb, $enrolment)) {
            return;
        }

        if (!self::column_exists($wpdb, $enrolment, 'guide_name')) {
            return;
        }

        if (!self::column_exists($wpdb, $students, 'guide_name')) {
            return;
        }

        $option_key = 'pr_guide_name_enrolment_backfill_v1';
        if (function_exists('get_option') && get_option($option_key, false)) {
            return;
        }

        $wpdb->query(
            "UPDATE {$enrolment} ss
            INNER JOIN {$students} s ON s.id = ss.student_id
            SET ss.guide_name = s.guide_name
            WHERE (ss.guide_name = '' OR ss.guide_name IS NULL)
              AND s.guide_name != ''"
        );

        if (function_exists('update_option')) {
            update_option($option_key, true, false);
        }
    }

    public static function drop_students_guide_name_column(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_students';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        if (!self::column_exists($wpdb, $table, 'guide_name')) {
            return true;
        }

        $wpdb->query("ALTER TABLE {$table} DROP COLUMN guide_name");

        return !self::column_exists($wpdb, $table, 'guide_name');
    }

    public static function backfill_review_assignments(object $wpdb): void
    {
        if (!self::table_exists($wpdb, $wpdb->prefix . 'pr_review_student_panels')) {
            return;
        }

        $option_key = 'pr_review_assignments_backfilled';
        if (function_exists('get_option') && get_option($option_key, false)) {
            return;
        }

        $reviews_table = $wpdb->prefix . 'pr_reviews';
        $reviews = $wpdb->get_results("SELECT id, session_id FROM {$reviews_table}", 'ARRAY_A');
        if (!is_array($reviews) || $reviews === []) {
            if (function_exists('update_option')) {
                update_option($option_key, true, false);
            }

            return;
        }

        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($wpdb);
        foreach ($reviews as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            $session_id = (int) ($review['session_id'] ?? 0);
            if ($review_id <= 0 || $session_id <= 0) {
                continue;
            }
            if ($assignments->list_student_panels($review_id) === []) {
                $assignments->seed_from_session_defaults($review_id, $session_id);
                continue;
            }

            if ($assignments->list_panel_reviewers($review_id) === []) {
                $assignments->sync_panel_reviewers_from_session($review_id, $session_id);
            }
        }

        if (function_exists('update_option')) {
            update_option($option_key, true, false);
        }
    }

    /**
     * One-time repair for sites where student panels were backfilled but reviewers were not.
     */
    public static function backfill_missing_review_panel_reviewers(object $wpdb): void
    {
        if (!self::table_exists($wpdb, $wpdb->prefix . 'pr_review_panel_reviewers')) {
            return;
        }

        $option_key = 'pr_review_panel_reviewers_backfill_v1';
        if (function_exists('get_option') && get_option($option_key, false)) {
            return;
        }

        $reviews_table = $wpdb->prefix . 'pr_reviews';
        $reviews = $wpdb->get_results("SELECT id, session_id FROM {$reviews_table}", 'ARRAY_A');
        if (!is_array($reviews) || $reviews === []) {
            if (function_exists('update_option')) {
                update_option($option_key, true, false);
            }

            return;
        }

        $assignments = new \ProjectReviews\Repositories\ReviewAssignmentRepository($wpdb);
        foreach ($reviews as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            $session_id = (int) ($review['session_id'] ?? 0);
            if ($review_id <= 0 || $session_id <= 0) {
                continue;
            }
            if ($assignments->list_panel_reviewers($review_id) !== []) {
                continue;
            }
            $assignments->sync_panel_reviewers_from_session($review_id, $session_id);
        }

        if (function_exists('update_option')) {
            update_option($option_key, true, false);
        }
    }

    private static function table_rubric_criteria(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_rubric_criteria (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            label varchar(255) NOT NULL DEFAULT '',
            max_marks decimal(10,4) NOT NULL DEFAULT 0.0000,
            weight decimal(10,4) NOT NULL DEFAULT 1.0000,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY review_id (review_id)
        ) {$charset_collate};";
    }

    private static function table_review_weights(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_review_weights (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            review_id bigint(20) unsigned NOT NULL,
            weight decimal(10,4) NOT NULL DEFAULT 1.0000,
            PRIMARY KEY  (id),
            UNIQUE KEY session_review (session_id, review_id),
            KEY session_id (session_id),
            KEY review_id (review_id)
        ) {$charset_collate};";
    }

    private static function table_reviewer_weights(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_reviewer_weights (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            reviewer_user_id bigint(20) unsigned NOT NULL,
            weight decimal(10,4) NOT NULL DEFAULT 1.0000,
            PRIMARY KEY  (id),
            UNIQUE KEY review_reviewer (review_id, reviewer_user_id),
            KEY review_id (review_id)
        ) {$charset_collate};";
    }

    private static function table_marks(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_marks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            review_id bigint(20) unsigned NOT NULL,
            student_id bigint(20) unsigned NOT NULL,
            reviewer_user_id bigint(20) unsigned NOT NULL,
            criterion_id bigint(20) unsigned NOT NULL,
            score decimal(10,4) DEFAULT NULL,
            flagged tinyint(1) NOT NULL DEFAULT 0,
            coordinator_overridden tinyint(1) NOT NULL DEFAULT 0,
            overridden_from_score decimal(10,4) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            PRIMARY KEY  (id),
            UNIQUE KEY mark_entry (session_id, review_id, student_id, reviewer_user_id, criterion_id),
            KEY session_id (session_id),
            KEY review_id (review_id),
            KEY student_id (student_id)
        ) {$charset_collate};";
    }

    private static function table_unfreeze_requests(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_unfreeze_requests (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            review_id bigint(20) unsigned NOT NULL,
            panel_id bigint(20) unsigned NOT NULL,
            reviewer_user_id bigint(20) unsigned NOT NULL,
            reason text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            requested_at datetime NOT NULL,
            resolved_at datetime DEFAULT NULL,
            resolved_by_user_id bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY status (status),
            KEY assignment_pending (session_id, review_id, panel_id, reviewer_user_id, status)
        ) {$charset_collate};";
    }

    public static function ensure_unfreeze_requests_table(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_unfreeze_requests';
        if (self::table_exists($wpdb, $table)) {
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'utf8mb4_unicode_ci';

        dbDelta(self::table_unfreeze_requests($wpdb->prefix, $charset));

        return self::table_exists($wpdb, $table);
    }

    public static function ensure_unfreeze_request_reason_column(object $wpdb): bool
    {
        $table = $wpdb->prefix . 'pr_unfreeze_requests';
        if (!self::table_exists($wpdb, $table)) {
            return false;
        }

        if (self::column_exists($wpdb, $table, 'reason')) {
            return true;
        }

        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN reason text NOT NULL AFTER reviewer_user_id"
        );

        return self::column_exists($wpdb, $table, 'reason');
    }

    private static function table_mark_audit(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_mark_audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_user_id bigint(20) unsigned NOT NULL,
            action varchar(64) NOT NULL DEFAULT '',
            entity varchar(64) NOT NULL DEFAULT '',
            entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
            old_value longtext,
            new_value longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY actor_user_id (actor_user_id),
            KEY entity_lookup (entity, entity_id)
        ) {$charset_collate};";
    }

    private static function table_session_reviewers(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_session_reviewers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            provisioned_for_session tinyint(1) NOT NULL DEFAULT 0,
            disabled_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_user (session_id, user_id),
            KEY session_id (session_id),
            KEY user_id (user_id)
        ) {$charset_collate};";
    }

    /**
     * All plugin tables for uninstall teardown (children before parents; no FK constraints).
     *
     * @return list<string> Prefixed table names.
     */
    public static function get_pr_table_names(string $prefix): array
    {
        $suffixes = [
            'pr_mark_audit',
            'pr_marks',
            'pr_unfreeze_requests',
            'pr_panel_unfreeze_requests',
            'pr_review_panel_freezes',
            'pr_review_student_attendance_by_reviewer',
            'pr_review_student_panels',
            'pr_review_panel_reviewers',
            'pr_review_reviewer_overrides',
            'pr_reviewer_weights',
            'pr_review_weights',
            'pr_rubric_criteria',
            'pr_reviews',
            'pr_session_reviewers',
            'pr_panel_reviewers',
            'pr_session_students',
            'pr_panels',
            'pr_sessions',
            'pr_student_meta',
            'pr_field_definitions',
            'pr_students',
            'pr_programs',
        ];

        return array_map(
            static fn (string $suffix): string => $prefix . $suffix,
            $suffixes
        );
    }

    /**
     * @return list<string> Option keys removed on opt-in uninstall (delete flag last).
     */
    public static function get_uninstall_option_names(): array
    {
        return [
            'pr_db_version',
            'pr_caps_version',
            'pr_rewrite_version',
            'pr_plugin_settings',
            'pr_review_assignments_backfilled',
            'pr_review_panel_reviewers_backfill_v1',
            'pr_programs_backfill_v1',
            'pr_theme_nav_bootstrap',
            'pr_theme_nav_bootstrap_status',
            'pr_theme_nav_manual_notice_dismissed',
            'pr_delete_data_on_uninstall',
        ];
    }

    /**
     * Drop the rubric scores view and all plugin tables. Safe to call when objects are already gone.
     */
    public static function drop_all(object $wpdb): void
    {
        $prefix = (string) ($wpdb->prefix ?? '');
        $view = $prefix . 'pr_rubric_scores';
        $wpdb->query("DROP VIEW IF EXISTS {$view}");

        foreach (self::get_pr_table_names($prefix) as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
}
