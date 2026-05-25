<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Install;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class BackupService
{
    private const README_FILENAME = 'README.txt';

    private const MANIFEST_FILENAME = 'manifest.json';

    private const SQL_PATH = 'database/pr-plugin-data.sql';

    private const OPTIONS_PATH = 'options/pr-plugin-options.json';

    /** @var list<string> */
    private const GLOBAL_TABLE_SUFFIXES = [
        'pr_students',
        'pr_field_definitions',
        'pr_student_meta',
    ];

    private object $wpdb;

    private SessionRepository $sessions;

    private ReviewRepository $reviews;

    private ReportsViewService $reports;

    private ExportService $export;

    /** @var array<int, list<int>> */
    private array $review_ids_by_session = [];

    /** @var array<int, list<int>> */
    private array $panel_ids_by_session = [];

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('BackupService requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->sessions = new SessionRepository($wpdb);
        $this->reviews = new ReviewRepository($wpdb);
        $assignments = new ReviewAssignmentRepository($wpdb);
        $students = new StudentRepository($wpdb);
        $marks = new MarkRepository($wpdb);
        $panels = new PanelRepository($wpdb);
        $scores = new ScoreService(
            $this->sessions,
            $this->reviews,
            $panels,
            $assignments,
            $marks
        );
        $this->reports = new ReportsViewService(
            $this->sessions,
            $this->reviews,
            $assignments,
            $students,
            $marks,
            $scores,
            $panels
        );
        $this->export = new ExportService();
    }

    /**
     * @return array{path: string, filename: string}|\WP_Error
     */
    public function build_full_backup_zip(): array|\WP_Error
    {
        $sessions = $this->sessions->list_all();
        $session_ids = array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $sessions
        );
        $session_ids = array_values(array_filter($session_ids, static fn (int $id): bool => $id > 0));

        $timestamp = gmdate('Y-m-d-His');
        $filename = 'project-reviews-backup-full-' . $timestamp . '.zip';

        return $this->build_zip($session_ids, 'full', null, $filename);
    }

    /**
     * @return array{path: string, filename: string}|\WP_Error
     */
    public function build_project_backup_zip(int $session_id): array|\WP_Error
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return new \WP_Error(
                'pr_session_not_found',
                __('Project not found.', 'project-reviews'),
                ['status' => 404]
            );
        }

        $slug = $this->session_slug($session_id, $session);
        $timestamp = gmdate('Y-m-d-His');
        $filename = 'project-reviews-backup-' . $slug . '-' . $timestamp . '.zip';

        return $this->build_zip([$session_id], 'project', $session_id, $filename);
    }

    /**
     * @param list<int> $session_ids
     * @return array{path: string, filename: string}|\WP_Error
     */
    private function build_zip(
        array $session_ids,
        string $scope,
        ?int $scoped_session_id,
        string $filename
    ): array|\WP_Error {
        if (!class_exists(\ZipArchive::class)) {
            return new \WP_Error(
                'pr_zip_unavailable',
                __('PHP Zip extension (ZipArchive) is required for backups. Enable ext-zip on the server.', 'project-reviews'),
                ['status' => 500]
            );
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $warnings = [];
        $table_row_counts = [];
        $project_slugs = [];

        foreach ($session_ids as $session_id) {
            $session = $this->sessions->find_by_id($session_id);
            if ($session === null) {
                continue;
            }
            $project_slugs[] = $this->session_slug($session_id, $session);
        }

        $temp_dir = $this->create_temp_dir();
        if ($temp_dir instanceof \WP_Error) {
            return $temp_dir;
        }

        $zip_path = $temp_dir . DIRECTORY_SEPARATOR . $filename;
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->remove_dir($temp_dir);

            return new \WP_Error(
                'pr_backup_failed',
                __('Could not create backup archive.', 'project-reviews'),
                ['status' => 500]
            );
        }

        $sql = $this->generate_sql($scoped_session_id, $table_row_counts);
        $zip->addFromString(self::SQL_PATH, $sql);
        $zip->addFromString(self::OPTIONS_PATH, $this->generate_options_json());
        $zip->addFromString(self::README_FILENAME, $this->readme_text());

        foreach ($session_ids as $session_id) {
            $session = $this->sessions->find_by_id($session_id);
            if ($session === null) {
                continue;
            }

            $slug = $this->session_slug($session_id, $session);
            $project_prefix = 'projects/' . $slug . '/';

            $consolidated = $this->build_consolidated_xlsx($session_id);
            if ($consolidated instanceof \WP_Error) {
                $warnings[] = [
                    'type' => 'consolidated_student_scores',
                    'session_id' => $session_id,
                    'message' => $consolidated->get_error_message(),
                ];
            } else {
                $zip->addFromString(
                    $project_prefix . 'consolidated-student-scores.xlsx',
                    $consolidated
                );
            }

            foreach ($this->confirmed_reviews($session_id) as $review) {
                $review_id = (int) ($review['id'] ?? 0);
                if ($review_id <= 0) {
                    continue;
                }

                $review_slug = $this->review_slug($review_id, $review);
                $review_prefix = $project_prefix . 'reviews/' . $review_slug . '/';

                $this->add_review_xlsx(
                    $zip,
                    $review_prefix . 'panel-roster.xlsx',
                    $this->build_panel_roster_xlsx($session_id, $review_id),
                    'panel_roster',
                    $session_id,
                    $review_id,
                    $warnings
                );
                $this->add_review_xlsx(
                    $zip,
                    $review_prefix . 'rubric-marks-matrix.xlsx',
                    $this->build_marks_matrix_xlsx($session_id, $review_id),
                    'rubric_marks_matrix',
                    $session_id,
                    $review_id,
                    $warnings
                );
                $this->add_review_xlsx(
                    $zip,
                    $review_prefix . 'overall-scores-matrix.xlsx',
                    $this->build_scores_matrix_xlsx($session_id, $review_id),
                    'overall_scores_matrix',
                    $session_id,
                    $review_id,
                    $warnings
                );
            }
        }

        $manifest = [
            'generated_at' => gmdate('c'),
            'plugin_version' => defined('PR_PLUGIN_VERSION') ? PR_PLUGIN_VERSION : '0.0.0',
            'db_version' => (string) get_option('pr_db_version', ''),
            'backup_scope' => $scope,
            'sql_scope' => $scope === 'project' ? 'project' : 'full',
            'session_id' => $scoped_session_id,
            'project_ids' => $session_ids,
            'project_slugs' => $project_slugs,
            'report_layout' => ReportsViewService::DEFAULT_MARKS_MATRIX_LAYOUT,
            'php_version' => PHP_VERSION,
            'table_row_counts' => $table_row_counts,
            'warnings' => $warnings,
        ];

        $zip->addFromString(
            self::MANIFEST_FILENAME,
            \wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        );
        $zip->close();

        return [
            'path' => $zip_path,
            'filename' => $filename,
            'temp_dir' => $temp_dir,
        ];
    }

    public function cleanup_temp(string $temp_dir): void
    {
        $this->remove_dir($temp_dir);
    }

    /**
     * @param array<string, int> $table_row_counts
     */
    public function generate_sql(?int $session_id, array &$table_row_counts = []): string
    {
        $prefix = (string) $this->wpdb->prefix;
        $tables = Install::get_pr_table_names($prefix);
        $view = $prefix . 'pr_rubric_scores';
        $generated = gmdate('Y-m-d H:i:s');

        $lines = [
            '-- Project Reviews plugin data export',
            '-- Generator: BackupService',
            '-- Generated: ' . $generated,
            '-- Tables: ' . count($tables),
            '-- Scope: ' . ($session_id === null ? 'full site' : 'project ' . $session_id),
            '',
            'SET NAMES utf8mb4;',
            '',
            'DROP VIEW IF EXISTS `' . $view . '`;',
            '',
        ];

        foreach ($tables as $table) {
            $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
        }

        $lines[] = '';

        foreach ($tables as $table) {
            $create = $this->show_create_table($table);
            if ($create !== '') {
                $lines[] = $create . ';';
                $lines[] = '';
            }
        }

        foreach ($tables as $table) {
            $suffix = $this->table_suffix($table, $prefix);
            $rows = $this->fetch_rows_for_table($suffix, $table, $session_id);
            $table_row_counts[$suffix] = count($rows);
            if ($rows === []) {
                continue;
            }

            $lines = array_merge($lines, $this->build_insert_statements($table, $rows));
            $lines[] = '';
        }

        $lines[] = Install::rubric_scores_view_ddl($prefix) . ';';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function generate_options_json(): string
    {
        $options = [];
        foreach (Install::get_uninstall_option_names() as $key) {
            $options[$key] = get_option($key);
        }

        return \wp_json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function readme_text(): string
    {
        $product = PluginSettings::app_display_name();

        return implode(
            "\n",
            [
                $product . ' — data backup (export only)',
                '============================================',
                '',
                'Contents:',
                '- database/pr-plugin-data.sql — plugin tables and pr_rubric_scores view (not WordPress core tables or users).',
                '- options/pr-plugin-options.json — plugin options from wp_options.',
                '- projects/{slug}/ — Excel committee deliverables per project.',
                '- projects/{slug}/reviews/{review-slug}/ — per-review Excel exports.',
                '',
                'PDF and CSV exports are omitted by design; this bundle matches the coordinator Downloads Excel set.',
                '',
                'Restore/import from this ZIP is not supported in this release. Keep copies off-site for disaster recovery,',
                'migration planning, or before uninstall when using the destructive uninstall option in plugin settings.',
                '',
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function confirmed_reviews(int $session_id): array
    {
        $confirmed = [];
        foreach ($this->reviews->list_for_session($session_id) as $review) {
            if ((string) ($review['status'] ?? '') === ReviewRepository::STATUS_CONFIRMED) {
                $confirmed[] = $review;
            }
        }

        return $confirmed;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function session_slug(int $session_id, array $session): string
    {
        $slug = sanitize_title((string) ($session['title'] ?? ''));
        if ($slug === '') {
            $slug = 'session-' . $session_id;
        }

        return sanitize_file_name($slug);
    }

    /**
     * @param array<string, mixed> $review
     */
    private function review_slug(int $review_id, array $review): string
    {
        $label = trim((string) ($review['label'] ?? ''));
        $slug = sanitize_title($label !== '' ? $label : 'review-' . $review_id);

        return sanitize_file_name($slug !== '' ? $slug : 'review-' . $review_id);
    }

    private function build_consolidated_xlsx(int $session_id): string|\WP_Error
    {
        $built = $this->reports->consolidated_student_export($session_id);
        if ($built instanceof \WP_Error) {
            return $built;
        }

        return $this->export->to_xlsx(
            $built['rows'],
            $built['merge_plan'],
            $built['styles']
        );
    }

    private function build_panel_roster_xlsx(int $session_id, int $review_id): string|\WP_Error
    {
        $built = $this->reports->panel_roster_export($session_id, $review_id);
        if ($built instanceof \WP_Error) {
            return $built;
        }

        return $this->export->to_xlsx(
            $built['rows'],
            $built['merge_plan'] ?? [],
            $built['styles'] ?? []
        );
    }

    private function build_marks_matrix_xlsx(int $session_id, int $review_id): string|\WP_Error
    {
        $built = $this->reports->marks_grid_export(
            $session_id,
            $review_id,
            ReportsViewService::DEFAULT_MARKS_MATRIX_LAYOUT,
            'reg_no',
            'asc'
        );
        if ($built instanceof \WP_Error) {
            return $built;
        }

        return $this->export->to_xlsx(
            $built['rows'],
            $built['merge_plan'],
            $built['styles']
        );
    }

    private function build_scores_matrix_xlsx(int $session_id, int $review_id): string|\WP_Error
    {
        $built = $this->reports->scores_matrix_export(
            $session_id,
            $review_id,
            'reg_no',
            'asc'
        );
        if ($built instanceof \WP_Error) {
            return $built;
        }

        return $this->export->to_xlsx(
            $built['rows'],
            $built['merge_plan'],
            $built['styles']
        );
    }

    /**
     * @param list<array<string, mixed>> $warnings
     */
    private function add_review_xlsx(
        \ZipArchive $zip,
        string $path,
        string|\WP_Error $body,
        string $type,
        int $session_id,
        int $review_id,
        array &$warnings
    ): void {
        if ($body instanceof \WP_Error) {
            $warnings[] = [
                'type' => $type,
                'session_id' => $session_id,
                'review_id' => $review_id,
                'message' => $body->get_error_message(),
            ];

            return;
        }

        $zip->addFromString($path, $body);
    }

    private function show_create_table(string $table): string
    {
        $row = $this->wpdb->get_row('SHOW CREATE TABLE `' . $table . '`', \ARRAY_A);
        if (is_array($row)) {
            $ddl = (string) ($row['Create Table'] ?? $row['Create View'] ?? '');
            if ($ddl !== '') {
                return $ddl;
            }
        }

        if (method_exists($this->wpdb, 'get_all_rows')) {
            $columns = array_keys($this->wpdb->get_all_rows($table)[0] ?? []);
            if ($columns !== []) {
                $defs = array_map(
                    static fn (string $col): string => '`' . $col . '` longtext',
                    $columns
                );

                return 'CREATE TABLE `' . $table . '` (' . implode(', ', $defs) . ')';
            }
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetch_rows_for_table(string $suffix, string $table, ?int $session_id): array
    {
        $rows = $this->wpdb->get_results('SELECT * FROM `' . $table . '`', \ARRAY_A);
        if (!is_array($rows)) {
            $rows = [];
        }

        if ($rows === [] && method_exists($this->wpdb, 'get_all_rows')) {
            $rows = $this->wpdb->get_all_rows($table);
        }

        if ($session_id === null) {
            return $rows;
        }

        if (in_array($suffix, self::GLOBAL_TABLE_SUFFIXES, true)) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->row_belongs_to_session($suffix, $row, $session_id)
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function row_belongs_to_session(string $suffix, array $row, int $session_id): bool
    {
        if ($suffix === 'pr_sessions') {
            return (int) ($row['id'] ?? 0) === $session_id;
        }

        if (isset($row['session_id'])) {
            return (int) $row['session_id'] === $session_id;
        }

        if (isset($row['review_id'])) {
            return in_array((int) $row['review_id'], $this->review_ids_for_session($session_id), true);
        }

        if (isset($row['panel_id'])) {
            return in_array((int) $row['panel_id'], $this->panel_ids_for_session($session_id), true);
        }

        if ($suffix === 'pr_mark_audit') {
            return $this->audit_row_belongs_to_session($row, $session_id);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function audit_row_belongs_to_session(array $row, int $session_id): bool
    {
        $entity = (string) ($row['entity'] ?? '');
        $entity_id = (int) ($row['entity_id'] ?? 0);

        if ($entity === 'session' && $entity_id === $session_id) {
            return true;
        }

        if ($entity === 'review' && in_array($entity_id, $this->review_ids_for_session($session_id), true)) {
            return true;
        }

        if ($entity === 'mark') {
            $marks_table = $this->wpdb->prefix . 'pr_marks';
            $mark = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT session_id FROM {$marks_table} WHERE id = %d",
                    $entity_id
                ),
                \ARRAY_A
            );

            return is_array($mark) && (int) ($mark['session_id'] ?? 0) === $session_id;
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function review_ids_for_session(int $session_id): array
    {
        if (!isset($this->review_ids_by_session[$session_id])) {
            $ids = [];
            foreach ($this->reviews->list_for_session($session_id) as $review) {
                $id = (int) ($review['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            $this->review_ids_by_session[$session_id] = $ids;
        }

        return $this->review_ids_by_session[$session_id];
    }

    /**
     * @return list<int>
     */
    private function panel_ids_for_session(int $session_id): array
    {
        if (!isset($this->panel_ids_by_session[$session_id])) {
            $panels_table = $this->wpdb->prefix . 'pr_panels';
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT id FROM {$panels_table} WHERE session_id = %d",
                    $session_id
                ),
                \ARRAY_A
            );
            $ids = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
            $this->panel_ids_by_session[$session_id] = $ids;
        }

        return $this->panel_ids_by_session[$session_id];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function build_insert_statements(string $table, array $rows): array
    {
        $lines = [];
        $batch_size = 50;
        $columns = array_keys($rows[0]);

        for ($offset = 0; $offset < count($rows); $offset += $batch_size) {
            $chunk = array_slice($rows, $offset, $batch_size);
            $value_groups = [];

            foreach ($chunk as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->sql_value($row[$column] ?? null);
                }
                $value_groups[] = '(' . implode(', ', $values) . ')';
            }

            $column_list = implode(', ', array_map(
                static fn (string $col): string => '`' . $col . '`',
                $columns
            ));

            $lines[] = 'INSERT INTO `' . $table . '` (' . $column_list . ') VALUES '
                . implode(",\n", $value_groups) . ';';
        }

        return $lines;
    }

    private function sql_value(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;
        if (method_exists($this->wpdb, '_real_escape')) {
            $string = $this->wpdb->_real_escape($string);
        } else {
            $string = addslashes($string);
        }

        return "'" . $string . "'";
    }

    private function table_suffix(string $table, string $prefix): string
    {
        if (str_starts_with($table, $prefix)) {
            return substr($table, strlen($prefix));
        }

        return $table;
    }

    /**
     * @return string|\WP_Error
     */
    private function create_temp_dir()
    {
        $base = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '/tmp';
        $dir = $base . DIRECTORY_SEPARATOR . 'pr-backup-' . wp_generate_password(12, false, false);
        if (!wp_mkdir_p($dir)) {
            return new \WP_Error(
                'pr_backup_failed',
                __('Could not create a temporary directory for the backup.', 'project-reviews'),
                ['status' => 500]
            );
        }

        return $dir;
    }

    private function remove_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->remove_dir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
