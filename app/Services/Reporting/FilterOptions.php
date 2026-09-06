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

    /**
     * code => "code — name". Optionally scoped to one distributor so the RT list
     * stays short when an RD is already picked.
     *
     * @return array<string,string>
     */
    public function retailers(?string $rdCode = null): array
    {
        $key = 'filters:rt:'.($rdCode ?: 'all');

        return Cache::remember($key, self::TTL, fn () => DB::table('retailers')
            ->when($rdCode, fn ($q) => $q->where('rd_code', $rdCode))
            ->orderBy('code')
            ->limit(5000)
            ->get(['code', 'name'])
            ->mapWithKeys(fn ($r) => [$r->code => trim($r->code.' — '.($r->name ?? ''), ' —')])
            ->all());
    }

    public function forget(): void
    {
        Cache::forget('filters:tso');
        Cache::forget('filters:model');
        Cache::forget('filters:rd');
        Cache::deleteMultiple(['filters:rt:all']);
    }
}
