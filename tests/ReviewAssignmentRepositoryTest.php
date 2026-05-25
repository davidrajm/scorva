<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;

final class ReviewAssignmentRepositoryTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_seed_and_copy_review_assignments(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Assign test']);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');
        $panels->add_reviewer($panel_a, [
            'name' => 'R1',
            'email' => 'r1@x.com',
            'user_id' => 101,
        ]);

        $student_id = $students->insert(['reg_no' => 'A1', 'name' => 'Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_a);

        $review1 = $reviews->create($session_id, ['label' => 'Review 1']);
        $this->assertNotEmpty($assignments->list_student_panels($review1));
        $this->assertSame($panel_a, (int) $assignments->get_student_panel($review1, $student_id)['panel_id']);

        $review2 = $reviews->create($session_id, ['label' => 'Review 2']);
        $this->assertSame($panel_a, (int) $assignments->get_student_panel($review2, $student_id)['panel_id']);

        $assignments->set_student_panel($review2, $student_id, $panel_b);
        $this->assertSame($panel_b, (int) $assignments->get_student_panel($review2, $student_id)['panel_id']);
        $this->assertSame($panel_a, (int) $assignments->get_student_panel($review1, $student_id)['panel_id']);
    }

    public function test_seed_and_copy_propagate_project_title(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Title propagation']);
        $panel_id = $panels->create($session_id, 'Panel A');
        $student_id = $students->insert(['reg_no' => 'T1', 'name' => 'Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id, 'Thesis Alpha');

        $review1 = $reviews->create($session_id, ['label' => 'Review 1']);
        $review2 = $reviews->create($session_id, ['label' => 'Review 2']);

        $this->assertSame(
            'Thesis Alpha',
            $assignments->resolve_project_title($session_id, $review1, $student_id)
        );

        $assignments->set_student_project_title($review2, $student_id, 'Thesis Beta');
        $assignments->copy_from_review($review2, $review1);

        $this->assertSame(
            'Thesis Beta',
            $assignments->resolve_project_title($session_id, $review1, $student_id)
        );
        $this->assertSame(
            'Thesis Beta',
            $assignments->resolve_project_title($session_id, $review2, $student_id)
        );
    }

    public function test_copy_from_review_overwrites_target_only(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Copy test']);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');
        $student_id = $students->insert(['reg_no' => 'C1', 'name' => 'Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_a);

        $review1 = $reviews->create($session_id, ['label' => 'Review 1']);
        $review2 = $reviews->create($session_id, ['label' => 'Review 2']);
        $assignments->set_student_panel($review1, $student_id, $panel_b);

        $assignments->copy_from_review($review1, $review2);
        $this->assertSame($panel_b, (int) $assignments->get_student_panel($review2, $student_id)['panel_id']);
    }

    public function test_sync_panel_reviewers_from_session_without_clearing_students(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Reviewer sync']);
        $panel_id = $panels->create($session_id, 'Panel A');
        $panels->add_reviewer($panel_id, [
            'name' => 'R1',
            'email' => 'r1@x.com',
            'user_id' => 11,
        ]);

        $student_id = $students->insert(['reg_no' => 'S1', 'name' => 'Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id);

        $review_id = $reviews->create($session_id, ['label' => 'Review 1']);
        $assignments->delete_panel_reviewer($review_id, $panel_id, 11);
        $this->assertSame([], $assignments->list_panel_reviewers($review_id));
        $this->assertNotEmpty($assignments->list_student_panels($review_id));

        $assignments->sync_panel_reviewers_from_session($review_id, $session_id);

        $this->assertTrue($assignments->is_reviewer_on_panel($review_id, $panel_id, 11));
        $this->assertSame($panel_id, (int) $assignments->get_student_panel($review_id, $student_id)['panel_id']);
    }
}
