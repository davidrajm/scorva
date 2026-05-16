<?php

declare(strict_types=1);

namespace ProjectReviews;

final class Install
{
    public static function get_schema_sql(string $prefix = '', string $charset_collate = ''): string
    {
        $tables = [
            self::table_students($prefix, $charset_collate),
            self::table_field_definitions($prefix, $charset_collate),
            self::table_student_meta($prefix, $charset_collate),
            self::table_sessions($prefix, $charset_collate),
            self::table_session_students($prefix, $charset_collate),
            self::table_panels($prefix, $charset_collate),
            self::table_panel_reviewers($prefix, $charset_collate),
            self::table_review_reviewer_overrides($prefix, $charset_collate),
            self::table_reviews($prefix, $charset_collate),
            self::table_rubric_criteria($prefix, $charset_collate),
            self::table_review_weights($prefix, $charset_collate),
            self::table_reviewer_weights($prefix, $charset_collate),
            self::table_marks($prefix, $charset_collate),
            self::table_mark_audit($prefix, $charset_collate),
            self::table_session_reviewers($prefix, $charset_collate),
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

        update_option('pr_db_version', PR_PLUGIN_VERSION);
    }

    private static function table_students(string $prefix, string $charset_collate): string
    {
        return "CREATE TABLE {$prefix}pr_students (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reg_no varchar(64) NOT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            batch varchar(64) NOT NULL DEFAULT '',
            guide_name varchar(255) NOT NULL DEFAULT '',
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
            email varchar(255) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned DEFAULT NULL,
            weight decimal(10,4) NOT NULL DEFAULT 1.0000,
            PRIMARY KEY  (id),
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
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) {$charset_collate};";
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
            status varchar(20) NOT NULL DEFAULT 'draft',
            PRIMARY KEY  (id),
            UNIQUE KEY mark_entry (session_id, review_id, student_id, reviewer_user_id, criterion_id),
            KEY session_id (session_id),
            KEY review_id (review_id),
            KEY student_id (student_id)
        ) {$charset_collate};";
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
}
