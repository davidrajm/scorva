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
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Repositories\UnfreezeRequestRepository;
use ProjectReviews\Rest_Panel_Reports;
use ProjectReviews\Rest_Reviewer_Unfreeze_Requests;
use ProjectReviews\Services\MarkService;
use ProjectReviews\Services\PanelHeadService;
use ProjectReviews\Services\PanelReportPdfContextBuilder;
use ProjectReviews\Services\PanelReportPdfService;
use ProjectReviews\Services\PanelReportService;
use ProjectReviews\Services\SessionPanelReportSettings;
use WP_REST_Request;

final class PanelHeadTest extends TestCase
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
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        if (!defined('PR_CAP_ENTER_MARKS')) {
            require_once dirname(__DIR__) . '/includes/capabilities.php';
        }

        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-panel-reports.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reviewer-unfreeze-requests.php';

        Install::ensure_schema_patches();

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $students = new StudentRepository($this->wpdb);
        $assignments = new ReviewAssignmentRepository($this->wpdb);

        $this->session_id = $sessions->create([
            'title' => 'Panel head project',
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

        $this->student_id = $students->insert(['reg_no' => 'PH01', 'name' => 'Student']);
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

        $head_service = new PanelHeadService($panels);
        $set_head = $head_service->set_session_panel_head($head_id, true);
        $this->assertNotInstanceOf(\WP_Error::class, $set_head);

        $assignments->seed_from_session_defaults($this->review_id, $this->session_id);

        Rest_Bootstrap::register_routes();
    }

    public function test_set_panel_head_clears_other_heads_on_panel(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $reviewers = $panels->list_reviewers($this->panel_id);
        $peer = null;
        foreach ($reviewers as $row) {
            if ((int) ($row['user_id'] ?? 0) === $this->peer_user_id) {
                $peer = $row;
                break;
            }
        }
        $this->assertIsArray($peer);

        $service = new PanelHeadService($panels);
        $set_peer = $service->set_session_panel_head((int) $peer['id'], true);
        $this->assertNotInstanceOf(\WP_Error::class, $set_peer);

        $head = $panels->find_panel_head($this->panel_id);
        $this->assertSame((int) $peer['id'], (int) ($head['id'] ?? 0));
    }

    public function test_panel_head_requires_linked_account(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $unlinked_id = $panels->add_reviewer($this->panel_id, [
            'name' => 'Unlinked',
            'email' => 'unlinked@example.com',
        ]);

        $result = (new PanelHeadService(new PanelRepository($this->wpdb)))->set_session_panel_head($unlinked_id, true);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('panel_head_requires_account', $result->get_error_code());
    }

    public function test_seed_copies_panel_head_to_review_assignments(): void
    {
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $this->assertTrue(
            $assignments->is_panel_head_for_user($this->review_id, $this->panel_id, $this->head_user_id)
        );
        $this->assertFalse(
            $assignments->is_panel_head_for_user($this->review_id, $this->panel_id, $this->peer_user_id)
        );
    }

    public function test_panel_report_denies_non_head(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->peer_user_id;

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $this->review_id);
        $request->set_param('panel_id', $this->panel_id);

        $result = Rest_Panel_Reports::get_report($request);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_panel_coordinator', $result->get_error_code());
    }

    public function test_panel_head_grants_reviewer_unfreeze(): void
    {
        $this->submit_all_marks_for_panel();

        $repo = new UnfreezeRequestRepository($this->wpdb);
        $pending = $repo->create_pending(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->peer_user_id,
            'Wrong total score'
        );

        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        RestTestFixtures::set_valid_rest_nonce('valid-nonce');
        $GLOBALS['pr_test_current_user_id'] = $this->head_user_id;

        $request = new WP_REST_Request();
        $request->set_param('id', (int) ($pending['id'] ?? 0));

        $result = Rest_Reviewer_Unfreeze_Requests::grant_request($request);
        $this->assertIsArray($result);
        $this->assertTrue($result['granted']);
        $this->assertGreaterThan(0, $result['marks_reverted']);
    }

    public function test_panel_freeze_blocks_mark_save(): void
    {
        $this->submit_all_marks_for_panel();

        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        $GLOBALS['pr_test_current_user_id'] = $this->head_user_id;

        $request = new WP_REST_Request();
        $request->set_param('session_id', $this->session_id);
        $request->set_param('review_id', $this->review_id);
        $request->set_param('panel_id', $this->panel_id);

        $freeze = Rest_Panel_Reports::freeze_panel($request);
        $this->assertIsArray($freeze);
        $this->assertTrue($freeze['frozen']);

        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );

        $save = $service->save_marks(
            $this->session_id,
            $this->review_id,
            $this->student_id,
            $this->peer_user_id,
            [['criterion_id' => $this->criterion_ids[0], 'score' => 8]],
            MarkRepository::STATUS_DRAFT,
            ReviewAssignmentRepository::ATTENDANCE_PRESENT
        );
        $this->assertInstanceOf(\WP_Error::class, $save);
        $this->assertSame('panel_scores_frozen', $save->get_error_code());
    }

    public function test_generate_pdf_requires_frozen_settings(): void
    {
        $this->submit_all_marks_for_panel();

        $blocked = (new PanelReportService())->generate_pdf(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        );
        $this->assertInstanceOf(\WP_Error::class, $blocked);
        $this->assertSame('panel_report_settings_not_frozen', $blocked->get_error_code());

        SessionPanelReportSettings::freeze_settings($this->session_id);

        $pdf = (new PanelReportService())->generate_pdf(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        );
        if ($pdf instanceof \WP_Error && $pdf->get_error_code() === 'pdf_unavailable') {
            $this->markTestSkipped($pdf->get_error_message());
        }
        $this->assertIsArray($pdf);
    }

    public function test_pdf_render_returns_pdf_magic_bytes(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('dompdf not installed');
        }

        $this->submit_all_marks_for_panel();
        SessionPanelReportSettings::freeze_settings($this->session_id);

        $report = (new PanelReportService())->get_report(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        );
        $this->assertIsArray($report);

        $pdf = (new PanelReportPdfService())->render($report);
        if ($pdf instanceof \WP_Error) {
            $this->markTestSkipped($pdf->get_error_message());
        }

        $this->assertStringStartsWith('%PDF', $pdf['pdf']);
    }

    public function test_pdf_html_uses_institutional_template(): void
    {
        $this->submit_all_marks_for_panel();

        $report = (new PanelReportService())->get_report(
            $this->session_id,
            $this->review_id,
            $this->panel_id,
            $this->head_user_id
        );
        $this->assertIsArray($report);

        $context = (new PanelReportPdfContextBuilder())->build($report);
        $html = (new PanelReportPdfService())->build_html($context);

        $this->assertStringContainsString('Review Report', $html);
        $this->assertStringContainsString('>R1</th>', $html);
        $this->assertStringContainsString('border: 1.00pt solid #000000', $html);
        $this->assertStringContainsString('>P<', $html);
        $this->assertStringContainsString('Final Marks', $html);
    }

    private function submit_all_marks_for_panel(): void
    {
        $service = new MarkService(
            new SessionRepository($this->wpdb),
            new ReviewRepository($this->wpdb),
            new ReviewAssignmentRepository($this->wpdb),
            new MarkRepository($this->wpdb)
        );

        foreach ([$this->head_user_id, $this->peer_user_id] as $reviewer_user_id) {
            $service->save_marks(
                $this->session_id,
                $this->review_id,
                $this->student_id,
                $reviewer_user_id,
                [['criterion_id' => $this->criterion_ids[0], 'score' => 7]],
                MarkRepository::STATUS_DRAFT,
                ReviewAssignmentRepository::ATTENDANCE_PRESENT
            );
            $freeze = $service->freeze_review_marks(
                $this->session_id,
                $this->review_id,
                $this->panel_id,
                $reviewer_user_id
            );
            $this->assertIsArray($freeze, (string) ($freeze instanceof \WP_Error ? $freeze->get_error_message() : ''));
        }

        $panels = new PanelRepository($this->wpdb);
        $head = $panels->find_panel_head($this->panel_id);
        $this->assertNotNull($head);
        $head_reviewer_id = (int) ($head['id'] ?? 0);
        (new PanelHeadService($panels))->set_session_panel_head($head_reviewer_id, true);

        $assignments = new ReviewAssignmentRepository($this->wpdb);
        $assignments->sync_panel_reviewers_from_session($this->review_id, $this->session_id);
    }
}
