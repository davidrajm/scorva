<?php

declare(strict_types=1);

namespace ProjectReviews\Tests\Support;

use ProjectReviews\Install;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\ReviewAssignmentRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\StudentRepository;
use ProjectReviews\Services\ReviewerProvisionService;
use ProjectReviews\Services\SessionCloseService;
use ProjectReviews\Tests\FakeWpdb;
use ProjectReviews\Tests\RestTestFixtures;

/**
 * Seeds a fully configured project on FakeWpdb (registry → project → panels → reviewers → rubrics → assignments).
 */
final class ScenarioBuilder
{
    private FakeWpdb $wpdb;

    /** @var list<int> */
    private array $student_ids = [];

    private int $session_id = 0;

    private int $panel_id = 0;

    /** @var list<int> */
    private array $review_ids = [];

    /** @var list<int> */
    private array $reviewer_user_ids = [];

    /** @var list<int> */
    private array $criterion_ids = [];

    private bool $marks_submitted = false;

    private bool $session_closed = false;

    private bool $flagged_marks = false;

    public function __construct(FakeWpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public static function fresh(FakeWpdb $wpdb): self
    {
        global $pr_test_users, $pr_test_user_meta, $pr_test_sent_mail;
        $pr_test_users = [];
        $pr_test_user_meta = [];
        $pr_test_sent_mail = [];
        RestTestFixtures::reset();
        Install::ensure_schema_patches();

        return new self($wpdb);
    }

    public function with_students(int $count = 2): self
    {
        $students = new StudentRepository($this->wpdb);
        $this->student_ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $this->student_ids[] = $students->insert([
                'reg_no' => 'JRN' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'name' => 'Journey Student ' . $i,
            ]);
        }

        return $this;
    }

    public function with_active_project(string $title = 'Journey Project'): self
    {
        $sessions = new SessionRepository($this->wpdb);
        $this->session_id = $sessions->create([
            'title' => $title,
            'status' => SessionRepository::STATUS_ACTIVE,
        ]);

        return $this;
    }

    public function with_panel(string $name = 'Panel A'): self
    {
        if ($this->session_id <= 0) {
            throw new \RuntimeException('Call with_active_project() before with_panel().');
        }

        $panels = new PanelRepository($this->wpdb);
        $this->panel_id = $panels->create($this->session_id, $name);

        $sessions = new SessionRepository($this->wpdb);
        foreach ($this->student_ids as $student_id) {
            $sessions->enrol_student($this->session_id, $student_id, $this->panel_id);
        }

        return $this;
    }

    public function with_reviewers(int $count = 2): self
    {
        $panels = new PanelRepository($this->wpdb);
        $this->reviewer_user_ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $user_id = $this->create_fixture_reviewer_user($i);
            $this->reviewer_user_ids[] = $user_id;
            $panels->add_reviewer($this->panel_id, [
                'name' => 'Reviewer ' . $i,
                'email' => 'reviewer' . $i . '@journey.test',
                'user_id' => $user_id,
            ]);
        }

