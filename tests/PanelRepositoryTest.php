<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Repositories\PanelRepository;

final class PanelRepositoryTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_expand_import_rows_splits_wide_format(): void
    {
        $rows = PanelRepository::expand_import_rows([
            [
                'panel' => 'Panel A',
                'reviewer_1' => 'Dr. Smith',
                'reviewer_1_email' => 'smith@example.com',
                'reviewer_2' => 'Dr. Jones',
                'reviewer_2_email' => 'jones@example.com',
            ],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('Panel A', $rows[0]['panel']);
        $this->assertSame('Dr. Smith', $rows[0]['reviewer_name']);
        $this->assertSame('smith@example.com', $rows[0]['email']);
        $this->assertSame('Dr. Jones', $rows[1]['reviewer_name']);
    }

    public function test_expand_import_rows_prefers_wide_format_when_long_fields_present(): void
    {
        $rows = PanelRepository::expand_import_rows([
            [
                'panel' => 'Panel A',
                'reviewer_1' => 'RA-1',
                'reviewer_1_email' => 'ra-1@vit.ac.in',
                'reviewer_1_weight' => '1',
                'reviewer_2' => 'RA-2',
                'reviewer_2_email' => 'ra-2@vit.ac.in',
                'reviewer_2_weight' => '1',
                'reviewer_name' => 'RA-1',
                'email' => 'ra-1@vit.ac.in',
            ],
            [
                'panel' => 'Panel B',
                'reviewer_1' => 'RB-1',
                'reviewer_1_email' => 'rb-1@vit.ac.in',
                'reviewer_1_weight' => '1',
                'reviewer_2' => 'RB-2',
                'reviewer_2_email' => 'rb-2@vit.ac.in',
                'reviewer_2_weight' => '1',
                'reviewer_3' => 'RB-3',
                'reviewer_3_email' => 'rb-3@vit.ac.in',
                'reviewer_3_weight' => '1',
                'reviewer_name' => 'RB-1',
                'email' => 'rb-1@vit.ac.in',
            ],
        ]);

        $this->assertCount(5, $rows);

        $by_panel = [];
        foreach ($rows as $row) {
            $by_panel[$row['panel']][] = $row['email'];
        }

        $this->assertSame(
            ['ra-1@vit.ac.in', 'ra-2@vit.ac.in'],
            $by_panel['Panel A']
        );
        $this->assertSame(
            ['rb-1@vit.ac.in', 'rb-2@vit.ac.in', 'rb-3@vit.ac.in'],
            $by_panel['Panel B']
        );
    }

    public function test_import_reviewers_wide_format_assigns_each_panel(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $session_id = 1;
        $panel_a_id = $panels->create($session_id, 'Panel A');
        $panel_b_id = $panels->create($session_id, 'Panel B');

        $result = $panels->import_reviewers($session_id, [
            [
                'panel' => 'Panel A',
                'reviewer_1' => 'RA-1',
                'reviewer_1_email' => 'ra-1@vit.ac.in',
                'reviewer_2' => 'RA-2',
                'reviewer_2_email' => 'ra-2@vit.ac.in',
            ],
            [
                'panel' => 'Panel B',
                'reviewer_1' => 'RB-1',
                'reviewer_1_email' => 'rb-1@vit.ac.in',
                'reviewer_2' => 'RB-2',
                'reviewer_2_email' => 'rb-2@vit.ac.in',
                'reviewer_3' => 'RB-3',
                'reviewer_3_email' => 'rb-3@vit.ac.in',
            ],
        ]);

        $this->assertSame(5, $result['imported']);
        $this->assertSame(0, $result['failed']);
        $this->assertCount(2, $panels->list_reviewers($panel_a_id));
        $this->assertCount(3, $panels->list_reviewers($panel_b_id));
    }

    public function test_import_reviewers_resolves_panel_by_number(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $session_id = 1;
        $panels->create($session_id, 'Alpha');
        $panels->create($session_id, 'Beta');

        $result = $panels->import_reviewers($session_id, [
            [
                'panel' => '2',
                'reviewer_name' => 'Reviewer Two',
                'email' => 'two@example.com',
            ],
        ]);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['failed']);

        $session_panels = $panels->list_by_session($session_id);
        $beta_id = (int) $session_panels[1]['id'];
        $reviewers = $panels->list_reviewers($beta_id);
        $this->assertCount(1, $reviewers);
        $this->assertSame('two@example.com', $reviewers[0]['email']);
    }

    public function test_import_reviewers_updates_existing_email_and_moves_panel(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $session_id = 1;
        $alpha_id = $panels->create($session_id, 'Alpha');
        $beta_id = $panels->create($session_id, 'Beta');
        $panels->add_reviewer($alpha_id, [
            'name' => 'Original',
            'email' => 'reviewer@example.com',
            'weight' => 1,
        ]);

        $result = $panels->import_reviewers($session_id, [
            [
                'panel' => 'Beta',
                'reviewer_name' => 'Updated Name',
                'email' => 'reviewer@example.com',
                'weight' => 2,
            ],
        ]);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['failed']);

        $alpha_reviewers = $panels->list_reviewers($alpha_id);
        $beta_reviewers = $panels->list_reviewers($beta_id);
        $this->assertCount(0, $alpha_reviewers);
        $this->assertCount(1, $beta_reviewers);
        $this->assertSame('Updated Name', $beta_reviewers[0]['name']);
        $this->assertSame(2.0, (float) $beta_reviewers[0]['weight']);
    }

    public function test_import_reviewers_replace_clears_existing_roster(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $session_id = 1;
        $panel_a_id = $panels->create($session_id, 'Panel A');
        $panels->create($session_id, 'Panel B');
        $panels->add_reviewer($panel_a_id, [
            'name' => 'Legacy',
            'email' => 'legacy@example.com',
        ]);

        $result = $panels->import_reviewers($session_id, [
            [
                'panel' => 'Panel B',
                'reviewer_name' => 'New Only',
                'email' => 'new@example.com',
            ],
        ], 'replace');

        $this->assertSame(1, $result['cleared']);
        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertCount(0, $panels->list_reviewers($panel_a_id));
        $session_panels = $panels->list_by_session($session_id);
        $panel_b_id = (int) $session_panels[1]['id'];
        $beta_reviewers = $panels->list_reviewers($panel_b_id);
        $this->assertCount(1, $beta_reviewers);
        $this->assertSame('new@example.com', $beta_reviewers[0]['email']);
    }

    public function test_import_reviewers_append_keeps_reviewers_not_in_file(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $session_id = 1;
        $panel_a_id = $panels->create($session_id, 'Panel A');
        $panel_b_id = $panels->create($session_id, 'Panel B');
        $panels->add_reviewer($panel_a_id, [
            'name' => 'Keep Me',
            'email' => 'keep@example.com',
        ]);

        $result = $panels->import_reviewers($session_id, [
            [
                'panel' => 'Panel B',
                'reviewer_name' => 'Added',
                'email' => 'added@example.com',
            ],
        ], 'append');

        $this->assertSame(0, $result['cleared']);
        $this->assertSame(1, $result['imported']);
        $this->assertCount(1, $panels->list_reviewers($panel_a_id));
        $this->assertCount(1, $panels->list_reviewers($panel_b_id));
    }

    public function test_import_reviewers_fails_when_same_email_on_multiple_panels_in_file(): void
    {
        $panels = new PanelRepository($this->wpdb);
        $session_id = 1;
        $panel_a_id = $panels->create($session_id, 'Panel A');
        $panel_b_id = $panels->create($session_id, 'Panel B');

        $result = $panels->import_reviewers($session_id, [
            [
                'panel' => 'Panel A',
                'reviewer_name' => 'Dr. Same',
                'email' => 'same@example.com',
                '_csv_row' => 2,
            ],
            [
                'panel' => 'Panel B',
                'reviewer_name' => 'Dr. Same',
                'email' => 'same@example.com',
                '_csv_row' => 3,
            ],
        ]);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(3, $result['errors'][0]['row']);
        $this->assertStringContainsString('Panel A', $result['errors'][0]['message']);

        $this->assertCount(1, $panels->list_reviewers($panel_a_id));
        $this->assertCount(0, $panels->list_reviewers($panel_b_id));
        $this->assertSame('same@example.com', $panels->list_reviewers($panel_a_id)[0]['email']);
    }
}
