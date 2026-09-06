<?php

namespace App\Services\Export;

use App\Jobs\BuildExportJob;
use App\Models\ExportJob;
use App\Services\Reporting\ReportFilters;

class ExportService
{
    public function queue(string $type, ReportFilters $filters, ?int $userId, string $format = 'csv'): ExportJob
    {
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
