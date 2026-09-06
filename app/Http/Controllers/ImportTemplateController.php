<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateController extends Controller
{
    /** Canonical column order — the permanent reference format for this module. */
    private const HEADERS = [
        'IMEI', 'Model', 'TSO', 'RD Code', 'RD Name', 'RTCode', 'RT Name', 'ST Date', 'Activation', 'Source',
    ];

    private const SAMPLE_ROWS = [
        ['863222207290410', 'C63 (8+128GB)', 'Roshan Singh', 'MDDX2803', 'BRAHMA DIGITAL PVT LTD', 'NP057002', 'Ashish electronics & mobile', '2025-03-03', '2026-07-08', 'Manual'],
        ['863222072207893', 'C63 (8+128GB)', 'Roshan Singh', 'MD001061', 'New Rameshworam Suppliers', 'NP065585', 'New dipesh mobile gallery', '2025-03-02', '', 'Manual'],
    ];

    public function __invoke(): StreamedResponse
    {
        $filename = 'dm-system-import-template.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it cleanly
            fputcsv($out, self::HEADERS, ',', '"', '');
            foreach (self::SAMPLE_ROWS as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store',
        ]);
    }
}
