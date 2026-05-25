<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;
use ProjectReviews\Repositories\SessionRepository;
use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Rest_Reviewers;
use WP_REST_Request;

final class RestReviewersTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        RestTestFixtures::reset();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        require_once dirname(__DIR__) . '/includes/capabilities.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-auth.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-bootstrap.php';
        require_once dirname(__DIR__) . '/includes/rest/class-rest-reviewers.php';

        Rest_Bootstrap::register_routes();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_add_panel_reviewer_lists_under_panel(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ASSIGN_REVIEWERS);

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $session_id = $sessions->create(['title' => 'Reviewer REST test']);
        $panel_id = $panels->create($session_id, 'Panel A');

        $add = new WP_REST_Request();
        $add->set_param('id', $session_id);
        $add->set_param('panel_id', $panel_id);
        $add->set_json_params([
            'name' => 'Dr. Smith',
            'email' => 'smith@example.com',
            'weight' => 1,
        ]);

        $created = Rest_Reviewers::add_panel_reviewer($add);
        $this->assertIsArray($created);
        $this->assertGreaterThan(0, $created['id']);
        $this->assertSame($panel_id, $created['panel_id']);

        $list = new WP_REST_Request();
        $list->set_param('id', $session_id);
        $list->set_param('panel_id', $panel_id);
        $listed = Rest_Reviewers::list_panel_reviewers($list);

        $this->assertCount(1, $listed['reviewers']);
        $this->assertSame('smith@example.com', $listed['reviewers'][0]['email']);
        $this->assertSame($panel_id, $listed['reviewers'][0]['panel_id']);
    }

    public function test_update_panel_reviewer_moves_to_another_panel(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ASSIGN_REVIEWERS);

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $session_id = $sessions->create(['title' => 'Move reviewer test']);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');
        $reviewer_id = $panels->add_reviewer($panel_a, [
            'name' => 'Mover',
            'email' => 'mover@example.com',
            'weight' => 1,
        ]);

        $update = new WP_REST_Request();
        $update->set_param('id', $session_id);
        $update->set_param('panel_id', $panel_a);
        $update->set_param('reviewer_id', $reviewer_id);
        $update->set_json_params([
            'name' => 'Mover',
            'email' => 'mover@example.com',
            'panel_id' => $panel_b,
        ]);

        $updated = Rest_Reviewers::update_panel_reviewer($update);
        $this->assertIsArray($updated);
        $this->assertSame($panel_b, $updated['panel_id']);

        $this->assertCount(0, $panels->list_reviewers($panel_a));
        $this->assertCount(1, $panels->list_reviewers($panel_b));
    }

    public function test_delete_panel_reviewer(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ASSIGN_REVIEWERS);

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $session_id = $sessions->create(['title' => 'Delete reviewer test']);
        $panel_id = $panels->create($session_id, 'Panel A');
        $reviewer_id = $panels->add_reviewer($panel_id, [
            'name' => 'Gone',
            'email' => 'gone@example.com',
        ]);

        $delete = new WP_REST_Request();
        $delete->set_param('id', $session_id);
        $delete->set_param('panel_id', $panel_id);
        $delete->set_param('reviewer_id', $reviewer_id);

        $result = Rest_Reviewers::delete_panel_reviewer($delete);
        $this->assertSame(['deleted' => true], $result);
        $this->assertCount(0, $panels->list_reviewers($panel_id));
    }

    public function test_add_panel_reviewer_rejects_duplicate_email_in_session(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ASSIGN_REVIEWERS);

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $session_id = $sessions->create(['title' => 'Duplicate email test']);
        $panel_a = $panels->create($session_id, 'Panel A');
        $panel_b = $panels->create($session_id, 'Panel B');
        $panels->add_reviewer($panel_a, [
            'name' => 'First',
            'email' => 'dup@example.com',
        ]);

        $add = new WP_REST_Request();
        $add->set_param('id', $session_id);
        $add->set_param('panel_id', $panel_b);
        $add->set_json_params([
            'name' => 'Second',
            'email' => 'dup@example.com',
        ]);

        $result = Rest_Reviewers::add_panel_reviewer($add);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('pr_reviewer_email_in_session', $result->get_error_code());
        $this->assertSame(409, $result->get_error_data()['status']);
    }

    public function test_import_reviewers_replace_mode_via_rest(): void
    {
        RestTestFixtures::login_with_cap(PR_CAP_ASSIGN_REVIEWERS);

        $sessions = new SessionRepository($this->wpdb);
        $panels = new PanelRepository($this->wpdb);
        $session_id = $sessions->create(['title' => 'Import replace test']);
        $panel_id = $panels->create($session_id, 'Panel A');
        $panels->add_reviewer($panel_id, [
            'name' => 'Old',
            'email' => 'old@example.com',
        ]);

        $import = new WP_REST_Request();
        $import->set_param('id', $session_id);
        $import->set_json_params([
            'import_mode' => 'replace',
            'rows' => [
                [
                    'panel' => 'Panel A',
                    'reviewer_name' => 'Fresh',
                    'email' => 'fresh@example.com',
                ],
            ],
        ]);

        $result = Rest_Reviewers::import_reviewers($import);
        $this->assertIsArray($result);
        $this->assertSame(1, $result['cleared']);
        $this->assertSame(1, $result['imported']);

        $listed = $panels->list_reviewers($panel_id);
        $this->assertCount(1, $listed);
        $this->assertSame('fresh@example.com', $listed[0]['email']);
    }
}
