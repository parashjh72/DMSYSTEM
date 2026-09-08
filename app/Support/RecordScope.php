<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Row-level data scoping for TSO users. A user with `scoped_tsos` set may only
 * see sales_activation_records whose `tso` is in that list; everyone else is
 * unrestricted. Applied at every read entry point (reports, stock/sellout,
 * quick reports, IMEI search, record exports).
 */
class RecordScope
{
    /** @return list<string>|null  null = unrestricted */
    public static function tsos(): ?array
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'isTsoScoped') || ! $user->isTsoScoped()) {
            return null;
        }

        return $user->scopedTsos();
    }

    public static function restricted(): bool
    {
        return static::tsos() !== null;
    }

    /**
     * @template T of Builder
     *
     * @param  T  $query
     * @return T
     */
    public static function apply(Builder $query, string $column = 'tso'): Builder
    {
        $tsos = static::tsos();

        return $tsos === null ? $query : $query->whereIn($column, $tsos);
    }
}
