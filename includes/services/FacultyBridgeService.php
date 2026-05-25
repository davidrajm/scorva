<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

final class FacultyBridgeService
{
    /**
     * @return array{
     *     setting_enabled: bool,
     *     table_available: bool,
     *     filter_registered: bool,
     *     import_available: bool
     * }
     */
    public function directory_import_status(): array
    {
        global $wpdb;
        $table = isset($wpdb) ? $wpdb->prefix . 'faculty' : 'wp_faculty';
        $setting_enabled = PluginSettings::faculty_bridge_enabled();
        $table_available = $this->table_exists($table);
        $filter_registered = function_exists('has_filter') && has_filter('pr_faculty_list_active');

        return [
            'setting_enabled' => $setting_enabled,
            'table_available' => $table_available,
            'filter_registered' => $filter_registered,
            'import_available' => $setting_enabled && ($table_available || $filter_registered),
        ];
    }

    /**
     * @return list<array{empId: string, name: string, email: string, designation?: string, gender?: string, status?: string}>|\WP_Error
     */
    public function list_active(): array|\WP_Error
    {
        $filtered = apply_filters('pr_faculty_list_active', null);
        if (is_array($filtered)) {
            return $this->normalize_rows($filtered);
        }

        if (!PluginSettings::faculty_bridge_enabled()) {
            return new \WP_Error(
                'faculty_bridge_unavailable',
                __('Faculty directory bridge is disabled in plugin settings.', 'project-reviews'),
                ['status' => 400]
            );
        }

        global $wpdb;
        if (!isset($wpdb)) {
            return new \WP_Error(
                'faculty_bridge_unavailable',
                __('Database is not available.', 'project-reviews'),
                ['status' => 500]
            );
        }

        $table = $wpdb->prefix . 'faculty';
        if (! $this->table_exists($table)) {
            return new \WP_Error(
                'faculty_bridge_unavailable',
                __('Faculty directory table was not found.', 'project-reviews'),
                ['status' => 400]
            );
        }

        $rows = $wpdb->get_results(
            "SELECT empId, emp_name, official_email, designation, gender, status FROM {$table}",
            'ARRAY_A'
        );

        if (!is_array($rows)) {
            return [];
        }

        return $this->normalize_rows($rows);
    }

    private function table_exists(string $table): bool
    {
        global $wpdb;
        if (!isset($wpdb)) {
            return false;
        }

        if (method_exists($wpdb, 'get_var')) {
            $like = $wpdb->esc_like($table);
            $found = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $like)
            );

            if (!is_string($found) || $found === '') {
                return false;
            }

            return strtolower($found) === strtolower($table);
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{empId: string, name: string, email: string, designation?: string, gender?: string, status?: string}>
     */
    private function normalize_rows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $emp_id = trim((string) ($row['empId'] ?? $row['emp_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? $row['emp_name'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? $row['official_email'] ?? '')));
            $status = trim((string) ($row['status'] ?? 'Active'));

            $normalized[] = [
                'empId' => $emp_id,
                'name' => $name,
                'email' => $email,
                'designation' => trim((string) ($row['designation'] ?? '')),
                'gender' => trim((string) ($row['gender'] ?? '')),
                'status' => $status,
            ];
        }

        return $normalized;
    }
}
