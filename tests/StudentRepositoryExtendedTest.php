<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\FieldDefinitionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class StudentRepositoryExtendedTest extends TestCase
{
    private FakeWpdb $wpdb;

    private StudentRepository $students;

    private FieldDefinitionRepository $fields;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $this->students = new StudentRepository($this->wpdb);
        $this->fields = new FieldDefinitionRepository($this->wpdb);
    }

    public function test_search_and_meta_round_trip(): void
    {
        $this->fields->insert([
            'field_key' => 'department',
            'label' => 'Department',
        ]);

        $id = $this->students->insert([
            'reg_no' => 'R501',
            'name' => 'Alan Turing',
            'meta' => ['department' => 'CS'],
        ]);

        $results = $this->students->list_all('Turing');
        $this->assertCount(1, $results);
        $this->assertSame('CS', $results[0]['meta']['department']);

        $this->students->update($id, [
            'name' => 'Alan M. Turing',
            'meta' => ['department' => 'Mathematics'],
        ]);

        $student = $this->students->find_by_id($id);
        $this->assertSame('Alan M. Turing', $student['name']);
        $this->assertSame('Mathematics', $student['meta']['department']);
    }

    public function test_import_skip_and_update_policies(): void
    {
        $this->students->insert([
            'reg_no' => 'R600',
            'name' => 'Existing',
        ]);

        $skipped = $this->students->import_rows(
            [['reg_no' => 'R600', 'name' => 'Ignored']],
            'skip'
        );
        $this->assertSame(1, $skipped['skipped']);

        $updated = $this->students->import_rows(
            [['reg_no' => 'R600', 'name' => 'Renamed']],
            'update'
        );
        $this->assertSame(1, $updated['updated']);
        $this->assertSame('Renamed', $this->students->find_by_reg_no('R600')['name']);
    }
}
