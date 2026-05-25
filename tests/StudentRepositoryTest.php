<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\StudentRepository;

final class StudentRepositoryTest extends TestCase
{
    private FakeWpdb $wpdb;

    private StudentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $this->repository = new StudentRepository($this->wpdb);
    }

    public function test_insert_and_fetch_student_fixture(): void
    {
        $id = $this->repository->insert([
            'reg_no' => 'R001',
            'name' => 'Ada Lovelace',
            'program' => 'MSC-DS',
            'batch' => '2026',
        ]);

        $this->assertGreaterThan(0, $id);

        $student = $this->repository->find_by_id($id);
        $this->assertNotNull($student);
        $this->assertSame('R001', $student['reg_no']);
        $this->assertSame('Ada Lovelace', $student['name']);
        $this->assertSame('MSC-DS', $student['program']);
        $this->assertSame('2026', $student['batch']);
    }

    public function test_search_matches_program(): void
    {
        $this->repository->insert([
            'reg_no' => 'R002',
            'name' => 'Grace Hopper',
            'program' => 'B.Tech CSE',
            'batch' => '2026',
        ]);

        $results = $this->repository->list_all('CSE');
        $this->assertCount(1, $results);
        $this->assertSame('R002', $results[0]['reg_no']);
    }
}
