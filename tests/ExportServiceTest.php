<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\ExportService;

final class ExportServiceTest extends TestCase
{
    public function test_to_csv_returns_valid_csv(): void
    {
        $service = new ExportService();
        $csv = $service->to_csv([
            ['Panel', 'Name'],
            ['A', 'Ada'],
        ]);

        $this->assertStringContainsString('Panel,Name', $csv);
        $this->assertStringContainsString('A,Ada', $csv);
    }

    public function test_to_xlsx_is_valid_with_merge_plan(): void
    {
        $rows = [
            ['Panel', 'Student', 'Score'],
            ['North', 'S1', 8.5],
            ['North', 'S1', 9.0],
            ['South', 'S2', 7.0],
        ];
        $merge_plan = ExportService::merge_plan_for_columns($rows, [0, 1]);

        $service = new ExportService();
        $binary = $service->to_xlsx($rows, $merge_plan, [
            'freeze_row' => 1,
            'numeric_columns' => [2],
        ]);

        $this->assertNotSame('', $binary);
        $this->assertGreaterThan(2, ExportService::count_merged_cells($merge_plan));

        $path = sys_get_temp_dir() . '/pr-export-test.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertTrue($sheet->getCell('A1')->getStyle()->getFont()->getBold());
        unlink($path);
    }

    public function test_to_xlsx_applies_numeric_format_from_data_start_row_with_preface(): void
    {
        $preface_rows = 3;
        $header_rows = 2;
        $rows = [
            ['Title', ''],
            ['Project', 'Demo'],
            ['', ''],
            ['Score', 'Name'],
            ['', 'Header 2'],
            ['9.5', 'Ada'],
        ];

        $service = new ExportService();
        $binary = $service->to_xlsx($rows, [], [
            'preface_row_count' => $preface_rows,
            'header_row_count' => $header_rows,
            'freeze_row' => $preface_rows + $header_rows,
            'data_start_row' => $preface_rows + $header_rows + 1,
            'numeric_columns' => [0],
        ]);

        $path = sys_get_temp_dir() . '/pr-export-preface-test.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('0.00', $sheet->getStyle('A6')->getNumberFormat()->getFormatCode());
        $this->assertNotSame('0.00', $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        unlink($path);
    }

    public function test_to_xlsx_applies_table_borders_when_table_corner_provided(): void
    {
        // 3-row preface + 2 header rows + 2 data rows: table starts at row 4.
        $rows = [
            ['Title', '', ''],
            ['Project', 'Demo', ''],
            ['', '', ''],
            ['Review 1', '', 'Total'],
            ['', 'R1', ''],
            ['S001', 8.5, 8.5],
            ['S002', 9.0, 9.0],
        ];

        $service = new ExportService();
        $binary  = $service->to_xlsx($rows, [], [
            'preface_row_count' => 3,
            'header_row_count'  => 2,
            'freeze_row'        => 5,
            'data_start_row'    => 6,
            'numeric_columns'   => [1, 2],
            'table_corner'      => ['min_row' => 4, 'max_row' => 7, 'min_col' => 1, 'max_col' => 3],
        ]);

        $path = sys_get_temp_dir() . '/pr-border-test.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();

        // Top-left corner of table (A4): top border should be thin.
        $topBorder = $sheet->getStyle('A4')->getBorders()->getTop()->getBorderStyle();
        $this->assertSame(Border::BORDER_THIN, $topBorder, 'Top-left table cell must have thin top border');

        // Bottom-right corner of table (C7): bottom border should be thin.
        $bottomBorder = $sheet->getStyle('C7')->getBorders()->getBottom()->getBorderStyle();
        $this->assertSame(Border::BORDER_THIN, $bottomBorder, 'Bottom-right table cell must have thin bottom border');

        // A cell in the middle of the table should also have thin inner grid borders.
        $innerBorder = $sheet->getStyle('B5')->getBorders()->getLeft()->getBorderStyle();
        $this->assertNotSame(Border::BORDER_NONE, $innerBorder, 'Inner table cell must have non-empty left border');

        // Preface row (A1) must NOT have a border from table_corner.
        $prefaceBorder = $sheet->getStyle('A1')->getBorders()->getBottom()->getBorderStyle();
        $this->assertSame(Border::BORDER_NONE, $prefaceBorder, 'Preface row must not receive table borders');

        unlink($path);
    }

    public function test_to_xlsx_applies_column_fill_ranges_to_data_rows(): void
    {
        $rows = [
            ['Fixed', 'Rev1 col A', 'Rev1 col B', 'Combined'],
            ['S001', 8.5, 9.0, 8.75],
            ['S002', 7.5, 8.0, 7.75],
        ];

        $service = new ExportService();
        $binary  = $service->to_xlsx($rows, [], [
            'header_row_count' => 1,
            'freeze_row'       => 1,
            'data_start_row'   => 2,
            'column_fill_ranges' => [
                // Review block cols B–C (0-based 1–2) → light grey.
                ['start_col' => 1, 'end_col' => 2, 'fillArgb' => 'FFF5F5F5', 'min_row' => 2, 'max_row' => 3],
            ],
        ]);

        $path = sys_get_temp_dir() . '/pr-fill-test.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();

        // Data cells in banded review block must carry the grey fill.
        $this->assertSame('FFF5F5F5', $sheet->getStyle('B2')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFF5F5F5', $sheet->getStyle('C3')->getFill()->getStartColor()->getARGB());

        // Header row of the same columns must have the header fill (FFE8EEF4), not the band fill.
        $headerFill = $sheet->getStyle('B1')->getFill()->getStartColor()->getARGB();
        $this->assertSame('FFE8EEF4', $headerFill, 'Header fill must override banding fill');

        // Fixed column A data cell must NOT have the grey fill.
        $fixedFill = $sheet->getStyle('A2')->getFill()->getFillType();
        $this->assertNotSame(Fill::FILL_SOLID, $fixedFill, 'Fixed column must not receive review-band fill');

        unlink($path);
    }

    public function test_to_xlsx_applies_column_widths(): void
    {
        $service = new ExportService();
        $binary  = $service->to_xlsx([['Col'], [1]], [], [
            'column_widths' => [['col' => 0, 'width' => 24.5]],
        ]);

        $path = sys_get_temp_dir() . '/pr-width.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame(24.5, $sheet->getColumnDimension('A')->getWidth());
        unlink($path);
    }

    public function test_to_xlsx_merged_header_row_gets_uniform_fill_after_merges(): void
    {
        $preface = 2;
        $rows = [
            ['Title', '', ''],
            ['Project', 'X', ''],
            ['Review band label', '', ''],
            ['Level 2', 'L2b', 'L2c'],
            ['d1', 'd2', 'd3'],
        ];
        $merge_plan = [
            ['start_col' => 0, 'end_col' => 2, 'row' => 3],
        ];

        $service = new ExportService();
        $binary  = $service->to_xlsx($rows, $merge_plan, [
            'preface_row_count' => $preface,
            'header_row_count'  => 2,
            'freeze_row'        => $preface + 2,
            'data_start_row'    => $preface + 3,
            'wrap_text_table'   => true,
        ]);

        $path = sys_get_temp_dir() . '/pr-merge-header-fill.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();

        foreach (['A3', 'B3', 'C3'] as $coord) {
            $this->assertSame(
                'FFE8EEF4',
                $sheet->getStyle($coord)->getFill()->getStartColor()->getARGB(),
                "{$coord} should carry header fill after horizontal merge"
            );
        }

        unlink($path);
    }

    public function test_to_xlsx_applies_header_fill_ranges_instead_of_uniform_header_fill(): void
    {
        $rows = [
            ['Merged', '', ''],
            ['x', 'y', 'z'],
        ];
        $merge_plan = [['start_col' => 0, 'end_col' => 2, 'row' => 1]];

        $service = new ExportService();
        $binary  = $service->to_xlsx($rows, $merge_plan, [
            'header_row_count' => 1,
            'freeze_row'       => 1,
            'header_fill_ranges' => [
                ['start_col' => 0, 'end_col' => 2, 'min_row' => 1, 'max_row' => 1, 'fillArgb' => 'FFFFE0E0'],
            ],
        ]);

        $path = sys_get_temp_dir() . '/pr-header-ranges.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('FFFFE0E0', $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFFFE0E0', $sheet->getStyle('B1')->getFill()->getStartColor()->getARGB());

        unlink($path);
    }

    public function test_to_csv_column_count_unaffected_by_xlsx_style_additions(): void
    {
        $service = new ExportService();
        $rows    = [
            ['Reg no', 'Student name', 'Review 1 | Panel', 'Review 1 | Total', 'Combined score'],
            ['S001', 'Alice', 'North', '9.25', '9.25'],
        ];
        $csv = $service->to_csv($rows);

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $lines, 'CSV must have header + one data row');
        $firstRow = str_getcsv($lines[0], ',', '"', '\\');
        $this->assertCount(5, $firstRow, 'CSV column count must equal data column count');
    }

