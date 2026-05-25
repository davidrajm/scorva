<?php

declare(strict_types=1);

namespace ProjectReviews\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExportService
{
    /**
     * @param list<list<string|int|float|null>> $rows First row is header.
     */
    public function to_csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open temp stream for CSV export.');
        }

        foreach ($rows as $row) {
            fputcsv(
                $handle,
                array_map(
                    static fn ($cell): string => self::escape_spreadsheet_cell($cell),
                    $row
                ),
                ',',
                '"',
                '\\'
            );
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param list<list<string|int|float|null>> $rows
     * @param list<array{col?: int, start_row?: int, end_row?: int, start_col?: int, end_col?: int, row?: int}> $merge_plan
     *   Vertical: col + start_row + end_row (1-based rows, 0-based col).
     *   Horizontal: start_col + end_col + row (1-based row, 0-based cols).
     * @param array{
     *   freeze_row?: int,
     *   header_row_count?: int,
     *   preface_row_count?: int,
     *   data_start_row?: int,
     *   numeric_columns?: list<int>,
     *   table_corner?: array{min_row: int, max_row: int, min_col: int, max_col: int},
     *   column_fill_ranges?: list<array{start_col: int, end_col: int, fillArgb: string, min_row?: int, max_row?: int}>,
     *   column_widths?: list<array{col: int, width: float}>,
     *   wrap_text_table?: bool,
     *   header_fill_argb?: string,
     *   header_fill_ranges?: list<array{start_col: int, end_col: int, min_row: int, max_row: int, fillArgb: string}>
     * } $styles
     *   header_fill_ranges col indices are 0-based; min_row/max_row are 1-based sheet rows.
     *   When header_fill_ranges is non-empty, it replaces the single solid header_fill_argb for those rectangles only;
     *   set header_fill_argb for any row/column not covered by ranges, or include full neutral strips in the plan.
     *   All row values are 1-based. table_corner col values are 1-based.
     *   column_fill_ranges col values (start_col, end_col) are 0-based.
     *   Omitting table_corner or column_fill_ranges preserves prior styling for other exports.
     */
    public function to_xlsx(array $rows, array $merge_plan = [], array $styles = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($colIndex + 1) . ($rowIndex + 1);
                if (is_string($value)) {
                    $sheet->setCellValueExplicit(
                        $coordinate,
                        self::escape_spreadsheet_cell($value),
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                } else {
                    $sheet->setCellValue($coordinate, $value);
                }
            }
        }

        // Merges must run before table/header styling so Excel and PhpSpreadsheet paint fills
        // correctly across merged header regions (especially horizontal review bands).
        foreach ($merge_plan as $merge) {
            if (isset($merge['start_col'], $merge['end_col'], $merge['row'])) {
                $startCol = Coordinate::stringFromColumnIndex(((int) $merge['start_col']) + 1);
                $endCol = Coordinate::stringFromColumnIndex(((int) $merge['end_col']) + 1);
                $row = (int) $merge['row'];
                $start = $startCol . $row;
                $end = $endCol . $row;
            } else {
                $col = Coordinate::stringFromColumnIndex(((int) ($merge['col'] ?? 0)) + 1);
                $start = $col . (int) ($merge['start_row'] ?? 0);
                $end = $col . (int) ($merge['end_row'] ?? 0);
            }

            if ($start !== $end) {
                $sheet->mergeCells("{$start}:{$end}");
            }
        }

        $prefaceRows = max(0, (int) ($styles['preface_row_count'] ?? 0));
        $headerRows = max(1, (int) ($styles['header_row_count'] ?? 1));
        $tableHeaderStart = $prefaceRows + 1;
        $tableHeaderEnd = $prefaceRows + $headerRows;
        $dataStartRow = (int) ($styles['data_start_row'] ?? ($prefaceRows > 0 ? $tableHeaderEnd + 1 : 2));
        $headerFillArgb = (string) ($styles['header_fill_argb'] ?? 'FFE8EEF4');

        if ($rows !== []) {
            $lastCol = Coordinate::stringFromColumnIndex(count($rows[0]));
            $totalRows = count($rows);
            if ($prefaceRows > 0) {
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                if ($prefaceRows > 2) {
                    $sheet->getStyle('A2:A' . ($prefaceRows - 1))->getFont()->setBold(true);
                }
                $headerRange = 'A' . $tableHeaderStart . ':' . $lastCol . $tableHeaderEnd;
            } else {
                $headerRange = 'A1:' . $lastCol . $headerRows;
            }

            // Body banding first; header fill applied afterward so it always wins in header rows.
            foreach ($styles['column_fill_ranges'] ?? [] as $fillRange) {
                $fStartCol = Coordinate::stringFromColumnIndex(((int) $fillRange['start_col']) + 1);
                $fEndCol   = Coordinate::stringFromColumnIndex(((int) $fillRange['end_col']) + 1);
                $fMinRow   = (int) ($fillRange['min_row'] ?? $dataStartRow);
                $fMaxRow   = (int) ($fillRange['max_row'] ?? $totalRows);
                if ($fMinRow > $fMaxRow) {
                    continue;
                }
                $sheet->getStyle("{$fStartCol}{$fMinRow}:{$fEndCol}{$fMaxRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB((string) $fillRange['fillArgb']);
            }

            $sheet->getStyle($headerRange)->getFont()->setBold(true);

            $headerFillRanges = $styles['header_fill_ranges'] ?? [];
            if ($headerFillRanges !== []) {
                foreach ($headerFillRanges as $hr) {
                    $hStart = Coordinate::stringFromColumnIndex(((int) $hr['start_col']) + 1);
                    $hEnd = Coordinate::stringFromColumnIndex(((int) $hr['end_col']) + 1);
                    $hMin = (int) $hr['min_row'];
                    $hMax = (int) $hr['max_row'];
                    if ($hMin > $hMax) {
                        continue;
                    }
                    $sheet->getStyle("{$hStart}{$hMin}:{$hEnd}{$hMax}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB((string) $hr['fillArgb']);
                }
            } else {
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($headerFillArgb);
            }

            if (!empty($styles['wrap_text_table'])) {
                // Consolidated-style sheets: wrap + center headers (review bands, reviewer labels).
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            if (!empty($styles['wrap_text_table']) && $dataStartRow <= $totalRows) {
                $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$totalRows}")->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_BOTTOM);
            }

            // Apply thin grid borders to the score table rectangle (headers + data rows; not preface).
            $tableCorner = $styles['table_corner'] ?? null;
            if (is_array($tableCorner)) {
                $tcMinRow = (int) ($tableCorner['min_row'] ?? 0);
                $tcMaxRow = (int) ($tableCorner['max_row'] ?? 0);
                $tcMinCol = (int) ($tableCorner['min_col'] ?? 0);
                $tcMaxCol = (int) ($tableCorner['max_col'] ?? 0);
                if ($tcMinRow > 0 && $tcMaxRow >= $tcMinRow && $tcMinCol > 0 && $tcMaxCol >= $tcMinCol) {
                    $borderRange = Coordinate::stringFromColumnIndex($tcMinCol) . $tcMinRow
                        . ':' . Coordinate::stringFromColumnIndex($tcMaxCol) . $tcMaxRow;
                    $sheet->getStyle($borderRange)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['argb' => 'FF666666'],
                            ],
                        ],
                    ]);
                }
            }
        }

        foreach ($styles['column_widths'] ?? [] as $widthSpec) {
            $letter = Coordinate::stringFromColumnIndex(((int) $widthSpec['col']) + 1);
            $sheet->getColumnDimension($letter)->setWidth((float) ($widthSpec['width'] ?? 8.5));
        }

        $freezeRow = (int) ($styles['freeze_row'] ?? 1);
        $sheet->freezePane('A' . ($freezeRow + 1));

        foreach ($styles['numeric_columns'] ?? [] as $colIndex) {
            $letter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $lastRow = max(1, count($rows));
            if ($dataStartRow <= $lastRow) {
                $sheet->getStyle("{$letter}{$dataStartRow}:{$letter}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('0.00');
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        if ($binary === false) {
            throw new \RuntimeException('Failed to generate XLSX export.');
        }

        return $binary;
    }

    /**
     * @param list<list<string|int|float|null>> $rows
     * @param list<int> $merge_columns 0-based column indices to merge consecutive identical values (skipping header).
     * @return list<array{col: int, start_row: int, end_row: int}>
     */
    public static function merge_plan_for_columns(array $rows, array $merge_columns): array
    {
        if (count($rows) < 2) {
            return [];
        }

        $plan = [];
        $dataRows = array_slice($rows, 1);

        foreach ($merge_columns as $col) {
            $start = 2;
            $prev = null;
            foreach ($dataRows as $index => $row) {
                $value = isset($row[$col]) ? (string) $row[$col] : '';
                $rowNum = $index + 2;
                if ($prev === null) {
                    $prev = $value;
                    $start = $rowNum;
                    continue;
                }
                if ($value !== $prev) {
                    if ($rowNum - 1 > $start) {
                        $plan[] = ['col' => $col, 'start_row' => $start, 'end_row' => $rowNum - 1];
                    }
                    $start = $rowNum;
                    $prev = $value;
                    continue;
                }
                if ($index === count($dataRows) - 1 && $rowNum > $start) {
                    $plan[] = ['col' => $col, 'start_row' => $start, 'end_row' => $rowNum];
                }
            }
        }

        return $plan;
    }

    public static function count_merged_cells(array $merge_plan): int
    {
        $count = 0;
        foreach ($merge_plan as $merge) {
            if (isset($merge['start_col'], $merge['end_col'])) {
                $span = (int) $merge['end_col'] - (int) $merge['start_col'] + 1;
            } else {
                $span = (int) ($merge['end_row'] ?? 0) - (int) ($merge['start_row'] ?? 0) + 1;
            }

            if ($span > 1) {
                $count += $span;
            }
        }

        return $count;
    }

    /**
     * Horizontal merges for grouped header cells on one row.
     *
     * @param int $row 1-based row index
     * @param int $startCol 0-based column where the first group begins
     * @param list<int> $colspans colspan per group (values &gt; 1 are merged)
     * @return list<array{start_col: int, end_col: int, row: int}>
     */
    public static function merge_plan_for_row_groups(int $row, int $startCol, array $colspans): array
    {
        $plan = [];
        $col = $startCol;
        foreach ($colspans as $span) {
            $span = max(1, (int) $span);
            if ($span > 1) {
                $plan[] = [
                    'start_col' => $col,
                    'end_col' => $col + $span - 1,
                    'row' => $row,
                ];
            }
            $col += $span;
        }

        return $plan;
    }

    /**
     * @param string|int|float|null $cell
     */
    public static function escape_spreadsheet_cell($cell): string
    {
        if ($cell === null) {
            return '';
        }

        $value = (string) $cell;
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
