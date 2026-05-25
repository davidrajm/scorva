<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Install;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Services\MarkService;
use ProjectReviews\Services\PanelHeadService;
use ProjectReviews\Services\PanelReportService;
use ProjectReviews\Services\ReportsViewService;

final class PanelReportServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    private int $session_id;

    private int $review_id;

    private int $panel_id;

    private int $head_user_id = 901;

    private int $peer_user_id = 902;

    private int $student_id;

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
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $this->session_id = $sessions->create([
            'title' => 'Panel freeze project',
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);
        $this->panel_id = $panels->create($this->session_id, 'Panel A');
        $head_id = $panels->add_reviewer($this->panel_id, [
            'name' => 'Head',
            'email' => 'head@example.com',
            'user_id' => $this->head_user_id,
        ]);
        $panels->add_reviewer($this->panel_id, [
            'name' => 'Peer',
            'email' => 'peer@example.com',
            'user_id' => $this->peer_user_id,
        ]);

        $this->student_id = $students->insert(['reg_no' => 'PF01', 'name' => 'Student']);
        $sessions->enrol_student($this->session_id, $this->student_id, $this->panel_id);

        $this->review_id = $reviews->create($this->session_id, [
            'label' => 'Review 1',
            'status' => ReviewRepository::STATUS_CONFIRMED,
        ]);
        $criteria = $reviews->replace_criteria($this->review_id, [
            ['label' => 'Quality', 'max_marks' => 10],
        ]);
        $this->criterion_ids = array_map(static fn (array $row): int => (int) $row['id'], $criteria);
        $reviews->set_marking_active($this->review_id, true);

        $set_head = (new PanelHeadService($panels))->set_session_panel_head($head_id, true);
        $this->assertNotInstanceOf(\WP_Error::class, $set_head);

        $assignments->seed_from_session_defaults($this->review_id, $this->session_id);
    }

    public function test_freeze_panel_rejects_when_reviewer_has_not_frozen_personal_scores(): void
    {
        $mark_service = $this->mark_service();

        $mark_service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->head_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => 7]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($mark_service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        ));

        $mark_service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->peer_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => 8]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $result = $this->panel_report_service()->freeze_panel(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('panel_freeze_reviewers_not_frozen', $result->get_error_code());
        $this->assertStringContainsString('Peer', $result->message);
        $this->assertStringContainsString('freeze their personal scores', $result->message);
    }

    public function test_panel_report_includes_student_mark_status(): void
    {
        $mark_service = $this->mark_service();

        $mark_service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->head_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => 7]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($mark_service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        ));

        $mark_service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->peer_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => 8]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );

        $report = $this->reports_view_service()->scores_matrix_for_panel(
            $this->session_id,
            $this->review_id,
            $this->panel_id
        );

        $this->assertIsArray($report);
        $this->assertSame('in_progress', $report['students'][0]['mark_status'] ?? '');

        $this->assertIsArray($mark_service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->peer_user_id
        ));

        $report_frozen = $this->reports_view_service()->scores_matrix_for_panel(
            $this->session_id,
            $this->review_id,
            $this->panel_id
        );

        $this->assertIsArray($report_frozen);
        $this->assertSame('frozen', $report_frozen['students'][0]['mark_status'] ?? '');
    }

    public function test_absent_student_not_frozen_when_only_one_reviewer_frozen(): void
    {
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $assignments->set_attendance_status(
            $this->review_id,
            $this->student_id,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );

        $mark_service = $this->mark_service();

        $mark_service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->head_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => null]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_ABSENT
        );
        $this->assertIsArray($mark_service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        ));

        $report = $this->reports_view_service()->scores_matrix_for_panel(
            $this->session_id,
            $this->review_id,
            $this->panel_id
        );

        $this->assertIsArray($report);
        $this->assertSame('in_progress', $report['students'][0]['mark_status'] ?? '');
        $this->assertSame(
            ReviewAssignmentRepository::ATTENDANCE_ABSENT,
            $report['students'][0]['attendance_status'] ?? ''
        );
    }

    public function test_freeze_panel_rejects_when_reviewer_has_incomplete_marks(): void
    {
        $mark_service = $this->mark_service();

        $mark_service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->head_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => 7]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertIsArray($mark_service->freeze_review_marks(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        ));

        $result = $this->panel_report_service()->freeze_panel(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('panel_freeze_incomplete_marks', $result->get_error_code());
        $this->assertStringContainsString('Peer', $result->message);
        $this->assertStringContainsString('score on every criterion', $result->message);
    }

    private function mark_service(): MarkService
    {
        return new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );
    }

    private function panel_report_service(): PanelReportService
    {
        return new PanelReportService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new PanelRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );
    }

    private function reports_view_service(): ReportsViewService
    {
        return new ReportsViewService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new StudentRepository($this->wpdb),
            new MarkRepository($this->wpdb),
            null,
            new PanelRepository($this->wpdb)
        );
    }
}