    public function test_to_xlsx_without_new_style_keys_behaves_as_before(): void
    {
        // Regression: omitting table_corner and column_fill_ranges must produce a valid workbook
        // with no borders and the existing header fill behaviour.
        $rows = [
            ['Panel', 'Score'],
            ['North', 9.5],
        ];

        $service = new ExportService();
        $binary  = $service->to_xlsx($rows, [], [
            'freeze_row'      => 1,
            'numeric_columns' => [1],
        ]);

        $path = sys_get_temp_dir() . '/pr-compat-test.xlsx';
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();

        // Header bold still applied.
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());

        // No borders on data cell (table_corner absent → no border logic runs).
        $noBorder = $sheet->getStyle('A2')->getBorders()->getTop()->getBorderStyle();
        $this->assertSame(Border::BORDER_NONE, $noBorder, 'Without table_corner, data cells must have no borders');

        unlink($path);
    }

    public function test_merge_plan_for_row_groups_creates_horizontal_merges(): void
    {
        $plan = ExportService::merge_plan_for_row_groups(1, 2, [2, 3, 1]);

        $this->assertCount(2, $plan);
        $this->assertSame(['start_col' => 2, 'end_col' => 3, 'row' => 1], $plan[0]);
        $this->assertSame(['start_col' => 4, 'end_col' => 6, 'row' => 1], $plan[1]);
    }
}
