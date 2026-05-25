<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Services\ScoreService;

final class ScoreServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id;

    private int $review_id;

    private int $student_id;

    private int $panel_id;

    private int $reviewer_a = 601;

    private int $reviewer_b = 602;

    private int $criterion_a;

    private int $criterion_b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $this->session_id = $sessions->create(['title' => 'Score test', 'status' => SessionRepository::STATUS_ACTIVE]);
        $this->panel_id = $panels->create($this->session_id, 'Panel A');
        $panels->add_reviewer($this->panel_id, ['name' => 'A', 'email' => 'a@x.com', 'user_id' => $this->reviewer_a, 'weight' => 2]);
        $panels->add_reviewer($this->panel_id, ['name' => 'B', 'email' => 'b@x.com', 'user_id' => $this->reviewer_b, 'weight' => 1]);

        $this->student_id = $students->insert(['reg_no' => 'S001', 'name' => 'Student']);
        $sessions->enrol_student($this->session_id, $this->student_id, $this->panel_id);

        $this->review_id = $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($this->review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
            ['label' => 'C2', 'max_marks' => 10, 'weight' => 1],
        ]);
        $this->criterion_a = (int) $criteria[0]['id'];
        $this->criterion_b = (int) $criteria[1]['id'];

        // Reviewer A: 8/10 + 6/10 => avg 70%
        $marks->upsert($this->session_id, $this->review_id, $this->student_id, $this->reviewer_a, $this->criterion_a, 8.0, MarkRepository::STATUS_SUBMITTED);
        $marks->upsert($this->session_id, $this->review_id, $this->student_id, $this->reviewer_a, $this->criterion_b, 6.0, MarkRepository::STATUS_SUBMITTED);
        // Reviewer B: 10/10 + 10/10 => 100%
        $marks->upsert($this->session_id, $this->review_id, $this->student_id, $this->reviewer_b, $this->criterion_a, 10.0, MarkRepository::STATUS_SUBMITTED);
        $marks->upsert($this->session_id, $this->review_id, $this->student_id, $this->reviewer_b, $this->criterion_b, 10.0, MarkRepository::STATUS_SUBMITTED);
    }

    private function scoreService(): ScoreService
    {
        return new ScoreService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new PanelRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );
    }

    public function test_golden_three_reviewers_two_criteria_equal_weights(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Golden', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel');
        $reviewer_ids = [701, 702, 703];
        foreach ($reviewer_ids as $index => $user_id) {
            $panels->add_reviewer($panel_id, [
                'name' => 'R' . ($index + 1),
                'email' => "r{$user_id}@x.com",
                'user_id' => $user_id,
                'weight' => 1,
            ]);
        }

        $student_id = $students->insert(['reg_no' => 'G001', 'name' => 'Golden Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id);

        $review_id = $reviews->create($session_id, [
            'label' => 'Golden Review',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
            ['label' => 'C2', 'max_marks' => 10, 'weight' => 1],
        ]);
        $c1 = (int) $criteria[0]['id'];
        $c2 = (int) $criteria[1]['id'];

        $mark_table = [
            701 => [5.0, 5.0],
            702 => [5.0, 9.0],
            703 => [4.0, 5.0],
        ];
        foreach ($mark_table as $reviewer_user_id => [$s1, $s2]) {
            $marks->upsert($session_id, $review_id, $student_id, $reviewer_user_id, $c1, $s1, MarkRepository::STATUS_SUBMITTED);
            $marks->upsert($session_id, $review_id, $student_id, $reviewer_user_id, $c2, $s2, MarkRepository::STATUS_SUBMITTED);
        }

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            new ReviewAssignmentRepository($this->wpdb),
            $marks
        );

        $this->assertSame(10.0, $service->calculate_reviewer_total($session_id, $student_id, $review_id, 701));
        $this->assertSame(14.0, $service->calculate_reviewer_total($session_id, $student_id, $review_id, 702));
        $this->assertSame(9.0, $service->calculate_reviewer_total($session_id, $student_id, $review_id, 703));

        $aggregate = $service->calculate_review_score($session_id, $student_id, $review_id);
        $this->assertSame(11.0, $aggregate['review_score']);

        $combined = $service->calculate_combined_score($session_id, $student_id);
        $this->assertSame(11.0, $combined['combined_score']);
    }

    public function test_level1_reviewer_total(): void
    {
        $service = $this->scoreService();

        $total_a = $service->calculate_reviewer_total(
            $this->session_id,
            $this->student_id,
            $this->review_id,
            $this->reviewer_a
        );

        $this->assertSame(14.0, $total_a);
        $this->assertSame(20.0, $service->calculate_reviewer_total(
            $this->session_id,
            $this->student_id,
            $this->review_id,
            $this->reviewer_b
        ));
    }

    public function test_level2_review_score_weighted_by_reviewer(): void
    {
        $service = $this->scoreService();

        $aggregate = $service->calculate_review_score($this->session_id, $this->student_id, $this->review_id);

        // (14*2 + 20*1) / 3 = 16
        $this->assertSame(16.0, $aggregate['review_score']);
    }

    public function test_level3_combined_score_defaults_weight_to_one(): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        $review2_id = $reviews->create($this->session_id, [
            'label' => 'Review 2',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $c2 = $reviews->replace_criteria($review2_id, [
            ['label' => 'Only', 'max_marks' => 10, 'weight' => 1],
        ]);
        $marks = new MarkRepository($this->wpdb);
        $marks->upsert(
            $this->session_id,
            $review2_id,
            $this->student_id,
            $this->reviewer_a,
            (int) $c2[0]['id'],
            5.0,
            MarkRepository::STATUS_SUBMITTED
        );

        $service = new ScoreService(
            new SessionRepository($this->wpdb),
            $reviews,
            new PanelRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            $marks
        );

        $combined = $service->calculate_combined_score($this->session_id, $this->student_id);

        // Review1=16, Review2=5 => (16+5)/2 = 10.5
        $this->assertSame(10.5, $combined['combined_score']);
    }

    public function test_progress_percent_matches_student_grain(): void
    {
        $service = $this->scoreService();
        $reviews = $service->calculate_session_progress($this->session_id);
        $this->assertNotEmpty($reviews);

        $review = $reviews[0];
        $row_a = null;
        foreach ($review['rows'] as $row) {
            if ((int) $row['reviewer_user_id'] === $this->reviewer_a) {
                $row_a = $row;
                break;
            }
        }

        $this->assertNotNull($row_a);
        // 1 student, 2 criteria submitted — counts as 1 student complete
        $this->assertSame(1, $row_a['completed']);
        $this->assertSame(1, $row_a['total']);
        $this->assertSame(100.0, $row_a['percent']);
        $this->assertSame('complete', $row_a['status']);
    }

    public function test_progress_includes_not_started_reviewer(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Not started', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel A');
        $panels->add_reviewer($panel_id, ['name' => 'A', 'email' => 'a3@x.com', 'user_id' => $this->reviewer_a, 'weight' => 1]);
        $panels->add_reviewer($panel_id, ['name' => 'B', 'email' => 'b3@x.com', 'user_id' => $this->reviewer_b, 'weight' => 1]);

        $student_id = $students->insert(['reg_no' => 'S200', 'name' => 'Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id);

        $review_id = $reviews->create($session_id, [
            'label' => 'Review NS',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
        ]);
        $criterion_id = (int) $criteria[0]['id'];

        $marks->upsert(
            $session_id,
            $review_id,
            $student_id,
            $this->reviewer_a,
            $criterion_id,
            7.0,
            MarkRepository::STATUS_SUBMITTED
        );

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            new ReviewAssignmentRepository($this->wpdb),
            $marks
        );

        $rows = $service->calculate_session_progress($session_id)[0]['rows'];
        $this->assertCount(2, $rows);

        $row_b = null;
        foreach ($rows as $row) {
            if ((int) $row['reviewer_user_id'] === $this->reviewer_b) {
                $row_b = $row;
                break;
            }
        }

        $this->assertNotNull($row_b);
        $this->assertSame(0, $row_b['completed']);
        $this->assertSame(1, $row_b['total']);
        $this->assertSame(0.0, $row_b['percent']);
        $this->assertTrue($row_b['linked'] ?? false);
        $this->assertSame($this->reviewer_b, $row_b['reviewer_user_id']);
        $this->assertSame('not_started', $row_b['status']);
    }

    public function test_progress_two_students_one_complete(): void
    {
        $students = new StudentRepository($this->wpdb);
        $sessions = new SessionRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $student2_id = $students->insert(['reg_no' => 'S002', 'name' => 'Student Two']);
        $sessions->enrol_student($this->session_id, $student2_id, $this->panel_id);

        $marks->upsert(
            $this->session_id,
            $this->review_id,
            $student2_id,
            $this->reviewer_a,
            $this->criterion_a,
            5.0,
            MarkRepository::STATUS_SUBMITTED
        );

        $service = $this->scoreService();
        $reviews = $service->calculate_session_progress($this->session_id);
        $row_a = null;
        foreach ($reviews[0]['rows'] as $row) {
            if ((int) $row['reviewer_user_id'] === $this->reviewer_a) {
                $row_a = $row;
                break;
            }
        }

        $this->assertNotNull($row_a);
        $this->assertSame(1, $row_a['completed']);
        $this->assertSame(2, $row_a['total']);
        $this->assertSame(50.0, $row_a['percent']);
        $this->assertSame('in_progress', $row_a['status']);
    }

    public function test_progress_lists_every_session_panel_reviewer_for_panel(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'All reviewers', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel A');
        $panels->add_reviewer($panel_id, ['name' => 'Alpha', 'email' => 'alpha@x.com', 'user_id' => $this->reviewer_a, 'weight' => 1]);
        $panels->add_reviewer($panel_id, ['name' => 'Beta', 'email' => 'beta@x.com', 'user_id' => $this->reviewer_b, 'weight' => 1]);
        $panels->add_reviewer($panel_id, ['name' => 'Gamma', 'email' => 'gamma@x.com', 'user_id' => 0, 'weight' => 1]);

        $student_id = $students->insert(['reg_no' => 'ALL1', 'name' => 'Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id);

        $review_id = $reviews->create($session_id, [
            'label' => 'Review all reviewers',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
        ]);
        $assignments->set_student_panel($review_id, $student_id, $panel_id);

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            $assignments,
            new MarkRepository($this->wpdb)
        );

        $panel = $service->calculate_session_progress($session_id)[0]['panels'][0];
        $this->assertCount(3, $panel['rows']);

        $names = array_map(static fn (array $row): string => (string) ($row['reviewer_name'] ?? ''), $panel['rows']);
        $this->assertContains('Alpha', $names);
        $this->assertContains('Beta', $names);
        $this->assertContains('Gamma', $names);

        $gamma = null;
        foreach ($panel['rows'] as $row) {
            if ((string) ($row['reviewer_name'] ?? '') === 'Gamma') {
                $gamma = $row;
                break;
            }
        }
        $this->assertNotNull($gamma);
        $this->assertFalse($gamma['linked']);
        $this->assertNull($gamma['reviewer_user_id']);
        $this->assertGreaterThan(0, (int) ($gamma['panel_reviewer_id'] ?? 0));
    }

    public function test_progress_includes_all_panels_and_review_student_totals(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Multi panel', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');
        $panels->add_reviewer($panel_a, ['name' => 'A1', 'email' => 'a1@x.com', 'user_id' => $this->reviewer_a, 'weight' => 1]);
        $panels->add_reviewer($panel_b, ['name' => 'B1', 'email' => 'b1@x.com', 'user_id' => $this->reviewer_b, 'weight' => 1]);

        $student_a = $students->insert(['reg_no' => 'PA1', 'name' => 'On A']);
        $student_b = $students->insert(['reg_no' => 'PB1', 'name' => 'On B']);
        $sessions->enrol_student($session_id, $student_a, $panel_a);
        $sessions->enrol_student($session_id, $student_b, $panel_b);

        $review_id = $reviews->create($session_id, [
            'label' => 'Review panels',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
        ]);

        $assignments->set_student_panel($review_id, $student_a, $panel_a);
        $assignments->set_student_panel($review_id, $student_b, $panel_b);

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            $assignments,
            new MarkRepository($this->wpdb)
        );

        $review = $service->calculate_session_progress($session_id)[0];
        $this->assertCount(2, $review['panels']);
        $this->assertSame(2, $review['summary']['students_total']);

        $totals_by_panel = [];
        foreach ($review['panels'] as $panel) {
            $totals_by_panel[(int) $panel['panel_id']] = (int) $panel['students_total'];
            $this->assertCount(1, $panel['rows']);
            $this->assertSame(1, $panel['rows'][0]['total']);
        }

        $this->assertSame(1, $totals_by_panel[$panel_a]);
        $this->assertSame(1, $totals_by_panel[$panel_b]);

        foreach ($review['panels'] as $panel) {
            $panel_summary = $panel['summary'];
            $this->assertSame(1, $panel_summary['marks_total']);
            $this->assertSame(
                $panel_summary['marks_completed']
                    + $panel_summary['marks_in_progress']
                    + $panel_summary['marks_not_started'],
                $panel_summary['marks_total']
            );
        }
    }

    public function test_review_summary_requires_all_panel_reviewers(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Summary test', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel A');
        $panels->add_reviewer($panel_id, ['name' => 'A', 'email' => 'a2@x.com', 'user_id' => $this->reviewer_a, 'weight' => 1]);
        $panels->add_reviewer($panel_id, ['name' => 'B', 'email' => 'b2@x.com', 'user_id' => $this->reviewer_b, 'weight' => 1]);

        $student_id = $students->insert(['reg_no' => 'S100', 'name' => 'Summary Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id);

        $review_id = $reviews->create($session_id, [
            'label' => 'Review summary',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
            ['label' => 'C2', 'max_marks' => 10, 'weight' => 1],
        ]);
        $criterion_a = (int) $criteria[0]['id'];
        $criterion_b = (int) $criteria[1]['id'];

        foreach ([$this->reviewer_a, $this->reviewer_b] as $reviewer_id) {
            $marks->upsert($session_id, $review_id, $student_id, $reviewer_id, $criterion_a, 8.0, MarkRepository::STATUS_SUBMITTED);
            $marks->upsert($session_id, $review_id, $student_id, $reviewer_id, $criterion_b, 8.0, MarkRepository::STATUS_SUBMITTED);
        }

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            new ReviewAssignmentRepository($this->wpdb),
            $marks
        );

        $summary = $service->calculate_session_progress($session_id)[0]['summary'];
        $this->assertSame(1, $summary['students_completed']);
        $this->assertSame(100.0, $summary['percent']);
        $this->assertSame(2, $summary['marks_completed']);
        $this->assertSame(0, $summary['marks_in_progress']);
        $this->assertSame(0, $summary['marks_not_started']);
        $this->assertSame(2, $summary['marks_total']);

        $marks->upsert($session_id, $review_id, $student_id, $this->reviewer_b, $criterion_b, null, MarkRepository::STATUS_DRAFT);

        $summary = $service->calculate_session_progress($session_id)[0]['summary'];
        $this->assertSame(0, $summary['students_completed']);
        $this->assertSame(1, $summary['students_total']);
        $this->assertSame(50.0, $summary['percent']);
        $this->assertSame(1, $summary['marks_completed']);
        $this->assertSame(1, $summary['marks_in_progress']);
        $this->assertSame(0, $summary['marks_not_started']);
        $this->assertSame(2, $summary['marks_total']);
    }

    public function test_mark_summary_one_of_two_reviewers_complete(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Mark grain', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel A');
        $panels->add_reviewer($panel_id, ['name' => 'A', 'email' => 'mg-a@x.com', 'user_id' => $this->reviewer_a, 'weight' => 1]);
        $panels->add_reviewer($panel_id, ['name' => 'B', 'email' => 'mg-b@x.com', 'user_id' => $this->reviewer_b, 'weight' => 1]);

        $student_id = $students->insert(['reg_no' => 'MG1', 'name' => 'Mark Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id);

        $review_id = $reviews->create($session_id, [
            'label' => 'Mark grain review',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
            ['label' => 'C2', 'max_marks' => 10, 'weight' => 1],
        ]);
        $criterion_a = (int) $criteria[0]['id'];
        $criterion_b = (int) $criteria[1]['id'];

        foreach ([$criterion_a, $criterion_b] as $criterion_id) {
            $marks->upsert($session_id, $review_id, $student_id, $this->reviewer_a, $criterion_id, 8.0, MarkRepository::STATUS_SUBMITTED);
        }

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            new ReviewAssignmentRepository($this->wpdb),
            $marks
        );

        $summary = $service->calculate_session_progress($session_id)[0]['summary'];
        $this->assertSame(50.0, $summary['percent']);
        $this->assertSame(1, $summary['marks_completed']);
        $this->assertSame(0, $summary['marks_in_progress']);
        $this->assertSame(1, $summary['marks_not_started']);
        $this->assertSame(2, $summary['marks_total']);
    }

    public function test_mark_summary_unlinked_reviewer_counts_not_started(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Unlinked mark', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($session_id, 'Panel A');
        $panels->add_reviewer($panel_id, ['name' => 'Linked', 'email' => 'lnk@x.com', 'user_id' => $this->reviewer_a, 'weight' => 1]);
        $panels->add_reviewer($panel_id, ['name' => 'Unlinked', 'email' => 'unlnk@x.com', 'user_id' => 0, 'weight' => 1]);

        $student_id = $students->insert(['reg_no' => 'UL1', 'name' => 'Student']);
        $sessions->enrol_student($session_id, $student_id, $panel_id);

        $review_id = $reviews->create($session_id, [
            'label' => 'Unlinked review',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
            ['label' => 'C2', 'max_marks' => 10, 'weight' => 1],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $marks->upsert($session_id, $review_id, $student_id, $this->reviewer_a, $criterion_id, 5.0, MarkRepository::STATUS_DRAFT);

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            new ReviewAssignmentRepository($this->wpdb),
            $marks
        );

        $summary = $service->calculate_session_progress($session_id)[0]['summary'];
        $this->assertSame(2, $summary['marks_total']);
        $this->assertSame(0, $summary['marks_completed']);
        $this->assertSame(1, $summary['marks_in_progress']);
        $this->assertSame(1, $summary['marks_not_started']);
        $this->assertSame(0.0, $summary['percent']);
        $this->assertSame(
            $summary['marks_completed'] + $summary['marks_in_progress'] + $summary['marks_not_started'],
            $summary['marks_total']
        );
    }

    public function test_panel_mark_summary_scoped_per_panel(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $marks = new MarkRepository($this->wpdb);

        $session_id = $sessions->create(['title' => 'Panel scope', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');
        $panels->add_reviewer($panel_a, ['name' => 'A1', 'email' => 'pa@x.com', 'user_id' => $this->reviewer_a, 'weight' => 1]);
        $panels->add_reviewer($panel_b, ['name' => 'B1', 'email' => 'pb@x.com', 'user_id' => $this->reviewer_b, 'weight' => 1]);

        $student_a = $students->insert(['reg_no' => 'PSA', 'name' => 'On A']);
        $student_b = $students->insert(['reg_no' => 'PSB', 'name' => 'On B']);
        $sessions->enrol_student($session_id, $student_a, $panel_a);
        $sessions->enrol_student($session_id, $student_b, $panel_b);

        $review_id = $reviews->create($session_id, [
            'label' => 'Panel scope review',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($review_id, [
            ['label' => 'C1', 'max_marks' => 10, 'weight' => 1],
        ]);
        $criterion_id = (int) $criteria[0]['id'];
        $marks->upsert($session_id, $review_id, $student_a, $this->reviewer_a, $criterion_id, 10.0, MarkRepository::STATUS_SUBMITTED);

        $assignments->set_student_panel($review_id, $student_a, $panel_a);
        $assignments->set_student_panel($review_id, $student_b, $panel_b);

        $service = new ScoreService(
            $sessions,
            $reviews,
            $panels,
            $assignments,
            $marks
        );

        $review = $service->calculate_session_progress($session_id)[0];
        $this->assertCount(2, $review['panels']);

        $panel_a_summary = null;
        $panel_b_summary = null;
        foreach ($review['panels'] as $panel) {
            if ((int) $panel['panel_id'] === $panel_a) {
                $panel_a_summary = $panel['summary'];
            }
            if ((int) $panel['panel_id'] === $panel_b) {
                $panel_b_summary = $panel['summary'];
            }
        }

        $this->assertNotNull($panel_a_summary);
        $this->assertNotNull($panel_b_summary);
        $this->assertSame(1, $panel_a_summary['marks_completed']);
        $this->assertSame(0, $panel_a_summary['marks_in_progress']);
        $this->assertSame(0, $panel_a_summary['marks_not_started']);
        $this->assertSame(1, $panel_a_summary['marks_total']);
        $this->assertSame(100.0, $panel_a_summary['percent']);

        $this->assertSame(0, $panel_b_summary['marks_completed']);
        $this->assertSame(0, $panel_b_summary['marks_in_progress']);
        $this->assertSame(1, $panel_b_summary['marks_not_started']);
        $this->assertSame(1, $panel_b_summary['marks_total']);

        $review_summary = $review['summary'];
        $this->assertSame(
            $panel_a_summary['marks_total'] + $panel_b_summary['marks_total'],
            $review_summary['marks_total']
        );
        $this->assertSame(
            $panel_a_summary['marks_completed'] + $panel_b_summary['marks_completed'],
            $review_summary['marks_completed']
        );
    }
}
