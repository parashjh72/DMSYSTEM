<?php

namespace App\Imports;

use App\Support\DateNormalizer;
use App\Support\Imei;

/**
 * Maps one row of a sell-through (RD -> RT) file: IMEI, RD code, RT code,
 * invoice date (stored as st_date). Assigns retailers to distributor stock.
 */
class SellThroughRowMapper
{
    /** @param array<string,int> $columnMap canonical field => 0-based column index */
    public function __construct(private readonly array $columnMap) {}

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     *
     * @throws RowMapException
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

        $rtCode = trim((string) $this->cell($row, 'rt_code'));
        if ($rtCode === '') {
            throw new RowMapException('validation', 'RT code is required for a sell-through row.');
        }

        $stDate = DateNormalizer::toDate($this->cell($row, 'st_date'));
        if ($stDate === null) {
            throw new RowMapException('invalid_date', 'Invoice / ST date is missing or unparseable.');
        }

        $rdCode = trim((string) $this->cell($row, 'rd_code'));
        $model = trim((string) $this->cell($row, 'model'));

        return [
            'imei' => $imei,
            'model' => $model === '' ? null : mb_substr($model, 0, 100),
            'rd_code' => $rdCode === '' ? null : mb_substr($rdCode, 0, 40),
            'rt_code' => mb_substr($rtCode, 0, 40),
            'st_date' => $stDate,
        ];
    }

    private function cell(array $row, string $field): mixed
    {
        $index = $this->columnMap[$field] ?? null;

        return $index === null ? null : ($row[$index] ?? null);
    }
}
