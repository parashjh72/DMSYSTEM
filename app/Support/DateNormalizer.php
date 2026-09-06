<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Throwable;

/**
 * Normalises the many date shapes an operator feed can carry into a Y-m-d string.
 * Dates are always stored in DATE columns, never VARCHAR (§18).
 */
class DateNormalizer
{
    /**
     * @return string|null Y-m-d, or null when blank / unparseable
     */
    public static function toDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // OpenSpout hands back DateTime for real Excel date cells.
        if ($value instanceof DateTimeImmutable || $value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if ($raw === '' || in_array(strtolower($raw), ['null', 'na', 'n/a', '-', '0'], true)) {
            return null;
        }

        // Excel serial (days since 1899-12-30). Guard the plausible business range
        // ~2001-09 (37500) .. ~2035 (49500) so a bare year like "2025" is not eaten.
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $raw)) {
            $serial = (float) $raw;
            if ($serial >= 20000 && $serial <= 60000) {
                $base = new DateTimeImmutable('1899-12-30');

                return $base->modify('+'.(int) floor($serial).' days')->format('Y-m-d');
            }
        }

        foreach (config('import.date_formats', []) as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $raw);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        // Ambiguous N/N/NNNN — honour the configured d/m/Y preference.
        if (config('import.date_dmy_preference', true)
            && preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$#', $raw, $m)) {
            [$d, $mth, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($d <= 31 && $mth <= 12) {
                $y = $y < 100 ? 2000 + $y : $y;
                if (checkdate($mth, $d, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $mth, $d);
                }
            }
        }

        try {
            return CarbonImmutable::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
