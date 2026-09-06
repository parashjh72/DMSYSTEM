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
use Throwable;

/**
 * Builds one export file in the background, streaming rows to disk (append mode)
 * so a multi-million-row CSV never sits in memory or a web request (§12).
 * CSV only for now — the most efficient format at scale.
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

        $disk = Storage::disk($export->disk);
        $relative = 'exports/'.$export->uuid.'.csv';
        $absolute = $disk->path($relative);
        @mkdir(dirname($absolute), 0775, true);

        $handle = fopen($absolute, 'w');

        try {
            [$header, $rows] = $definition->build(
                $export->type,
                ReportFilters::fromArray($export->filters ?? []),
                function (int $done) use ($export) {
                    $export->forceFill(['processed_rows' => $done])->saveQuietly();
                },
            );

            fprintf($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($handle, $header, ',', '"', '');

            $count = 0;
            foreach ($rows as $row) {
                fputcsv($handle, $row, ',', '"', '');
                $count++;
            }

            fclose($handle);
            $handle = null;

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
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($absolute);
            $export->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        ExportJobModel::where('uuid', $this->exportUuid)->update([
            'status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
