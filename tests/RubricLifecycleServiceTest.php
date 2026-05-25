<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Services\RubricLifecycleService;

final class RubricLifecycleServiceTest extends TestCase
{
    private FakeWpdb $wpdb;

    private SessionRepository $sessions;

    private ReviewRepository $reviews;

    private RubricLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->sessions = new SessionRepository($this->wpdb);
        $this->reviews = new ReviewRepository($this->wpdb);
        $this->lifecycle = new RubricLifecycleService($this->reviews);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_confirm_draft_review_with_valid_criteria(): void
    {
        $session_id = $this->sessions->create(['title' => 'Rubric lifecycle']);
        $review_id = $this->reviews->create($session_id, ['label' => 'Review 1']);
        $this->reviews->replace_criteria($review_id, [
            ['label' => 'Technical depth', 'max_marks' => 10, 'weight' => 1],
        ]);

        $result = $this->lifecycle->confirm($review_id);

        $this->assertTrue($result['confirmed']);
        $review = $this->reviews->find_by_id($review_id);
        $this->assertSame(ReviewRepository::STATUS_CONFIRMED, $review['status']);
        $this->assertTrue($this->lifecycle->is_marking_allowed($review_id));
    }

    public function test_confirm_rejects_empty_criteria(): void
    {
        $session_id = $this->sessions->create(['title' => 'Empty rubric']);
        $review_id = $this->reviews->create($session_id, ['label' => 'Review 1']);

        $this->expectException(\InvalidArgumentException::class);
        $this->lifecycle->confirm($review_id);
    }

    public function test_unlock_moves_confirmed_to_unlocked(): void
    {
        $session_id = $this->sessions->create(['title' => 'Unlock test']);
        $review_id = $this->seed_confirmed_review($session_id);

        $this->lifecycle->unlock($review_id);

        $review = $this->reviews->find_by_id($review_id);
        $this->assertSame(ReviewRepository::STATUS_UNLOCKED, $review['status']);
        $this->assertFalse($this->lifecycle->is_marking_allowed($review_id));
    }

    public function test_reconfirm_keep_flag_sets_flagged_on_marks(): void
    {
        $session_id = $this->sessions->create(['title' => 'Keep flag']);
        $review_id = $this->seed_confirmed_review($session_id);
        $this->seed_mark($session_id, $review_id, flagged: 0);

        $this->lifecycle->unlock($review_id);
        $this->reviews->replace_criteria($review_id, [
            ['label' => 'Technical depth', 'max_marks' => 15, 'weight' => 1],
        ]);

        $result = $this->lifecycle->confirm($review_id, 'keep_flag');

        $this->assertTrue($result['confirmed']);
        $this->assertSame(1, $result['marks_flagged']);
        $marks = $this->reviews->list_marks_for_review($review_id);
        $this->assertCount(1, $marks);
        $this->assertSame(1, (int) $marks[0]['flagged']);
    }

    public function test_reconfirm_clear_removes_marks(): void
    {
        $session_id = $this->sessions->create(['title' => 'Clear marks']);
        $review_id = $this->seed_confirmed_review($session_id);
        $this->seed_mark($session_id, $review_id);

        $this->lifecycle->unlock($review_id);
        $this->reviews->replace_criteria($review_id, [
            ['label' => 'Presentation', 'max_marks' => 10, 'weight' => 1],
        ]);

        $result = $this->lifecycle->confirm($review_id, 'clear');

        $this->assertTrue($result['confirmed']);
        $this->assertSame(1, $result['marks_cleared']);
        $this->assertSame([], $this->reviews->list_marks_for_review($review_id));
    }

    public function test_reconfirm_requires_mark_action_when_unlocked_with_marks(): void
    {
        $session_id = $this->sessions->create(['title' => 'Needs action']);
        $review_id = $this->seed_confirmed_review($session_id);
        $this->seed_mark($session_id, $review_id);

        $this->lifecycle->unlock($review_id);

        $this->expectException(\InvalidArgumentException::class);
        $this->lifecycle->confirm($review_id);
    }

    private function seed_confirmed_review(int $session_id): int
    {
        $review_id = $this->reviews->create($session_id, ['label' => 'Review 1']);
        $this->reviews->replace_criteria($review_id, [
            ['label' => 'Criterion A', 'max_marks' => 10, 'weight' => 1],
        ]);
        $this->lifecycle->confirm($review_id);

        return $review_id;
    }

    private function seed_mark(int $session_id, int $review_id, int $flagged = 0): void
    {
        $criteria = $this->reviews->list_criteria($review_id);
        $criterion_id = (int) ($criteria[0]['id'] ?? 0);

        $this->wpdb->insert(
            $this->wpdb->prefix . 'pr_marks',
            [
                'session_id' => $session_id,
                'review_id' => $review_id,
                'student_id' => 1,
                'reviewer_user_id' => 99,
                'criterion_id' => $criterion_id,
                'score' => 7,
                'flagged' => $flagged,
                'status' => 'submitted',
            ]
        );
    }
}
