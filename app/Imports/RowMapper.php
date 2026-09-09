<?php

namespace App\Imports;

use App\Support\DateNormalizer;
use App\Support\Imei;

/**
 * Turns one raw spreadsheet row into a normalised record array ready for staging.
 * Pure, side-effect free, cheap — safe to run for every row inside a chunk job.
 */
class RowMapper
{
    private const TEXT_FIELDS = ['model', 'product_code', 'tso', 'rd_code', 'rd_name', 'rt_code', 'rt_name', 'source'];

    private const TEXT_LIMITS = [
        'model' => 100, 'product_code' => 60, 'tso' => 120, 'rd_code' => 40, 'rd_name' => 191,
        'rt_code' => 40, 'rt_name' => 191, 'source' => 40,
    ];

    /**
     * @param  array<string,int>  $columnMap  canonical field => 0-based column index
     */
    public function __construct(private readonly array $columnMap) {}

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed> normalised, keyed by DB column
     *
     * @throws RowMapException on missing / invalid IMEI (row is unusable)
     */
    public function map(array $row): array
    {
        $imei = Imei::clean($this->cell($row, 'imei'));

        if ($imei === '') {
            throw new RowMapException('missing_imei', 'IMEI cell is empty.');
        }
        if (! Imei::isValid($imei)) {
            throw new RowMapException('invalid_imei', "IMEI '{$imei}' is not a valid 14-17 digit value.");
        }

        $record = ['imei' => $imei];

        foreach (self::TEXT_FIELDS as $field) {
            $value = trim((string) $this->cell($row, $field));
            $record[$field] = $value === '' ? null : mb_substr($value, 0, self::TEXT_LIMITS[$field]);
        }

        $record['st_date'] = DateNormalizer::toDate($this->cell($row, 'st_date'));
        $record['activation_date'] = DateNormalizer::toDate($this->cell($row, 'activation_date'));
        $record['sell_in_date'] = DateNormalizer::toDate($this->cell($row, 'sell_in_date'));

        return $record;
    }

    private function cell(array $row, string $field): mixed
    {
        $index = $this->columnMap[$field] ?? null;

        return $index === null ? null : ($row[$index] ?? null);
    }
}
