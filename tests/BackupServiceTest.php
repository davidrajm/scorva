<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\BackupService;
use ProjectReviews\Tests\Support\ScenarioBuilder;
use ZipArchive;

final class BackupServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/tests/RestAuthTest.php';
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['pr_test_options']['pr_db_version'] = '1.0.0';

        require_once dirname(__DIR__) . '/includes/Install.php';
        require_once dirname(__DIR__) . '/includes/repositories/SessionRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/ReviewRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/StudentRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/PanelRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/MarkRepository.php';
        require_once dirname(__DIR__) . '/includes/repositories/ReviewAssignmentRepository.php';
        require_once dirname(__DIR__) . '/includes/services/ExportService.php';
        require_once dirname(__DIR__) . '/includes/services/ScoreService.php';
        require_once dirname(__DIR__) . '/includes/services/MarkService.php';
        require_once dirname(__DIR__) . '/includes/services/ReportsViewService.php';
        require_once dirname(__DIR__) . '/includes/services/BackupService.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_project_backup_zip_contains_sql_manifest_and_xlsx(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->with_marks_submitted()
            ->build();

        $service = new BackupService($this->wpdb);
        $built = $service->build_project_backup_zip($ctx['session_id']);
        $this->assertIsArray($built);

        $extracted = $this->extract_zip((string) $built['path']);
        try {
            $this->assertFileExists($extracted . '/manifest.json');
            $this->assertFileExists($extracted . '/database/pr-plugin-data.sql');
            $this->assertFileExists($extracted . '/options/pr-plugin-options.json');
            $this->assertFileExists($extracted . '/README.txt');

            $manifest = json_decode(
                (string) file_get_contents($extracted . '/manifest.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $this->assertSame('project', $manifest['backup_scope']);
            $this->assertSame('rubric', $manifest['report_layout']);

            $sql = (string) file_get_contents($extracted . '/database/pr-plugin-data.sql');
            $this->assertStringContainsString('INSERT INTO', $sql);
            $this->assertStringContainsString('pr_sessions', $sql);

            $xlsx_files = $this->find_files($extracted, '*.xlsx');
            $this->assertNotEmpty($xlsx_files);
            $this->assertTrue(
                $this->path_exists_matching($extracted, 'projects/*/consolidated-student-scores.xlsx')
            );
        } finally {
            $service->cleanup_temp((string) $built['temp_dir']);
            $this->remove_dir($extracted);
        }
    }

    public function test_project_backup_sql_omits_other_session_panels(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        $ctx = ScenarioBuilder::fresh($this->wpdb)
            ->build_configured_project()
            ->build();

        $sessions = new SessionRepository($this->wpdb);
        $other_session_id = $sessions->create([
            'title' => 'Other Committee Project',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $panels = new PanelRepository($this->wpdb);
        $other_panel_id = $panels->create($other_session_id, 'Other Panel Unique');

        $service = new BackupService($this->wpdb);
        $built = $service->build_project_backup_zip($ctx['session_id']);
        $this->assertIsArray($built);

        $extracted = $this->extract_zip((string) $built['path']);
        try {
            $sql = (string) file_get_contents($extracted . '/database/pr-plugin-data.sql');
            $this->assertStringNotContainsString('Other Panel Unique', $sql);
            $this->assertStringNotContainsString(
                "'Other Committee Project'",
                $sql
            );
            unset($other_panel_id);
        } finally {
            $service->cleanup_temp((string) $built['temp_dir']);
            $this->remove_dir($extracted);
        }
    }

    private function extract_zip(string $zip_path): string
    {
        $dir = sys_get_temp_dir() . '/pr-backup-test-' . uniqid('', true);
        if (!wp_mkdir_p($dir)) {
            throw new \RuntimeException('Could not create extract directory.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zip_path);
        if ($opened !== true) {
            throw new \RuntimeException('Could not open zip: ' . $zip_path);
        }
        $zip->extractTo($dir);
        $zip->close();

        return $dir;
    }

    /**
     * @return list<string>
     */
    private function find_files(string $dir, string $pattern): array
    {
        $matches = glob($dir . '/' . $pattern, GLOB_BRACE) ?: [];
        $nested = glob($dir . '/*/' . $pattern) ?: [];
        $deep = glob($dir . '/*/*/' . $pattern) ?: [];
        $deeper = glob($dir . '/*/*/*/' . $pattern) ?: [];

        return array_values(array_filter(array_merge($matches, $nested, $deep, $deeper)));
    }

    private function path_exists_matching(string $root, string $pattern): bool
    {
        $parts = explode('/', $pattern);
        return $this->match_glob($root, $parts);
    }

    /**
     * @param list<string> $parts
     */
    private function match_glob(string $dir, array $parts): bool
    {
        if ($parts === []) {
            return is_file($dir);
        }

        $part = array_shift($parts);
        if ($part === null) {
            return false;
        }

        if ($parts === []) {
            foreach (glob($dir . '/' . $part) ?: [] as $path) {
                if (is_file($path)) {
                    return true;
                }
            }

            return false;
        }

        foreach (glob($dir . '/' . $part, GLOB_ONLYDIR) ?: [] as $path) {
            if ($this->match_glob($path, $parts)) {
                return true;
            }
        }

        return false;
    }

    private function remove_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->remove_dir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
