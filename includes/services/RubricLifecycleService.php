<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\ReviewRepository;

final class RubricLifecycleService
{
    public const MARK_KEEP_FLAG = 'keep_flag';

    public const MARK_CLEAR = 'clear';

    private ReviewRepository $reviews;

    private AuditService $audit;

    public function __construct(?ReviewRepository $reviews = null, ?AuditService $audit = null)
    {
        $this->reviews = $reviews ?? new ReviewRepository();
        $this->audit = $audit ?? new AuditService();
    }

    public function is_marking_allowed(int $review_id): bool
    {
        $review = $this->reviews->find_by_id($review_id);

        return $review !== null
            && (string) ($review['status'] ?? '') === ReviewRepository::STATUS_CONFIRMED;
    }

    /**
     * @return array{confirmed: bool, marks_flagged?: int, marks_cleared?: int}
     */
    public function confirm(int $review_id, ?string $mark_action = null): array
    {
        $review = $this->reviews->find_by_id($review_id);
        if ($review === null) {
            throw new \InvalidArgumentException('Review not found.');
        }

        $status = (string) ($review['status'] ?? '');
        if ($status === ReviewRepository::STATUS_CONFIRMED) {
            throw new \InvalidArgumentException('Review is already confirmed.');
        }

        if (!in_array($status, [ReviewRepository::STATUS_DRAFT, ReviewRepository::STATUS_UNLOCKED], true)) {
            throw new \InvalidArgumentException('Review cannot be confirmed from its current state.');
        }

        $this->assert_valid_criteria($review_id);

        $result = ['confirmed' => true];

        if ($status === ReviewRepository::STATUS_UNLOCKED) {
            $mark_count = $this->reviews->count_marks_for_review($review_id);
            if ($mark_count > 0) {
                $mark_action = $this->normalize_mark_action($mark_action);
                if ($mark_action === self::MARK_KEEP_FLAG) {
                    $result['marks_flagged'] = $this->reviews->flag_marks_for_review($review_id);
                } else {
                    $result['marks_cleared'] = $this->reviews->clear_marks_for_review($review_id);
                }
            }
        }

        $this->reviews->set_status($review_id, ReviewRepository::STATUS_CONFIRMED);

        $session_id = (int) ($review['session_id'] ?? 0);
        $this->audit->log(
            'rubric_confirmed',
            'session',
            $session_id,
            $status,
            json_encode(
                array_merge(['status' => ReviewRepository::STATUS_CONFIRMED], $result),
                JSON_THROW_ON_ERROR
            )
        );

        return $result;
    }

    public function unlock(int $review_id): void
    {
        $review = $this->reviews->find_by_id($review_id);
        if ($review === null) {
            throw new \InvalidArgumentException('Review not found.');
        }

        if ((string) ($review['status'] ?? '') !== ReviewRepository::STATUS_CONFIRMED) {
            throw new \InvalidArgumentException('Only confirmed reviews can be unlocked.');
        }

        $this->reviews->set_status($review_id, ReviewRepository::STATUS_UNLOCKED);

        $session_id = (int) ($review['session_id'] ?? 0);
        $this->audit->log(
            'rubric_unlocked',
            'session',
            $session_id,
            ReviewRepository::STATUS_CONFIRMED,
            ReviewRepository::STATUS_UNLOCKED
        );
    }

    /**
     * @param list<array{label?: string, max_marks?: float|int|string}> $criteria
     */
    public static function assert_valid_criteria_rows(array $criteria): void
    {
        $valid_count = 0;

        foreach ($criteria as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $max_marks = (float) ($row['max_marks'] ?? 0);
            if ($max_marks <= 0) {
                throw new \InvalidArgumentException('Each criterion needs max marks greater than zero.');
            }

            ++$valid_count;
        }

        if ($valid_count === 0) {
            throw new \InvalidArgumentException('At least one rubric criterion is required.');
        }
    }

    private function assert_valid_criteria(int $review_id): void
    {
        self::assert_valid_criteria_rows($this->reviews->list_criteria($review_id));
    }

    private function normalize_mark_action(?string $mark_action): string
    {
        $mark_action = strtolower(trim((string) $mark_action));
        if (!in_array($mark_action, [self::MARK_KEEP_FLAG, self::MARK_CLEAR], true)) {
            throw new \InvalidArgumentException('mark_action must be keep_flag or clear when re-confirming with existing marks.');
        }

        return $mark_action;
    }
}
