<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SessionPanelReportSettings;

final class PluginSettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        if (function_exists('delete_option')) {
            delete_option(PluginSettings::OPTION_KEY);
            delete_option(SessionPanelReportSettings::option_key(99));
        }
        parent::tearDown();
    }

    public function test_default_panel_report_pdf_includes_review_report_title(): void
    {
        $defaults = PluginSettings::default_panel_report_pdf();

        $this->assertSame('Review Report', $defaults['report']['title']);
        $this->assertTrue($defaults['table']['show_attendance']);
        $this->assertSame('Final Marks', $defaults['table']['final_marks_column_header']);
        $this->assertSame('R{n}', $defaults['table']['reviewer_header_pattern']);
        $this->assertTrue($defaults['footer']['show_generated_datetime']);
        $this->assertSame(1, $defaults['styles']['table_border_pt']);
        $this->assertFalse($defaults['settings_frozen']);
    }

    public function test_session_panel_report_settings_freeze_and_unfreeze(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress options unavailable.');
        }

        SessionPanelReportSettings::save(88, [
            'report' => ['program_name' => 'B.Tech'],
        ]);
        $this->assertFalse(SessionPanelReportSettings::is_settings_frozen(88));

        $frozen = SessionPanelReportSettings::freeze_settings(88);
        $this->assertIsArray($frozen);
        $this->assertTrue(SessionPanelReportSettings::is_settings_frozen(88));
        $this->assertNotSame('', $frozen['settings_frozen_at'] ?? '');

        $blocked = SessionPanelReportSettings::save(88, ['report' => ['program_name' => 'Changed']]);
        $this->assertInstanceOf(\WP_Error::class, $blocked);
        $this->assertSame('panel_report_settings_frozen', $blocked->get_error_code());

        $unfrozen = SessionPanelReportSettings::unfreeze_settings(88);
        $this->assertIsArray($unfrozen);
        $this->assertFalse(SessionPanelReportSettings::is_settings_frozen(88));

        if (function_exists('delete_option')) {
            delete_option(SessionPanelReportSettings::option_key(88));
        }
    }

    public function test_session_panel_report_save_before_freeze_preserves_unsaved_edits(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress options unavailable.');
        }

        SessionPanelReportSettings::save(77, [
            'report' => ['program_name' => 'Original'],
        ]);

        $saved = SessionPanelReportSettings::save(77, [
            'report' => ['program_name' => 'Updated Before Freeze'],
        ]);
        $this->assertIsArray($saved);

        $frozen = SessionPanelReportSettings::freeze_settings(77);
        $this->assertIsArray($frozen);
        $this->assertSame('Updated Before Freeze', $frozen['report']['program_name']);

        if (function_exists('delete_option')) {
            delete_option(SessionPanelReportSettings::option_key(77));
        }
    }

    public function test_sanitize_panel_report_pdf_preserves_letterhead_blocks(): void
    {
        $sanitized = PluginSettings::sanitize_panel_report_pdf([
            'letterhead' => [
                'blocks' => [
                    ['type' => 'text', 'value' => 'Dept of CS', 'style' => 'title'],
                    ['type' => 'text', 'value' => 'Example School', 'style' => 'subtitle'],
                ],
            ],
            'signatures' => [
                'hod' => [
                    'enabled' => true,
                    'label' => 'Head of the Department',
                    'name' => 'Dr. Example',
                ],
            ],
        ]);

        $text_blocks = array_values(array_filter(
            $sanitized['letterhead']['blocks'],
            static fn (array $block): bool => ($block['type'] ?? '') === 'text'
        ));
        $this->assertSame('Dept of CS', $text_blocks[0]['value']);
        $this->assertSame('Dr. Example', $sanitized['signatures']['hod']['name']);
    }

    public function test_session_panel_report_settings_round_trip(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress options unavailable.');
        }

        $saved = SessionPanelReportSettings::save(99, [
            'report' => [
                'program_name' => 'B.Tech CSE',
                'semester' => 'Even 2026',
            ],
            'table' => [
                'final_marks_column_header' => 'Final Marks',
            ],
        ]);

        $this->assertSame('B.Tech CSE', $saved['report']['program_name']);
        $loaded = SessionPanelReportSettings::get(99);
        $this->assertSame('Even 2026', $loaded['report']['semester']);
    }
}
