<?php

namespace App\Services\Export;

use App\Jobs\BuildExportJob;
use App\Models\ExportJob;
use App\Services\Reporting\ReportFilters;
use App\Support\RecordScope;

class ExportService
{
    public function queue(string $type, ReportFilters $filters, ?int $userId, string $format = 'csv'): ExportJob
    {
        // Freeze the requester's RD row-scope into the payload — the queue worker
        // that builds the file runs with no authenticated user.
        $filters->rdScope = $filters->rdScope ?: (RecordScope::rdCodes() ?? []);

        $export = ExportJob::create([
            'type' => $type,
            'format' => in_array($format, ['csv', 'xlsx'], true) ? $format : 'csv',
            'filters' => $filters->toArray(),
            'status' => 'pending',
            'created_by' => $userId,
        ]);

        BuildExportJob::dispatch($export->uuid)->onQueue('exports');

        return $export;
    }
}
