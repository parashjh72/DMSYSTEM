<?php

namespace App\Imports;

use App\Support\DateNormalizer;
use App\Support\Imei;

/**
 * Maps one row of an activation file: IMEI + activation date. Marks existing
 * devices activated.
 */
class ActivationRowMapper
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

        $date = DateNormalizer::toDate($this->cell($row, 'activation_date'));
        if ($date === null) {
            throw new RowMapException('invalid_date', 'Activation date is missing or unparseable.');
        }

        return ['imei' => $imei, 'activation_date' => $date];
    }

    private function cell(array $row, string $field): mixed
    {
        $index = $this->columnMap[$field] ?? null;

        return $index === null ? null : ($row[$index] ?? null);
    }
}
