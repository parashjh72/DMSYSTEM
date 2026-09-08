<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;

/**
 * Resolves a relative period key (config('reports.periods')) into an inclusive
 * [from, to] date pair, evaluated in the reports timezone.
 */
class ReportPeriod
{
    /**
     * @return array{0: ?string, 1: ?string} [from, to] as Y-m-d, or [null, null] for "none"
     */
    public static function resolve(string $key, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('reports.timezone'));
        $today = $now->startOfDay();

        return match ($key) {
            'yesterday' => [
                $today->subDay()->toDateString(),
                $today->subDay()->toDateString(),
            ],
            'last_7_days' => [
                $today->subDays(7)->toDateString(),
                $today->subDay()->toDateString(),
            ],
            'last_30_days' => [
                $today->subDays(30)->toDateString(),
                $today->subDay()->toDateString(),
            ],
            'last_month' => [
                $today->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'month_to_date' => [
                $today->startOfMonth()->toDateString(),
                $today->toDateString(),
            ],
            default => [null, null], // 'none'
        };
    }

    public static function label(string $key): string
    {
        return config("reports.periods.{$key}", $key);
    }
}
