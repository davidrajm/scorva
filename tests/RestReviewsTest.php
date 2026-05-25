<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\MarkRepository;
use ProjectReviews\Repositories\ReviewRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Reviews;
use WP_Error;
use WP_REST_Request;

final class RestReviewsTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/RestAuthTest.php';
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        if (!defined('PR_CAP_MANAGE_SESSIONS')) {
            require_once dirname(__DIR__) . '/includes/capabilities.php';
        }
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reviews.php';

        Rest_Bootstrap::register_routes();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_create_review_with_criteria_and_confirm(): void
    {
        RestTestFixtures::login_with_caps([PR_CAP_MANAGE_SESSIONS, PR_CAP_CONFIRM_RUBRICS]);
        RestTestFixtures::set_valid_rest_nonce('reviews');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'REST rubric']);

        $create = new WP_REST_Request();
        $create->set_param('session_id', $session_id);
        $create->set_header('X-WP-Nonce', 'reviews');
        $create->set_json_params([
            'label' => 'Review 1',
            'criteria' => [
                ['label' => 'Depth', 'max_marks' => 10, 'weight' => 1],
            ],
        ]);

        $review = Rest_Reviews::create_review($create);
        $this->assertIsArray($review);
        $this->assertSame('draft', $review['status']);
        $this->assertCount(1, $review['criteria']);

        $confirm = new WP_REST_Request();
        $confirm->set_params([
            'session_id' => $session_id,
            'review_id' => $review['id'],
        ]);
        $confirm->set_header('X-WP-Nonce', 'reviews');
        $confirmed = Rest_Reviews::confirm_review($confirm);

        $this->assertIsArray($confirmed);
        $this->assertSame('confirmed', $confirmed['review']['status']);
        $this->assertTrue($confirmed['review']['marking_allowed']);
    }

    public function test_save_weights_defaults_and_persist(): void
    {
        RestTestFixtures::login_with_caps([PR_CAP_MANAGE_SESSIONS, PR_CAP_CONFIGURE_WEIGHTS]);
        RestTestFixtures::set_valid_rest_nonce('weights');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Weights']);
        $review_id = (new ReviewRepository($this->wpdb))->create($session_id, ['label' => 'Review 1']);

        $save = new WP_REST_Request();
        $save->set_param('session_id', $session_id);
        $save->set_header('X-WP-Nonce', 'weights');
        $save->set_json_params([
            'review_weights' => [
                ['review_id' => $review_id, 'weight' => 2],
            ],
            'reviewer_weights' => [
                ['review_id' => $review_id, 'reviewer_user_id' => 42, 'weight' => 1.5],
            ],
        ]);

        $result = Rest_Reviews::save_weights($save);
        $this->assertIsArray($result);
        $this->assertSame(2.0, $result['review_weights'][0]['weight']);
        $this->assertSame(1.5, $result['reviewer_weights'][0]['weight']);
    }

    public function test_list_marks_includes_flagged_boolean(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);

        $sessions = new SessionRepository($this->wpdb);
        $reviews = new ReviewRepository($this->wpdb);
        $session_id = $sessions->create(['title' => 'Flagged marks']);
        $review_id = $reviews->create($session_id, ['label' => 'Review 1']);
        $saved = $reviews->replace_criteria($review_id, [
            ['label' => 'A', 'max_marks' => 10, 'weight' => 1],
        ]);

        $this->wpdb->insert(
            $this->wpdb->prefix . 'pr_marks',
            [
                'session_id' => $session_id,
                'review_id' => $review_id,
                'student_id' => 1,
                'reviewer_user_id' => 9,
                'criterion_id' => (int) $saved[0]['id'],
                'score' => 5,
                'flagged' => 1,
                'status' => 'submitted',
            ]
        );

        $request = new WP_REST_Request();
        $request->set_param('session_id', $session_id);
        $request->set_param('review_id', $review_id);

        $result = Rest_Reviews::list_marks($request);
        $this->assertTrue($result['marks'][0]['flagged']);
    }

    public function test_confirm_without_cap_is_forbidden(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ENTER_MARKS);
        RestTestFixtures::set_valid_rest_nonce('confirm-deny');

        $route = $this->find_route_callback('/sessions/(?P<session_id>\\d+)/reviews/(?P<review_id>\\d+)/confirm', 'POST');
        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'confirm-deny');
        $result = $route['permission_callback']($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_forbidden', $result->get_error_code());
    }

    public function test_confirm_allowed_with_manage_sessions_cap(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('confirm-allow');

        $route = $this->find_route_callback('/sessions/(?P<session_id>\\d+)/reviews/(?P<review_id>\\d+)/confirm', 'POST');
        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', 'confirm-allow');
        $result = $route['permission_callback']($request);

        $this->assertTrue($result);
    }

    public function test_save_criteria_allowed_when_confirmed_without_marks(): void
    {
        RestTestFixtures::login_with_caps([PR_CAP_MANAGE_SESSIONS, PR_CAP_CONFIRM_RUBRICS]);
        RestTestFixtures::set_valid_rest_nonce('criteria-confirmed');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Post-confirm edit']);
        $review_id = $this->seed_confirmed_review($session_id);

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $save->set_header('X-WP-Nonce', 'criteria-confirmed');
        $save->set_json_params([
            'criteria' => [
                ['label' => 'Updated depth', 'max_marks' => 12, 'weight' => 1],
            ],
        ]);

        $result = Rest_Reviews::save_criteria($save);

        $this->assertIsArray($result);
        $this->assertTrue($result['criteria_editable']);
        $this->assertFalse($result['has_marks']);
        $this->assertSame('Updated depth', $result['criteria'][0]['label']);
    }

    public function test_save_criteria_rejected_when_scoring_started(): void
    {
        RestTestFixtures::login_with_caps([PR_CAP_MANAGE_SESSIONS, PR_CAP_CONFIRM_RUBRICS]);
        RestTestFixtures::set_valid_rest_nonce('criteria-locked');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Scoring lock']);
        $review_id = $this->seed_confirmed_review($session_id);
        $this->seed_mark($session_id, $review_id);

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $save->set_header('X-WP-Nonce', 'criteria-locked');
        $save->set_json_params([
            'criteria' => [
                ['label' => 'Blocked', 'max_marks' => 10, 'weight' => 1],
            ],
        ]);

        $result = Rest_Reviews::save_criteria($save);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_rubric_locked', $result->get_error_code());
        $this->assertStringContainsString('scoring has started', $result->message);
    }

    public function test_save_criteria_allowed_when_unlocked_with_marks(): void
    {
        RestTestFixtures::login_with_caps([PR_CAP_MANAGE_SESSIONS, PR_CAP_CONFIRM_RUBRICS]);
        RestTestFixtures::set_valid_rest_nonce('criteria-unlock');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Unlock edit']);
        $review_id = $this->seed_confirmed_review($session_id);
        $this->seed_mark($session_id, $review_id);

        $unlock = new WP_REST_Request();
        $unlock->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $unlock->set_header('X-WP-Nonce', 'criteria-unlock');
        Rest_Reviews::unlock_review($unlock);

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $save->set_header('X-WP-Nonce', 'criteria-unlock');
        $save->set_json_params([
            'criteria' => [
                ['label' => 'Revised criterion', 'max_marks' => 8, 'weight' => 1],
            ],
        ]);

        $result = Rest_Reviews::save_criteria($save);

        $this->assertIsArray($result);
        $this->assertSame('unlocked', $result['status']);
        $this->assertTrue($result['criteria_editable']);
        $this->assertSame('Revised criterion', $result['criteria'][0]['label']);
    }

    public function test_save_criteria_rejects_zero_max_marks(): void
    {
        RestTestFixtures::login_with_caps([PR_CAP_MANAGE_SESSIONS, PR_CAP_CONFIRM_RUBRICS]);
        RestTestFixtures::set_valid_rest_nonce('criteria-zero');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Zero max marks']);
        $review_id = $this->seed_confirmed_review($session_id);

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $save->set_header('X-WP-Nonce', 'criteria-zero');
        $save->set_json_params([
            'criteria' => [
                ['label' => 'Invalid', 'max_marks' => 0, 'weight' => 1],
            ],
        ]);

        $result = Rest_Reviews::save_criteria($save);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_invalid_criteria', $result->get_error_code());
        $this->assertStringContainsString('max marks greater than zero', $result->message);
    }

    public function test_save_criteria_rejects_labeled_zero_without_partial_replace(): void
    {
        RestTestFixtures::login_with_caps([PR_CAP_MANAGE_SESSIONS, PR_CAP_CONFIRM_RUBRICS]);
        RestTestFixtures::set_valid_rest_nonce('criteria-partial');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Partial replace guard']);
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = $reviews->create($session_id, ['label' => 'Review 1']);
        $reviews->replace_criteria($review_id, [
            ['label' => 'Original', 'max_marks' => 10, 'weight' => 1],
        ]);

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $save->set_header('X-WP-Nonce', 'criteria-partial');
        $save->set_json_params([
            'criteria' => [
                ['label' => 'Valid', 'max_marks' => 8, 'weight' => 1],
                ['label' => 'Bad', 'max_marks' => 0, 'weight' => 1],
            ],
        ]);

        $result = Rest_Reviews::save_criteria($save);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_invalid_criteria', $result->get_error_code());

        $remaining = $reviews->list_criteria($review_id);
        $this->assertCount(1, $remaining);
        $this->assertSame('Original', $remaining[0]['label']);
        $this->assertSame(10.0, (float) $remaining[0]['max_marks']);
    }

    public function test_list_review_criteria_not_editable_when_scoring_started(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('reviews-list');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'List lock']);
        $review_id = $this->seed_confirmed_review($session_id);
        $this->seed_mark($session_id, $review_id);

        $list = new WP_REST_Request();
        $list->set_param('session_id', $session_id);
        $list->set_header('X-WP-Nonce', 'reviews-list');

        $result = Rest_Reviews::list_reviews($list);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['reviews']);
        $this->assertFalse($result['reviews'][0]['criteria_editable']);
        $this->assertTrue($result['reviews'][0]['has_marks']);
        $this->assertSame('confirmed', $result['reviews'][0]['status']);
    }

    public function test_save_criteria_omitting_id_deletes_criterion_row(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('criteria-delete');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Criteria delete']);
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = $reviews->create($session_id, ['label' => 'Review 1']);
        $saved = $reviews->replace_criteria($review_id, [
            ['label' => 'First', 'max_marks' => 10, 'weight' => 1],
            ['label' => 'Second', 'max_marks' => 5, 'weight' => 1],
        ]);
        $this->assertCount(2, $saved);
        $first_id = (int) $saved[0]['id'];
        $second_id = (int) $saved[1]['id'];

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $save->set_header('X-WP-Nonce', 'criteria-delete');
        $save->set_json_params([
            'criteria' => [
                [
                    'id' => $first_id,
                    'label' => 'First',
                    'max_marks' => 10,
                    'weight' => 1,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $result = Rest_Reviews::save_criteria($save);
        $this->assertIsArray($result);
        $this->assertCount(1, $result['criteria']);
        $this->assertSame($first_id, (int) $result['criteria'][0]['id']);

        $remaining = $reviews->list_criteria($review_id);
        $this->assertCount(1, $remaining);
        $this->assertSame($first_id, (int) $remaining[0]['id']);
        $remaining_ids = array_map(static fn (array $row): int => (int) $row['id'], $remaining);
        $this->assertNotContains($second_id, $remaining_ids);
    }

    public function test_save_criteria_with_ids_preserves_rows_without_duplicates(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('criteria-stable');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Criteria stable']);
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = $reviews->create($session_id, ['label' => 'Review 1']);
        $saved = $reviews->replace_criteria($review_id, [
            ['label' => 'Alpha', 'max_marks' => 10, 'weight' => 1],
            ['label' => 'Beta', 'max_marks' => 8, 'weight' => 1],
        ]);
        $alpha_id = (int) $saved[0]['id'];
        $beta_id = (int) $saved[1]['id'];

        $save = new WP_REST_Request();
        $save->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $save->set_header('X-WP-Nonce', 'criteria-stable');
        $save->set_json_params([
            'criteria' => [
                [
                    'id' => $alpha_id,
                    'label' => 'Alpha',
                    'max_marks' => 10,
                    'weight' => 1,
                    'sort_order' => 0,
                ],
                [
                    'id' => $beta_id,
                    'label' => 'Beta',
                    'max_marks' => 8,
                    'weight' => 1,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $result = Rest_Reviews::save_criteria($save);
        $this->assertIsArray($result);
        $this->assertCount(2, $result['criteria']);
        $this->assertSame($alpha_id, (int) $result['criteria'][0]['id']);
        $this->assertSame($beta_id, (int) $result['criteria'][1]['id']);
        $this->assertSame(2, count($reviews->list_criteria($review_id)));
    }

    public function test_delete_draft_review_without_marks(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('reviews');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Delete review']);

        $create_first = new WP_REST_Request();
        $create_first->set_param('session_id', $session_id);
        $create_first->set_header('X-WP-Nonce', 'reviews');
        $create_first->set_json_params(['label' => 'Keep me']);
        $first = Rest_Reviews::create_review($create_first);
        $this->assertIsArray($first);

        $create = new WP_REST_Request();
        $create->set_param('session_id', $session_id);
        $create->set_header('X-WP-Nonce', 'reviews');
        $create->set_json_params(['label' => 'Remove me']);

        $review = Rest_Reviews::create_review($create);
        $this->assertIsArray($review);

        $delete = new WP_REST_Request();
        $delete->set_params([
            'session_id' => $session_id,
            'review_id' => $review['id'],
        ]);
        $delete->set_header('X-WP-Nonce', 'reviews');

        $result = Rest_Reviews::delete_review($delete);
        $this->assertSame(['deleted' => true], $result);
        $this->assertSame(1, (new ReviewRepository($this->wpdb))->count_for_session($session_id));
    }

    public function test_delete_review_blocked_when_only_round(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('reviews');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Single review']);

        $create = new WP_REST_Request();
        $create->set_param('session_id', $session_id);
        $create->set_header('X-WP-Nonce', 'reviews');
        $create->set_json_params(['label' => 'Only']);
        $review = Rest_Reviews::create_review($create);
        $this->assertIsArray($review);

        $delete = new WP_REST_Request();
        $delete->set_params([
            'session_id' => $session_id,
            'review_id' => $review['id'],
        ]);
        $delete->set_header('X-WP-Nonce', 'reviews');

        $result = Rest_Reviews::delete_review($delete);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pr_review_last_round', $result->get_error_code());
    }

    public function test_delete_review_with_scores_requires_matching_label(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('reviews');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Scored delete']);

        $create_keep = new WP_REST_Request();
        $create_keep->set_param('session_id', $session_id);
        $create_keep->set_header('X-WP-Nonce', 'reviews');
        $create_keep->set_json_params(['label' => 'Round A']);
        Rest_Reviews::create_review($create_keep);

        $create_scored = new WP_REST_Request();
        $create_scored->set_param('session_id', $session_id);
        $create_scored->set_header('X-WP-Nonce', 'reviews');
        $create_scored->set_json_params(['label' => 'Round B']);
        $review_row = Rest_Reviews::create_review($create_scored);
        $this->assertIsArray($review_row);
        $review_id = (int) $review_row['id'];

        $reviews_repo = new ReviewRepository($this->wpdb);
        $reviews_repo->replace_criteria($review_id, [
            ['label' => 'Depth', 'max_marks' => 10, 'weight' => 1],
        ]);
        $this->seed_mark($session_id, $review_id);

        $delete_bad = new WP_REST_Request();
        $delete_bad->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $delete_bad->set_header('X-WP-Nonce', 'reviews');
        $delete_bad->set_json_params(['confirm_label' => 'Wrong']);

        $reject = Rest_Reviews::delete_review($delete_bad);
        $this->assertInstanceOf(WP_Error::class, $reject);
        $this->assertSame('pr_review_delete_confirmation_required', $reject->get_error_code());

        $delete_ok = new WP_REST_Request();
        $delete_ok->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $delete_ok->set_header('X-WP-Nonce', 'reviews');
        $delete_ok->set_json_params(['confirm_label' => 'Round B']);

        $result = Rest_Reviews::delete_review($delete_ok);
        $this->assertSame(['deleted' => true], $result);
        $this->assertSame(1, $reviews_repo->count_for_session($session_id));
        $this->assertSame(
            0,
            (new MarkRepository($this->wpdb))->count_entered_scores_for_review($review_id)
        );
    }

    public function test_update_marking_active_blocked_when_coordinator_locked(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_MANAGE_SESSIONS);
        RestTestFixtures::set_valid_rest_nonce('reviews');

        $session_id = (new SessionRepository($this->wpdb))->create(['title' => 'Locked marking']);
        $review_id = $this->seed_confirmed_review($session_id);

        $reviews = new ReviewRepository($this->wpdb);
        $reviews->set_coordinator_marks_locked($review_id, true);

        $update = new WP_REST_Request();
        $update->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $update->set_header('X-WP-Nonce', 'reviews');
        $update->set_json_params(['marking_active' => true]);

        $result = Rest_Reviews::update_review($update);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('coordinator_marks_locked', $result->get_error_code());
    }

    private function seed_confirmed_review(int $session_id): int
    {
        $reviews = new ReviewRepository($this->wpdb);
        $review_id = $reviews->create($session_id, ['label' => 'Review 1']);
        $reviews->replace_criteria($review_id, [
            ['label' => 'Depth', 'max_marks' => 10, 'weight' => 1],
        ]);

        $confirm = new WP_REST_Request();
        $confirm->set_params([
            'session_id' => $session_id,
            'review_id' => $review_id,
        ]);
        $confirm->set_header('X-WP-Nonce', 'reviews');
        RestTestFixtures::set_valid_rest_nonce('reviews');
        Rest_Reviews::confirm_review($confirm);

        return $review_id;
    }

    private function seed_mark(int $session_id, int $review_id): void
    {
        $reviews = new ReviewRepository($this->wpdb);
        $criteria = $reviews->list_criteria($review_id);
        $criterion_id = (int) ($criteria[0]['id'] ?? 0);

        $this->wpdb->insert(
            $this->wpdb->prefix . 'pr_marks',
            [
                'session_id' => $session_id,
                'review_id' => $review_id,
                'student_id' => 1,
                'reviewer_user_id' => 9,
                'criterion_id' => $criterion_id,
                'score' => 5,
                'flagged' => 0,
                'status' => 'submitted',
            ]
        );
    }

    /**
     * @return array{permission_callback: callable}
     */
    private function find_route_callback(string $pattern, string $method): array
    {
        foreach ($GLOBALS['pr_test_registered_routes'] as $route) {
            if ($route['route'] !== $pattern) {
                continue;
            }

            $args = $route['args'];
            if (isset($args['methods']) && $args['methods'] === $method) {
                return $args;
            }

            if (!isset($args[0])) {
                continue;
            }

            foreach ($args as $entry) {
                if (is_array($entry) && ($entry['methods'] ?? '') === $method) {
                    return $entry;
                }
            }
        }

        $this->fail('Route not registered: ' . $pattern . ' ' . $method);
    }
}
