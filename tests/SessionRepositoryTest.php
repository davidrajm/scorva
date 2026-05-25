<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class SessionRepositoryTest extends TestCase
{
    private FakeWpdb $wpdb;

    private SessionRepository $sessions;

    private StudentRepository $students;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $this->sessions = new SessionRepository($this->wpdb);
        $this->students = new StudentRepository($this->wpdb);
    }

    public function test_create_session_defaults_to_draft(): void
    {
        $id = $this->sessions->create(['title' => 'May Reviews']);
        $session = $this->sessions->find_by_id($id);

        $this->assertNotNull($session);
        $this->assertSame('May Reviews', $session['title']);
        $this->assertSame(SessionRepository::STATUS_DRAFT, $session['status']);
    }

    public function test_enrol_student_links_session_and_student(): void
    {
        $session_id = $this->sessions->create(['title' => 'Enrolment test']);
        $student_id = $this->students->insert([
            'reg_no' => 'R200',
            'name' => 'Alan Turing',
        ]);

        $enrolment_id = $this->sessions->enrol_student($session_id, $student_id);
        $this->assertGreaterThan(0, $enrolment_id);
        $this->assertSame(1, $this->sessions->count_enrolled($session_id));

        $enrolment = $this->sessions->find_enrolment($session_id, $student_id);
        $this->assertNotNull($enrolment);
        $this->assertSame($student_id, (int) $enrolment['student_id']);
    }

    public function test_valid_statuses_include_draft_active_closed(): void
    {
        $this->assertContains('draft', SessionRepository::VALID_STATUSES);
        $this->assertContains('active', SessionRepository::VALID_STATUSES);
        $this->assertContains('closed', SessionRepository::VALID_STATUSES);
    }

    public function test_count_enrolled_for_sessions_returns_aggregate_counts(): void
    {
        $session_a = $this->sessions->create(['title' => 'Session A']);
        $session_b = $this->sessions->create(['title' => 'Session B']);
        $student_one = $this->students->insert(['reg_no' => 'R201', 'name' => 'Ada']);
        $student_two = $this->students->insert(['reg_no' => 'R202', 'name' => 'Grace']);

        $this->sessions->enrol_student($session_a, $student_one);
        $this->sessions->enrol_student($session_a, $student_two);
        $this->sessions->enrol_student($session_b, $student_one);

        $counts = $this->sessions->count_enrolled_for_sessions([$session_a, $session_b, 999]);

        $this->assertSame(2, $counts[$session_a]);
        $this->assertSame(1, $counts[$session_b]);
        $this->assertSame(0, $counts[999]);
    }

    public function test_enrol_students_bulk_skips_unknown_and_duplicate_ids(): void
    {
        $session_id = $this->sessions->create(['title' => 'Bulk enrol']);
        $student_id = $this->students->insert(['reg_no' => 'R203', 'name' => 'Katherine']);

        $this->sessions->enrol_student($session_id, $student_id);

        $result = $this->sessions->enrol_students_bulk(
            $session_id,
            [$student_id, $student_id, 999, 0],
            $this->students
        );

        $this->assertSame([], $result['enrolled']);
        $this->assertCount(3, $result['skipped']);
        $this->assertSame('already_enrolled', $result['skipped'][0]['reason']);
        $this->assertSame('duplicate', $result['skipped'][1]['reason']);
        $this->assertSame('not_found', $result['skipped'][2]['reason']);
    }

    public function test_import_enrolment_auto_creates_student_when_name_provided(): void
    {
        $session_id = $this->sessions->create(['title' => 'Import auto-create']);
        $this->students->insert(['reg_no' => 'R204', 'name' => 'Present']);

        $result = $this->sessions->import_enrolment(
            $session_id,
            [
                ['reg_no' => 'R204', 'panel' => 'A'],
                [
                    'reg_no' => 'R205',
                    'name' => 'New Student',
                    'program' => 'MDT',
                    'batch' => '2025',
                    'panel' => 'A',
                ],
            ],
            $this->students
        );

        $this->assertIsArray($result);
        $this->assertSame(2, $result['enrolled']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(2, $this->sessions->count_enrolled($session_id));

        $created = $this->students->find_by_reg_no('R205');
        $this->assertNotNull($created);
        $this->assertSame('New Student', $created['name']);
        $this->assertSame('MDT', $created['program']);
        $this->assertSame('2025', $created['batch']);
    }

    public function test_import_enrolment_fails_new_student_without_name(): void
    {
        $session_id = $this->sessions->create(['title' => 'Import name required']);

        $result = $this->sessions->import_enrolment(
            $session_id,
            [['reg_no' => 'R299', 'panel' => 'A']],
            $this->students
        );

        $this->assertIsArray($result);
        $this->assertSame(0, $result['enrolled']);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('Name is required', $result['errors'][0]['message']);
        $this->assertSame(0, $this->sessions->count_enrolled($session_id));
    }

    public function test_import_enrolment_enrols_when_all_reg_nos_in_registry(): void
    {
        $session_id = $this->sessions->create(['title' => 'Import ok']);
        $this->students->insert(['reg_no' => 'R206', 'name' => 'One']);
        $this->students->insert(['reg_no' => 'R207', 'name' => 'Two']);

        $result = $this->sessions->import_enrolment(
            $session_id,
            [
                ['reg_no' => 'R206', 'panel' => 'Panel 1'],
                ['reg_no' => 'R207', 'panel' => 'Panel 1'],
            ],
            $this->students
        );

        $this->assertIsArray($result);
        $this->assertSame(2, $result['enrolled']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(2, $this->sessions->count_enrolled($session_id));
    }

    public function test_enrol_student_persists_guide_fields(): void
    {
        $session_id = $this->sessions->create(['title' => 'Guide fields']);
        $student_id = $this->students->insert(['reg_no' => 'R208', 'name' => 'Guide Test']);

        $this->sessions->enrol_student(
            $session_id,
            $student_id,
            null,
            null,
            'EMP99',
            'Dr. Mentor'
        );

        $enrolment = $this->sessions->find_enrolment($session_id, $student_id);
        $this->assertNotNull($enrolment);
        $this->assertSame('EMP99', $enrolment['guide_emp_id']);
        $this->assertSame('Dr. Mentor', $enrolment['guide_name']);
    }

    public function test_import_enrolment_persists_guide_fields(): void
    {
        $session_id = $this->sessions->create(['title' => 'Guide import']);
        $this->students->insert(['reg_no' => 'R209', 'name' => 'Imported']);

        $result = $this->sessions->import_enrolment(
            $session_id,
            [
                [
                    'reg_no' => 'R209',
                    'panel' => 'Panel A',
                    'guide_emp_id' => 'EMP1',
                    'guide_name' => 'Dr. Smith',
                ],
            ],
            $this->students
        );

        $this->assertIsArray($result);
        $student = $this->students->find_by_reg_no('R209');
        $enrolment = $this->sessions->find_enrolment($session_id, (int) $student['id']);
        $this->assertSame('EMP1', $enrolment['guide_emp_id']);
        $this->assertSame('Dr. Smith', $enrolment['guide_name']);
    }
}
