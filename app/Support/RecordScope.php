<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Row-level data scoping for the RD-scoped roles (ASM, TSO, RD). A user with
 * `scoped_rd_codes` set may only see sales_activation_records whose `rd_code`
 * is in that list; Super Admin / Admin / NSM are unrestricted.
 *
 * Applied at every read entry point (reports, stock/sellout, quick reports,
 * IMEI search, data explorer, and every export — the scope is frozen into
 * queued export and scheduled-report payloads so the worker applies it too).
 */
class RecordScope
{
    /** @return list<string>|null  null = unrestricted */
    public static function rdCodes(): ?array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'isScoped') || ! $user->isScoped()) {
            return null;
        }

        return $user->scopedRdCodes();
    }

    public static function restricted(): bool
    {
        return static::rdCodes() !== null;
    }

    /**
     * @template T of Builder
     *
     * @param  T  $query
     * @return T
     */
    public static function apply(Builder $query, string $column = 'rd_code'): Builder
    {
        $codes = static::rdCodes();

        return $codes === null ? $query : $query->whereIn($column, $codes);
    }
}
