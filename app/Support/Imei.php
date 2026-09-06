<?php

namespace App\Support;

class Imei
{
    /**
     * Strip spaces, hyphens and any trailing ".0" that a numeric-typed cell adds.
     */
    public static function clean(mixed $value): string
    {
        $raw = trim((string) $value);
        $raw = preg_replace('/\.0+$/', '', $raw) ?? $raw;

        return preg_replace('/[\s\-]+/', '', $raw) ?? '';
    }

    public static function isValid(string $imei): bool
    {
        $min = (int) config('import.imei_min_digits', 14);
        $max = (int) config('import.imei_max_digits', 17);

        if (! preg_match('/^\d{'.$min.','.$max.'}$/', $imei)) {
            return false;
        }

        if (config('import.imei_enforce_luhn', false) && strlen($imei) === 15) {
            return self::luhnValid($imei);
        }

        return true;
    }

    public static function luhnValid(string $number): bool
    {
        $sum = 0;
        $digits = strrev($number);
        foreach (str_split($digits) as $i => $d) {
            $d = (int) $d;
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return $sum % 10 === 0;
    }
}
