<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateController extends Controller
{
    /** Canonical column order — the permanent reference format for this module. */
    private const HEADERS = [
        'IMEI', 'Model', 'TSO', 'RD Code', 'RD Name', 'RTCode', 'RT Name', 'ST Date', 'Activation', 'SELL-IN', 'Source',
    ];

    private const SAMPLE_ROWS = [
        ['863222207290410', 'C63 (8+128GB)', 'Roshan Singh', 'MDDX2803', 'BRAHMA DIGITAL PVT LTD', 'NP057002', 'Ashish electronics & mobile', '2025-03-03', '2026-07-08', '2025-02-20', 'Manual'],
        ['863222072207893', 'C63 (8+128GB)', 'Roshan Singh', 'MD001061', 'New Rameshworam Suppliers', 'NP065585', 'New dipesh mobile gallery', '2025-03-02', '', '2025-02-18', 'Manual'],
    ];

    private const SELL_THROUGH_HEADERS = ['IMEI', 'RD Code', 'RT Code', 'Invoice Date'];

    private const SELL_THROUGH_ROWS = [
        ['863222207290410', 'MDDX2803', 'NP057002', '2026-09-05'],
        ['863222072207893', 'MD001061', 'NP065585', '2026-09-06'],
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $sell = $request->query('kind') === 'sell_through';
        $filename = $sell ? 'dm-system-sell-through-template.csv' : 'dm-system-import-template.csv';
        $headers = $sell ? self::SELL_THROUGH_HEADERS : self::HEADERS;
        $rows = $sell ? self::SELL_THROUGH_ROWS : self::SAMPLE_ROWS;

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it cleanly
            fputcsv($out, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store',
        ]);
    }
}
