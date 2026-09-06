<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chunk sizing
    |--------------------------------------------------------------------------
    |
    | chunk_size        Rows read from the file and staged per ImportChunkJob.
    | insert_batch      Rows per bulk INSERT statement into the staging table.
    |
    | Guidance (see docs/ARCHITECTURE.md §3):
    |   shared / low memory ....... 2,000 - 5,000
    |   dedicated / 4GB+ .......... 10,000 - 25,000
    |   tuned import box .......... 50,000 - 100,000
    |
    | Default 5,000 keeps a single job under ~50MB and a few seconds of runtime
    | on modest hardware. Never hard-code past this file.
    */
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 5000),
    'insert_batch' => (int) env('IMPORT_INSERT_BATCH', 2000),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'disk' => env('IMPORT_DISK', 'local'),
    'directory' => 'imports',

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'prepare' => env('IMPORT_QUEUE_PREPARE', 'imports'),
        'chunk' => env('IMPORT_QUEUE_CHUNK', 'imports'),
        'finalize' => env('IMPORT_QUEUE_FINALIZE', 'imports'),
        'summary' => env('IMPORT_QUEUE_SUMMARY', 'summaries'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Header mapping
    |--------------------------------------------------------------------------
    |
    | Canonical field => list of accepted header spellings (lower-cased, trimmed,
    | non-alphanumerics collapsed). The uploader can override the resolved map
    | before the import starts.
    */
    'header_aliases' => [
        'imei' => ['imei', 'imei no', 'imei number', 'imei1'],
        'model' => ['model', 'model name', 'device model', 'sku'],
        'tso' => ['tso', 'tso name', 'territory sales officer', 'so'],
        'rd_code' => ['rd code', 'rdcode', 'distributor code', 'rd'],
        'rd_name' => ['rd name', 'rdname', 'distributor name', 'distributor'],
        'rt_code' => ['rtcode', 'rt code', 'retailer code', 'rt'],
        'rt_name' => ['rt name', 'rtname', 'retailer name', 'retailer'],
        'st_date' => ['st date', 'stdate', 'sell through date', 'sellthrough date', 'sell-thru date', 'st'],
        'activation_date' => ['activation', 'activation date', 'activated on', 'act date'],
        'source' => ['source', 'data source', 'src'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Required fields
    |--------------------------------------------------------------------------
    */
    'required_fields' => ['imei'],

    /*
    |--------------------------------------------------------------------------
    | IMEI validation
    |--------------------------------------------------------------------------
    |
    | Accept 14-17 digit numeric strings (IMEI 15, IMEISV 16, plus slack for
    | check-digit-stripped or padded feeds). Luhn is NOT enforced by default —
    | operator feeds frequently carry technically-invalid-but-real IMEIs.
    */
    'imei_min_digits' => 14,
    'imei_max_digits' => 17,
    'imei_enforce_luhn' => (bool) env('IMPORT_IMEI_LUHN', false),

    /*
    |--------------------------------------------------------------------------
    | Date parsing
    |--------------------------------------------------------------------------
    |
    | Explicit formats tried in order before falling back to Carbon::parse().
    | Excel serial numbers (integer / float days since 1899-12-30) are detected
    | separately.
    */
    'date_formats' => [
        'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd-M-Y', 'd.m.Y',
        'Y/m/d', 'd/m/y', 'm/d/y', 'Y-m-d H:i:s', 'd/m/Y H:i',
    ],
    'date_dmy_preference' => true, // ambiguous NN/NN/NNNN treated as d/m/Y

    /*
    |--------------------------------------------------------------------------
    | Stale batch reaper
    |--------------------------------------------------------------------------
    */
    'stale_after_minutes' => 30,
];
