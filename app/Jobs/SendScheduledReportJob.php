<?php

namespace App\Jobs;

use App\Mail\ScheduledReportMail;
use App\Models\ExportJob as ExportJobModel;
use App\Models\ScheduledReport;
use App\Services\Reporting\ReportPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates one scheduled report file (reusing the normal export pipeline) and
 * emails it to the report's recipients. Dispatched by reports:dispatch-scheduled.
 */
class SendScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $scheduledReportId) {}

    public function handle(): void
    {
        $report = ScheduledReport::find($this->scheduledReportId);
        if (! $report) {
            return;
        }

        try {
            [$from, $to] = ReportPeriod::resolve($report->period);

            $dateable = (bool) (config("reports.schedulable.{$report->export_type}.1") ?? false);
            $filters = (array) ($report->filters ?? []);
            if ($dateable && $from) {
                $filters["{$report->date_basis}_from"] = $from;
                $filters["{$report->date_basis}_to"] = $to;
            }

            $export = ExportJobModel::create([
                'type' => $report->export_type,
                'format' => $report->format === 'csv' ? 'csv' : 'xlsx',
                'filters' => $filters,
                'status' => 'pending',
                'created_by' => $report->created_by,
            ]);

            // Reuse the full build+write+store pipeline synchronously.
            BuildExportJob::dispatchSync($export->uuid);
            $export->refresh();

            if ($export->status !== 'completed' || ! $export->stored_path) {
                throw new \RuntimeException($export->error_message ?: 'Export build failed.');
            }

            $absolute = Storage::disk($export->disk)->path($export->stored_path);
            $fileName = Str::slug($report->name).'-'.now(config('reports.timezone'))->format('Ymd')
                .'.'.$export->format;

            $recipients = array_values(array_filter(
                (array) $report->recipients,
                fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL),
            ));

            if ($recipients === []) {
                throw new \RuntimeException('No valid recipient email addresses.');
            }

            Mail::to($recipients)->send(new ScheduledReportMail(
                report: $report,
                filePath: $absolute,
                fileName: $fileName,
                window: ['from' => $from, 'to' => $to],
                rowCount: (int) $export->total_rows,
            ));

            $report->forceFill([
                'last_run_at' => now(),
                'last_status' => 'ok',
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $report?->forceFill([
                'last_run_at' => now(),
                'last_status' => 'failed',
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            throw $e;
        }
    }
}
