<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Install;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Services\PanelHeadService;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Services\MarkService;

final class MarkServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id;

    private int $review_id;

    private int $student_id;

    private int $reviewer_user_id = 501;

    private int $criterion_id;

    /** @var list<int> */
    private array $criterion_ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        Install::ensure_schema_patches();

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);

        $this->session_id = $sessions->create(['title' => 'Mark test', 'status' => SessionRepository::STATUS_ACTIVE]);
        $panel_id = $panels->create($this->session_id, 'Panel A');
        $reviewer_row_id = $panels->add_reviewer($panel_id, [
            'name' => 'Reviewer One',
            'email' => 'r1@example.com',
            'user_id' => $this->reviewer_user_id,
        ]);
        (new PanelHeadService($panels))->set_session_panel_head($reviewer_row_id, true);

        $this->student_id = $students->insert(['reg_no' => 'M001', 'name' => 'Student One']);
        $sessions->enrol_student($this->session_id, $this->student_id, $panel_id);

        $this->review_id = $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($this->review_id, [
            ['label' => 'Quality', 'max_marks' => 10, 'weight' => 2],
            ['label' => 'Presentation', 'max_marks' => 5, 'weight' => 1],
        ]);
        $this->criterion_id = (int) $criteria[0]['id'];
        $this->criterion_ids = array_map(static fn (array $row): int => (int) $row['id'], $criteria);
        $reviews->set_marking_active($this->review_id, true);
        (new ReviewAssignmentRepository($this->wpdb))->seed_from_session_defaults(
            $this->review_id,
            $this->session_id
        );
    }

    private function markService(): MarkService
    {
        return new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb),
            new UnfreezeRequestRepository($this->wpdb)
        );
    }

    private function freezePanelMarks(MarkService $service): int
    {
        $panels = new PanelRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);
        $reviews = new ReviewRepository($this->wpdb);

        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [['criterion_id' => (int) $row['id'], 'score' => 5]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }

        $service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id
        );

        return $panel_id;
    }

    public function test_save_draft_marks_persists(): void
    {
        $service = $this->markService();

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [
                ['criterion_id' => $this->criterion_id, 'score' => 8],
            ],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertIsArray($result);
        $this->assertCount(1, $result['marks']);
        $this->assertSame(MarkRepository::STATUS_DRAFT, $result['marks'][0]['status']);
        $this->assertSame(8.0, $result['marks'][0]['score']);
    }

    public function test_save_submitted_marks_persists(): void
    {
        $service = $this->markService();

        $reviews = new ReviewRepository($this->wpdb);
        $payload = [];
        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $max = (float) ($row['max_marks'] ?? 10);
            $payload[] = [
                'criterion_id' => (int) $row['id'],
                'score' => min(9.0, $max),
            ];
        }
        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $payload,
            MarkRepository::STATUS_SUBMITTED,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertIsArray($result);
        $this->assertCount(count($this->criterion_ids), $result['marks']);
        $this->assertSame(MarkRepository::STATUS_SUBMITTED, $result['marks'][0]['status']);
    }

    public function test_rejects_unconfirmed_rubric(): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        $draft_review_id = $reviews->create($this->session_id, ['label' => 'Draft review']);

        $service = $this->markService();

        $result = $service->save_marks(
            $this->session_id,
            $draft_review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rubric_not_confirmed', $result->get_error_code());
    }

    public function test_rejects_closed_session(): void
    {
        $sessions = new SessionRepository($this->wpdb);
        $sessions->update($this->session_id, ['status' => SessionRepository::STATUS_CLOSED]);

        $service = new MarkService(
            $sessions,
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('session_closed', $result->get_error_code());
    }

    public function test_rejects_unassigned_reviewer(): void
    {
        $service = $this->markService();

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            9999,
            [['criterion_id' => $this->criterion_id, 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_assigned', $result->get_error_code());
    }

    public function test_rejects_score_above_max_marks(): void
    {
        $service = $this->markService();

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 99]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_score', $result->get_error_code());
    }

    public function test_accepts_half_point_score(): void
    {
        $service = $this->markService();

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 2.5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertIsArray($result);
        $this->assertSame(2.5, $result['marks'][0]['score']);
    }

    public function test_rejects_non_half_point_score(): void
    {
        $service = $this->markService();

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 2.3]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_score', $result->get_error_code());
    }

    public function test_accepts_boundary_scores_zero_and_max(): void
    {
        $service = $this->markService();

        $zero = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 0]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($zero);
        $this->assertSame(0.0, $zero['marks'][0]['score']);

        $max = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 10]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($max);
        $this->assertSame(10.0, $max['marks'][0]['score']);
    }

    public function test_override_sets_coordinator_shuttle_columns(): void
    {
        $marks = new MarkRepository($this->wpdb);
        $mark_id = $marks->upsert(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $this->criterion_id,
            8.0,
            MarkRepository::STATUS_SUBMITTED
        );

        $result = $this->markService()->override_mark(
            $mark_id,
            7.5,
            'Panel consensus recorded wrong score',
            1
        );

        $this->assertTrue($result['ok'] ?? false);
        $mark = $result['mark'] ?? [];
        $this->assertTrue($mark['coordinator_overridden'] ?? false);
        $this->assertFalse($mark['flagged'] ?? true);
        $this->assertSame(8.0, $mark['overridden_from_score'] ?? null);
        $this->assertSame(7.5, $mark['score'] ?? null);

        $second = $this->markService()->override_mark(
            $mark_id,
            6.0,
            'Second correction with audit reason',
            1
        );
        $this->assertTrue($second['ok'] ?? false);
        $this->assertSame(8.0, $second['mark']['overridden_from_score'] ?? null);
    }

    public function test_override_rejects_non_half_point_score(): void
    {
        $marks = new MarkRepository($this->wpdb);
        $mark_id = $marks->upsert(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $this->criterion_id,
            5.0,
            MarkRepository::STATUS_SUBMITTED
        );

        $override = $this->markService()->override_mark(
            $mark_id,
            2.3,
            'Valid reason text',
            1
        );

        $this->assertFalse($override['ok']);
        $this->assertSame('invalid_score', $override['error']);
    }

    public function test_rejects_submit_without_all_criteria_scores(): void
    {
        $service = $this->markService();

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 8]],
            MarkRepository::STATUS_SUBMITTED,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_score', $result->get_error_code());
    }

    public function test_rejects_marking_inactive_review(): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        $reviews->set_marking_active($this->review_id, false);

        $result = $this->markService()->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('marking_inactive', $result->get_error_code());
    }

    public function test_freeze_review_marks_submits_all_criteria(): void
    {
        $service = $this->markService();
        $reviews = new ReviewRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $panel_id = $panels->list_by_session($this->session_id)[0]['id'] ?? 0;
        $panel_id = (int) $panel_id;

        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [['criterion_id' => (int) $row['id'], 'score' => 5]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }

        $result = $service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['frozen']);
        $this->assertSame(1, $result['students_updated']);

        $marks = new MarkRepository($this->wpdb);
        $this->assertTrue(
            $marks->is_student_frozen_for_reviewer(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                count($this->criterion_ids)
            )
        );
    }

    public function test_freeze_returns_incomplete_marks_when_scores_missing(): void
    {
        $service = $this->markService();
        $panels = new PanelRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);

        $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $result = $service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('incomplete_marks', $result->get_error_code());
        $this->assertStringContainsString('Student One', $result->message);
        $this->assertStringContainsString('Presentation', $result->message);

        $data = $result->get_error_data();
        $this->assertIsArray($data);
        $this->assertSame(1, $data['incomplete_count'] ?? null);
        $incomplete = $data['incomplete'] ?? [];
        $this->assertCount(1, $incomplete);
        $this->assertSame($this->student_id, $incomplete[0]['student_id'] ?? null);
        $missing_labels = array_column($incomplete[0]['missing_criteria'] ?? [], 'label');
        $this->assertContains('Presentation', $missing_labels);
    }

    public function test_partial_draft_save_does_not_clear_other_criteria(): void
    {
        $service = $this->markService();
        $reviews = new ReviewRepository($this->wpdb);
        $criteria = $reviews->list_criteria($this->review_id);
        $first_id = (int) $criteria[0]['id'];
        $second_id = (int) $criteria[1]['id'];

        $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [
                ['criterion_id' => $first_id, 'score' => 7],
                ['criterion_id' => $second_id, 'score' => 4],
            ],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $first_id, 'score' => 8]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $marks = new MarkRepository($this->wpdb);
        $rows = $marks->list_for_student_review(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id
        );
        $by_criterion = [];
        foreach ($rows as $row) {
            $by_criterion[(int) ($row['criterion_id'] ?? 0)] = $row['score'];
        }

        $this->assertSame(8.0, (float) ($by_criterion[$first_id] ?? 0));
        $this->assertSame(4.0, (float) ($by_criterion[$second_id] ?? 0));
    }

    public function test_save_rejected_when_student_frozen(): void
    {
        $service = $this->markService();
        $panels = new PanelRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);
        $reviews = new ReviewRepository($this->wpdb);

        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $max = (float) ($row['max_marks'] ?? 10);
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [['criterion_id' => (int) $row['id'], 'score' => min(6.0, $max)]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }

        $service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id
        );

        $result = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 7]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('marks_frozen', $result->get_error_code());
    }

    public function test_attendance_required_on_save(): void
    {
        $result = $this->markService()->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [],
            MarkRepository::STATUS_DRAFT,
            null
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('attendance_required', $result->get_error_code());
    }

    public function test_save_absent_clears_scores_for_all_criteria(): void
    {
        $service = $this->markService();
        $reviews = new ReviewRepository($this->wpdb);

        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [['criterion_id' => (int) $row['id'], 'score' => 7]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }

        $absent = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );

        $this->assertIsArray($absent);
        $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_ABSENT, $absent['attendance_status']);

        $marks = new MarkRepository($this->wpdb);
        foreach ($marks->list_for_student_review(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id
        ) as $row) {
            $this->assertNull($row['score']);
        }
    }

    public function test_freeze_succeeds_when_present_complete_and_other_absent(): void
    {
        $service = $this->markService();
        $reviews = new ReviewRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $sessions = new SessionRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);

        $absent_student_id = $students->insert(['reg_no' => 'M002', 'name' => 'Student Two']);
        $sessions->enrol_student($this->session_id, $absent_student_id, $panel_id);

        foreach ($reviews->list_criteria($this->review_id) as $row) {
            $max = (float) ($row['max_marks'] ?? 10);
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                [['criterion_id' => (int) $row['id'], 'score' => min(6.0, $max)]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
        }

        $service->save_marks(
            $this->session_id,
            $this->review_id,
            $absent_student_id,
            $this->reviewer_user_id,
            [],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );

        $result = $service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['frozen']);
        $this->assertSame(2, $result['students_updated']);
    }

    public function test_attendance_conflict_when_peer_has_marks_but_no_assertion_row(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);
        $reviewer_two = 503;
        $panels->add_reviewer($panel_id, [
            'name' => 'Reviewer Three',
            'email' => 'r3@example.com',
            'user_id' => $reviewer_two,
        ]);
        $assignments->upsert_panel_reviewer($this->review_id, $panel_id, $reviewer_two, 1.0, false);

        $service = $this->markService();
        $criteria_payload = [['criterion_id' => $this->criterion_id, 'score' => 5]];

        $this->assertIsArray($service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $criteria_payload,
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        ));

        $assertions_table = $this->wpdb->prefix . 'pr_review_student_attendance_by_reviewer';
        $this->wpdb->delete(
            $assertions_table,
            [
                'review_id' => $this->review_id,
                'student_id' => $this->student_id,
            ]
        );

        $conflict = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $reviewer_two,
            $criteria_payload,
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );

        $this->assertInstanceOf(\WP_Error::class, $conflict);
        $this->assertSame('attendance_conflict', $conflict->get_error_code());
    }

    public function test_attendance_conflict_when_panel_reviewers_disagree(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);
        $reviewer_two = 502;
        $panels->add_reviewer($panel_id, [
            'name' => 'Reviewer Two',
            'email' => 'r2@example.com',
            'user_id' => $reviewer_two,
        ]);
        $assignments->upsert_panel_reviewer($this->review_id, $panel_id, $reviewer_two, 1.0, false);

        $service = $this->markService();
        $criteria_payload = [['criterion_id' => $this->criterion_id, 'score' => 5]];

        $first = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $criteria_payload,
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($first);

        $conflict = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $reviewer_two,
            $criteria_payload,
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );
        $this->assertInstanceOf(\WP_Error::class, $conflict);
        $this->assertSame('attendance_conflict', $conflict->get_error_code());
        $data = $conflict->get_error_data();
        $this->assertIsArray($data);
        $conflicts = $data['conflicts'] ?? null;
        $this->assertIsArray($conflicts);
        $this->assertCount(2, $conflicts);
        $names = array_column($conflicts, 'reviewer_name');
        $this->assertContains('Reviewer One', $names);
        $this->assertContains('Reviewer Two', $names);
        $this->assertSame(
            ReviewAssignmentRepository::ATTENDANCE_PRESENT,
            $assignments->get_attendance_status($this->review_id, $this->student_id)
        );

        $aligned = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $reviewer_two,
            $criteria_payload,
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($aligned);
        $this->assertSame(
            ReviewAssignmentRepository::ATTENDANCE_PRESENT,
            $assignments->get_attendance_status($this->review_id, $this->student_id)
        );
    }

    public function test_get_marks_includes_attendance_status(): void
    {
        $service = $this->markService();
        $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 5]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $result = $service->get_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            false
        );

        $this->assertIsArray($result);
        $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_PRESENT, $result['attendance_status']);
    }

    public function test_coordinator_corrects_unanimous_present_to_absent(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $panel_id = (int) ($panels->list_by_session($this->session_id)[0]['id'] ?? 0);
        $reviewer_two = 502;
        $reviewer_three = 503;
        $panels->add_reviewer($panel_id, [
            'name' => 'Reviewer Two',
            'email' => 'r2@example.com',
            'user_id' => $reviewer_two,
        ]);
        $panels->add_reviewer($panel_id, [
            'name' => 'Reviewer Three',
            'email' => 'r3@example.com',
            'user_id' => $reviewer_three,
        ]);
        $assignments->upsert_panel_reviewer($this->review_id, $panel_id, $reviewer_two, 1.0, false);
        $assignments->upsert_panel_reviewer($this->review_id, $panel_id, $reviewer_three, 1.0, false);

        $service = $this->markService();
        $criteria_payload = [['criterion_id' => $this->criterion_id, 'score' => 5]];

        foreach ([$this->reviewer_user_id, $reviewer_two] as $reviewer_id) {
            $saved = $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $reviewer_id,
                $criteria_payload,
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
            $this->assertIsArray($saved);
        }

        $conflict = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $reviewer_three,
            [],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );
        $this->assertInstanceOf(\WP_Error::class, $conflict);
        $this->assertSame('attendance_conflict', $conflict->get_error_code());

        $corrected = $service->correct_attendance_by_coordinator(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'Student did not attend the oral review.',
            900
        );
        $this->assertIsArray($corrected);
        $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_ABSENT, $corrected['attendance_status']);
        $this->assertSame(3, $corrected['reviewers_updated']);

        $this->assertSame(
            ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            $assignments->get_attendance_status($this->review_id, $this->student_id)
        );

        $assertions = $assignments->list_attendance_assertions_for_panel_student(
            $this->review_id,
            $this->student_id,
            $panel_id
        );
        $this->assertCount(3, $assertions);
        foreach ($assertions as $row) {
            $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_ABSENT, $row['attendance_status']);
        }

        foreach ([$this->reviewer_user_id, $reviewer_two, $reviewer_three] as $reviewer_id) {
            foreach ($service->get_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $reviewer_id,
                false
            )['marks'] as $mark) {
                $this->assertNull($mark['score']);
            }
        }

        $after = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $reviewer_three,
            [],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );
        $this->assertIsArray($after);
        $this->assertSame(ReviewAssignmentRepository::ATTENDANCE_ABSENT, $after['attendance_status']);
    }

    public function test_coordinator_attendance_correction_requires_reason(): void
    {
        $service = $this->markService();
        $result = $service->correct_attendance_by_coordinator(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'short',
            900
        );
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('reason_too_short', $result->get_error_code());
    }

    public function test_request_unfreeze_when_not_frozen_returns_error(): void
    {
        $service = $this->markService();
        $panel_id = (int) (new PanelRepository($this->wpdb))->list_by_session($this->session_id)[0]['id'];

        $result = $service->request_unfreeze(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id,
            'Need to fix a typo'
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_frozen', $result->get_error_code());
    }

    public function test_request_unfreeze_requires_reason(): void
    {
        $service = $this->markService();
        $panel_id = $this->freezePanelMarks($service);

        $result = $service->request_unfreeze(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id,
            '   '
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('unfreeze_reason_required', $result->get_error_code());
    }

    public function test_request_unfreeze_is_idempotent(): void
    {
        $service = $this->markService();
        $panel_id = $this->freezePanelMarks($service);

        $first = $service->request_unfreeze(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id,
            'Wrong score entered'
        );
        $second = $service->request_unfreeze(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id,
            'Ignored on duplicate'
        );

        $this->assertIsArray($first);
        $this->assertIsArray($second);
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(UnfreezeRequestRepository::STATUS_PENDING, $second['status']);
    }

    public function test_grant_unfreeze_reverts_marks_and_allows_save(): void
    {
        $service = $this->markService();
        $panel_id = $this->freezePanelMarks($service);
        $request = $service->request_unfreeze(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id,
            'Need to correct marks'
        );
        $this->assertIsArray($request);

        $marks = new MarkRepository($this->wpdb);
        $this->assertTrue(
            $marks->is_student_frozen_for_reviewer(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                count($this->criterion_ids)
            )
        );

        $grant = $service->grant_unfreeze((int) $request['id'], $this->reviewer_user_id);
        $this->assertIsArray($grant);
        $this->assertTrue($grant['granted']);
        $this->assertGreaterThan(0, $grant['marks_reverted']);

        $this->assertFalse(
            $marks->is_student_frozen_for_reviewer(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $this->reviewer_user_id,
                count($this->criterion_ids)
            )
        );

        $saved = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 9]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($saved);
    }

    public function test_coordinator_without_panel_head_cannot_grant_unfreeze(): void
    {
        $service = $this->markService();
        $panel_id = $this->freezePanelMarks($service);
        $request = $service->request_unfreeze(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id,
            'Need correction'
        );
        $this->assertIsArray($request);

        $grant = $service->grant_unfreeze((int) $request['id'], 900);
        $this->assertInstanceOf(\WP_Error::class, $grant);
        $this->assertSame('not_panel_coordinator', $grant->get_error_code());
    }

    public function test_request_unfreeze_blocked_when_panel_frozen(): void
    {
        $service = $this->markService();
        $panel_id = $this->freezePanelMarks($service);
        (new PanelFreezeRepository($this->wpdb))->freeze($this->review_id, $panel_id, $this->reviewer_user_id);

        $result = $service->request_unfreeze(
            $this->session_id,
            $this->review_id,
            $panel_id,
            $this->reviewer_user_id,
            'Need to fix scores'
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('panel_scores_frozen', $result->get_error_code());
    }

    public function test_coordinator_lock_blocks_save_and_override(): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        $reviews->set_coordinator_marks_locked($this->review_id, true);

        $service = $this->markService();
        $save = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            [['criterion_id' => $this->criterion_id, 'score' => 7]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertInstanceOf(\WP_Error::class, $save);
        $this->assertSame('coordinator_marks_locked', $save->get_error_code());

        $marks = new MarkRepository($this->wpdb);
        $marks->upsert(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id,
            $this->criterion_id,
            5.0,
            MarkRepository::STATUS_SUBMITTED
        );
        $mark_id = (int) ($marks->list_for_student_review(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->reviewer_user_id
        )[0]['id'] ?? 0);

        $override = $service->override_mark($mark_id, 6.0, 'Valid reason text', 1);
        $this->assertFalse($override['ok']);
        $this->assertSame('coordinator_marks_locked', $override['error']);
    }

    public function test_lock_review_marks_is_idempotent_and_audited(): void
    {
        $service = $this->markService();
        $panel_id = $this->freezePanelMarks($service);
        (new PanelFreezeRepository($this->wpdb))->freeze($this->review_id, $panel_id, 42);

        $first = $service->lock_review_marks($this->session_id, $this->review_id, 42);
        $this->assertIsArray($first);
        $this->assertTrue($first['coordinator_marks_locked']);

        $reviews = new ReviewRepository($this->wpdb);
        $this->assertTrue($reviews->is_coordinator_marks_locked($this->review_id));
        $this->assertFalse($reviews->is_marking_active($this->review_id));

        $second = $service->lock_review_marks($this->session_id, $this->review_id, 42);
        $this->assertIsArray($second);
        $this->assertTrue($second['coordinator_marks_locked']);
    }

    public function test_lock_review_marks_blocked_until_all_panels_frozen(): void
    {
        $service = $this->markService();
        $result = $service->lock_review_marks($this->session_id, $this->review_id, 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('panels_not_all_frozen', $result->get_error_code());
        $data = $result->get_error_data();
        $this->assertIsArray($data['unfrozen_panels'] ?? null);
        $this->assertNotEmpty($data['unfrozen_panels']);
    }

    public function test_unlock_review_marks_restores_marking_active(): void
    {
        $service = $this->markService();
        $panel_id = $this->freezePanelMarks($service);
        (new PanelFreezeRepository($this->wpdb))->freeze($this->review_id, $panel_id, 42);
        $service->lock_review_marks($this->session_id, $this->review_id, 42);

        $reviews = new ReviewRepository($this->wpdb);
        $this->assertFalse($reviews->is_marking_active($this->review_id));

        $unlock = $service->unlock_review_marks($this->session_id, $this->review_id, 99);
        $this->assertIsArray($unlock);
        $this->assertFalse($unlock['coordinator_marks_locked']);
        $this->assertTrue($unlock['marking_active']);
        $this->assertFalse($reviews->is_coordinator_marks_locked($this->review_id));
        $this->assertTrue($reviews->is_marking_active($this->review_id));
    }

    public function test_unlock_review_marks_is_idempotent(): void
    {
        $service = $this->markService();
        $first = $service->unlock_review_marks($this->session_id, $this->review_id, 42);
        $this->assertIsArray($first);
        $this->assertFalse($first['coordinator_marks_locked']);

        $second = $service->unlock_review_marks($this->session_id, $this->review_id, 42);
        $this->assertIsArray($second);
        $this->assertFalse($second['coordinator_marks_locked']);
    }

    public function test_correct_attendance_blocked_when_coordinator_locked(): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        $reviews->set_coordinator_marks_locked($this->review_id, true);

        $service = $this->markService();
        $result = $service->correct_attendance_by_coordinator(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            'Student did not attend the oral review.',
            900
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('coordinator_marks_locked', $result->get_error_code());
    }
}
