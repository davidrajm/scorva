<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\PanelFreezeRepository;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\PanelUnfreezeRequestRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Repositories\UnfreezeRequestRepository;

final class SessionDeleteService
{
    private object $wpdb;

    private SessionRepository $sessions;

    private ReviewRepository $reviews;

    private MarkRepository $marks;

    private PanelRepository $panels;

    private AuditService $audit;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $wpdb = $GLOBALS['wpdb'] ?? null;
        }

        if ($wpdb === null) {
            throw new \RuntimeException('SessionDeleteService requires $wpdb.');
        }

        $this->wpdb = $wpdb;
        $this->sessions = new SessionRepository($wpdb);
        $this->reviews = new ReviewRepository($wpdb);
        $this->marks = new MarkRepository($wpdb);
        $this->panels = new PanelRepository($wpdb);
        $this->audit = new AuditService($wpdb);
    }

    /**
     * @return array{ok: true, deleted: true}|array{ok: false, error: string}
     */
    public function delete(int $session_id, ?int $actor_user_id = null): array
    {
        $session = $this->sessions->find_by_id($session_id);
        if ($session === null) {
            return ['ok' => false, 'error' => 'session_not_found'];
        }

        if ($actor_user_id === null) {
            $actor_user_id = function_exists('get_current_user_id')
                ? (int) get_current_user_id()
                : 0;
        }

        $title = trim((string) ($session['title'] ?? ''));
        $reviewer_user_ids = $this->session_reviewer_user_ids($session_id);

        $this->audit->delete_scoped_rows_for_session($session_id);

        foreach ($this->reviews->list_for_session($session_id) as $review) {
            $review_id = (int) ($review['id'] ?? 0);
            if ($review_id <= 0) {
                continue;
            }

            $this->marks->delete_all_for_review($review_id);
            (new PanelFreezeRepository($this->wpdb))->delete_all_for_review($review_id);
            (new PanelUnfreezeRequestRepository($this->wpdb))->delete_all_for_review($review_id);
            (new UnfreezeRequestRepository($this->wpdb))->delete_all_for_review($review_id);
            $this->reviews->delete($review_id);
        }

        foreach ($this->panels->list_by_session($session_id) as $panel) {
            $panel_id = (int) ($panel['id'] ?? 0);
            if ($panel_id > 0) {
                $this->panels->delete($panel_id);
            }
        }

        $this->wpdb->delete(
            $this->wpdb->prefix . 'pr_session_reviewers',
            ['session_id' => $session_id],
            ['%d']
        );

        SessionPanelReportSettings::delete($session_id);

        $this->audit->log(
            'session_deleted',
            'session',
            $session_id,
            $title !== '' ? $title : null,
            null,
            $actor_user_id
        );

        $this->sessions->delete($session_id);

        foreach ($reviewer_user_ids as $user_id) {
            SessionReviewerAccountMeta::clear_account_disabled_meta_if_unused(
                $this->wpdb,
                $user_id
            );
        }

        return ['ok' => true, 'deleted' => true];
    }

    /**
     * @return list<int>
     */
    private function session_reviewer_user_ids(int $session_id): array
    {
        $table = $this->wpdb->prefix . 'pr_session_reviewers';
        $sql = $this->wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table} WHERE session_id = %d",
            $session_id
        );
        $ids = $this->wpdb->get_col($sql);
        if (!is_array($ids)) {
            return [];
        }

        $user_ids = [];
        foreach ($ids as $id) {
            $user_id = (int) $id;
            if ($user_id > 0) {
                $user_ids[] = $user_id;
            }
        }

        return $user_ids;
    }
}
