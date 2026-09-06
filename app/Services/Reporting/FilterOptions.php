<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dropdown option lists for report / explorer filters, sourced from the master
 * tables (kept in sync by every import) so we never SELECT DISTINCT the raw
 * table. Cached briefly; the sets only change on import.
 */
class FilterOptions
{
    private const TTL = 300;

    /** @return list<string> */
    public function tso(): array
    {
        return Cache::remember('filters:tso', self::TTL, fn () => DB::table('territory_officers')
            ->orderBy('name')->pluck('name')->all());
    }

    /** @return list<string> */
    public function models(): array
    {
        return Cache::remember('filters:model', self::TTL, fn () => DB::table('device_models')
            ->orderBy('name')->pluck('name')->all());
    }

    /** @return array<string,string> code => "code — name" */
    public function distributors(): array
    {
        return Cache::remember('filters:rd', self::TTL, fn () => DB::table('retail_distributors')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->mapWithKeys(fn ($r) => [$r->code => trim($r->code.' — '.($r->name ?? ''), ' —')])
            ->all());
    }

    /** Hard ceiling on how many RT options are handed to a <select>. */
    public const RT_LIMIT = 200;

    /**
     * code => "code — name". Optionally scoped to one distributor, and/or filtered
     * by a search term (matches RT code or name). Capped at RT_LIMIT rows.
     *
     * A search query is not cached (open-ended); the unfiltered list per RD is.
     *
     * @return array{options: array<string,string>, truncated: bool}
     */
    public function retailers(?string $rdCode = null, ?string $search = null): array
    {
        $search = $search !== null ? trim($search) : null;

        $build = function () use ($rdCode, $search) {
            $rows = DB::table('retailers')
                ->when($rdCode, fn ($q) => $q->where('rd_code', $rdCode))
                ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w
                    ->where('code', 'like', $s.'%')
                    ->orWhere('name', 'like', '%'.$s.'%')))
                ->orderBy('code')
                ->limit(self::RT_LIMIT + 1)
                ->get(['code', 'name']);

            return [
                'options' => $rows->take(self::RT_LIMIT)
                    ->mapWithKeys(fn ($r) => [$r->code => trim($r->code.' — '.($r->name ?? ''), ' —')])
                    ->all(),
                'truncated' => $rows->count() > self::RT_LIMIT,
            ];
        };

        if ($search !== null && $search !== '') {
            return $build();
        }

        return Cache::remember('filters:rt:'.($rdCode ?: 'all'), self::TTL, $build);
    }

    public function retailerLabel(string $code): string
    {
        $name = DB::table('retailers')->where('code', $code)->value('name');

        return trim($code.' — '.($name ?? ''), ' —');
    }

    /**
     * Resolve pasted retailer codes / names to RT codes.
     *
     * @param  list<string>  $tokens
     * @return array{matched: list<string>, unmatched: list<string>}
     */
    public function matchRetailers(array $tokens, ?string $rdCode = null): array
    {
        $matched = [];
        $unmatched = [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            $code = DB::table('retailers')
                ->when($rdCode, fn ($q) => $q->where('rd_code', $rdCode))
                ->where(fn ($q) => $q
                    ->where('code', $token)
                    ->orWhere('name', $token)
                    ->orWhere('name', 'like', '%'.$token.'%'))
                ->orderByRaw('CASE WHEN code = ? THEN 0 WHEN name = ? THEN 1 ELSE 2 END', [$token, $token])
                ->value('code');

            if ($code !== null) {
                $matched[$code] = $code;
            } else {
                $unmatched[] = $token;
            }
        }

        return ['matched' => array_values($matched), 'unmatched' => $unmatched];
    }

    public function forget(): void
    {
        Cache::forget('filters:tso');
        Cache::forget('filters:model');
        Cache::forget('filters:rd');
        Cache::deleteMultiple(['filters:rt:all']);
    }
}
