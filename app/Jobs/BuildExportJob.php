<?php

namespace App\Jobs;

use App\Models\ExportJob as ExportJobModel;
use App\Services\Export\ExportDefinition;
use App\Services\Reporting\ReportFilters;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

/**
 * Builds one export file in the background.
 *
 * CSV: rows streamed to disk (append) — safe for millions of rows.
 * XLSX: styled workbook (coloured header, banded rows, per-row max highlighted,
 *       bold totals row summing every numeric column). Meant for the pivot stock
 *       reports, which are bounded in size.
 */
class BuildExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public string $exportUuid) {}

    public function handle(ExportDefinition $definition): void
    {
        $export = ExportJobModel::where('uuid', $this->exportUuid)->firstOrFail();
        $export->update(['status' => 'processing', 'processed_rows' => 0]);

        $format = $export->format === 'xlsx' ? 'xlsx' : 'csv';
        $disk = Storage::disk($export->disk);
        $relative = 'exports/'.$export->uuid.'.'.$format;
        $absolute = $disk->path($relative);
        @mkdir(dirname($absolute), 0775, true);

        try {
            [$header, $rows, $extraSheets] = $definition->build(
                $export->type,
                ReportFilters::fromArray($export->filters ?? []),
                fn (int $done) => $export->forceFill(['processed_rows' => $done])->saveQuietly(),
            );

            $count = $format === 'xlsx'
                ? $this->writeXlsx($absolute, $header, $rows, $extraSheets)
                : $this->writeCsv($absolute, $header, $rows);

            $export->update([
                'status' => 'completed',
                'total_rows' => $count,
                'processed_rows' => $count,
                'stored_path' => $relative,
                'file_size' => filesize($absolute) ?: null,
                'completed_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        } catch (Throwable $e) {
            @unlink($absolute);
            $export->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            throw $e;
        }
    }

    /** @return int rows written */
    private function writeCsv(string $path, array $header, iterable $rows): int
    {
        $handle = fopen($path, 'w');
        fprintf($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($handle, $header, ',', '"', '');

        $count = 0;
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
            $count++;
        }
        fclose($handle);

        return $count;
    }

    /**
     * @param  array<int,array<int,mixed>>|iterable  $rows
     * @param  list<array{name:string,header:list<string>,indexes:list<int>}>  $extraSheets
     * @return int data rows written on the first sheet
     */
    private function writeXlsx(string $path, array $header, iterable $rows, array $extraSheets = []): int
    {
        // Extra sheets re-read the rows, so materialise once.
        $matrix = is_array($rows) ? $rows : iterator_to_array($rows, false);

        $writer = new XlsxWriter;
        $writer->openToFile($path);

        $count = $this->writeSheet($writer, $writer->getCurrentSheet(), 'Detail', $header, $matrix);

        foreach ($extraSheets as $spec) {
            $projected = array_map(
                fn ($row) => array_map(fn ($i) => array_values($row)[$i] ?? '', $spec['indexes']),
                $matrix,
            );
            $writer->addNewSheetAndMakeItCurrent();
            $this->writeSheet($writer, $writer->getCurrentSheet(), $spec['name'], $spec['header'], $projected);
        }

        $writer->close();

        return $count;
    }

    /** @param array<int,array<int,mixed>> $rows @return int data rows */
    private function writeSheet($writer, $sheet, string $name, array $header, array $rows): int
    {
        $headerStyle = (new Style)
            ->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor(Color::DARK_BLUE);
        $bandStyle = (new Style)->withBackgroundColor('EEF2FB');
        $highlightStyle = (new Style)->withFontBold(true)->withBackgroundColor(Color::LIGHT_GREEN);
        $totalStyle = (new Style)->withFontBold(true)->withBackgroundColor('D9E2F3');

        $labelCols = $this->labelColumnCount($header);
        $sheet->setName(mb_substr($name ?: 'Sheet', 0, 31));
        $sheet->setSheetView((new SheetView)->withFreezeRow(2)->withFreezeColumn(chr(65 + $labelCols)));
        $sheet->setColumnWidthForRange(28, 1, $labelCols);

        $writer->addRow(Row::fromValuesWithStyle($header, $headerStyle));

        $totals = array_fill(0, count($header), 0);
        $count = 0;

        foreach ($rows as $row) {
            $values = array_values($row);
            $rowMax = max(array_map(fn ($v) => is_numeric($v) ? (float) $v : 0, array_slice($values, $labelCols)) ?: [0]);

            $cells = [];
            foreach ($values as $i => $value) {
                $numeric = is_numeric($value) && $i >= $labelCols;
                if ($numeric) {
                    $totals[$i] += (float) $value;
                }
                $style = match (true) {
                    $numeric && $rowMax > 0 && (float) $value === $rowMax => $highlightStyle,
                    $count % 2 === 1 => $bandStyle,
                    default => null,
                };
                $cells[$i] = Cell::fromValue($numeric ? 0 + $value : $value, $style);
            }
            $writer->addRow(new Row($cells));
            $count++;
        }

        $totalCells = [];
        foreach ($header as $i => $_) {
            $totalCells[$i] = Cell::fromValue(
                $i === 0 ? 'Total' : ($totals[$i] ?: ($i < $labelCols ? '' : 0)),
                $totalStyle,
            );
        }
        $writer->addRow(new Row($totalCells));

        return $count;
    }

    /** How many leading columns are text labels (RD/RT code + name) rather than data. */
    private function labelColumnCount(array $header): int
    {
        $labels = ['RD Code', 'RD Name', 'RT Code', 'RT Name', 'Model', 'TSO', 'ST Date'];
        $n = 0;
        foreach ($header as $col) {
            if (in_array($col, $labels, true)) {
                $n++;
            } else {
                break;
            }
        }

        return max(1, $n);
    }

    public function failed(Throwable $e): void
    {
        ExportJobModel::where('uuid', $this->exportUuid)->update([
            'status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