        return $this;
    }

    public function with_reviews(int $count = 2): self
    {
        $reviews = new ReviewRepository($this->wpdb);
        $this->review_ids = [];
        $this->criterion_ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $review_id = $reviews->create($this->session_id, [
                'label' => 'Review ' . $i,
                'status' => ReviewRepository::STATUS_DRAFT,
                'sort_order' => $i - 1,
            ]);
            $this->review_ids[] = $review_id;
            $criteria = $reviews->replace_criteria($review_id, [
                ['label' => 'Criterion A', 'max_marks' => 10],
                ['label' => 'Criterion B', 'max_marks' => 10],
            ]);
            foreach ($criteria as $row) {
                $this->criterion_ids[] = (int) $row['id'];
            }
            $this->wpdb->insert($this->wpdb->prefix . 'pr_review_weights', [
                'session_id' => $this->session_id,
                'review_id' => $review_id,
                'weight' => 1.0,
            ]);
        }

        return $this;
    }

    public function with_confirmed_rubrics(): self
    {
        $reviews = new ReviewRepository($this->wpdb);
        foreach ($this->review_ids as $review_id) {
            $reviews->set_status($review_id, ReviewRepository::STATUS_CONFIRMED);
        }

        return $this;
    }

    public function with_assignments(): self
    {
        $assignments = new ReviewAssignmentRepository($this->wpdb);
        foreach ($this->review_ids as $review_id) {
            $assignments->seed_from_session_defaults($review_id, $this->session_id);
        }

        return $this;
    }

    public function with_marking_active(): self
    {
        $reviews = new ReviewRepository($this->wpdb);
        foreach ($this->review_ids as $review_id) {
            $reviews->set_marking_active($review_id, true);
        }

        return $this;
    }

    public function with_marks_submitted(): self
    {
        $this->marks_submitted = true;

        return $this;
    }

    public function with_session_closed(): self
    {
        $this->session_closed = true;

        return $this;
    }

    public function with_flagged_marks(): self
    {
        $this->flagged_marks = true;

        return $this;
    }

    /**
     * Standard happy-path chain.
     */
    public function build_configured_project(): self
    {
        return $this
            ->with_students(2)
            ->with_active_project()
            ->with_panel()
            ->with_reviewers(2)
            ->with_reviews(2)
            ->with_confirmed_rubrics()
            ->with_assignments()
            ->with_marking_active();
    }

    /**
     * @return array{
     *     session_id: int,
     *     panel_id: int,
     *     student_ids: list<int>,
     *     review_ids: list<int>,
     *     reviewer_user_ids: list<int>,
     *     criterion_ids: list<int>
     * }
     */
    public function build(): array
    {
        if ($this->marks_submitted) {
            $this->seed_submitted_marks();
        }

        if ($this->session_closed) {
            (new SessionCloseService($this->wpdb))->close($this->session_id, 1);
        }

        return [
            'session_id' => $this->session_id,
            'panel_id' => $this->panel_id,
            'student_ids' => $this->student_ids,
            'review_ids' => $this->review_ids,
            'reviewer_user_ids' => $this->reviewer_user_ids,
            'criterion_ids' => $this->criterion_ids,
        ];
    }

    public function session_id(): int
    {
        return $this->session_id;
    }

    public function panel_id(): int
    {
        return $this->panel_id;
    }

    public function first_review_id(): int
    {
        return $this->review_ids[0] ?? 0;
    }

    public function first_student_id(): int
    {
        return $this->student_ids[0] ?? 0;
    }

    public function first_reviewer_user_id(): int
    {
        return $this->reviewer_user_ids[0] ?? 0;
    }

    public function first_criterion_id(): int
    {
        return $this->criterion_ids[0] ?? 0;
    }

    private function seed_submitted_marks(): void
    {
        $marks = new MarkRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);

        foreach ($this->review_ids as $review_id) {
            $criteria = $reviews->list_criteria($review_id);
            foreach ($this->student_ids as $student_id) {
                foreach ($this->reviewer_user_ids as $reviewer_user_id) {
                    foreach ($criteria as $criterion) {
                        $criterion_id = (int) ($criterion['id'] ?? 0);
                        $flagged = $this->flagged_marks ? 1 : 0;
                        $marks->upsert(
                            $this->session_id,
                            $review_id,
                            $student_id,
                            $reviewer_user_id,
                            $criterion_id,
                            7.5,
                            MarkRepository::STATUS_SUBMITTED,
                            (bool) $flagged
                        );
                    }
                }
            }
        }
    }

    private function create_fixture_reviewer_user(int $index): int
    {
        $user_id = wp_create_user('journey_reviewer_' . $index, 'pass', 'reviewer' . $index . '@journey.test');
        if ($user_id instanceof \WP_Error) {
            throw new \RuntimeException($user_id->get_error_message());
        }
        update_user_meta((int) $user_id, 'pr_test_fixture', '1');
        RestTestFixtures::track_created_user_id((int) $user_id);

        return (int) $user_id;
    }
}
