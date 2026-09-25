<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Admin-editable Field Sales settings (Field Sales Setup → Policies), stored in
 * the settings table and falling back to config/field_sales.php.
 */
class FieldSalesConfig
{
    public const KEY = 'field_sales';

    /** @return array{daily_summary_enabled: bool, daily_summary_time: string, daily_summary_last_sent_on: ?string} */
    public static function all(): array
    {
        try {
            $stored = (array) Setting::get(self::KEY, []);
        } catch (Throwable) {
            $stored = [];
        }

        return [
            'daily_summary_enabled' => (bool) ($stored['daily_summary_enabled'] ?? config('field_sales.daily_summary.enabled')),
            'daily_summary_time' => (string) ($stored['daily_summary_time'] ?? config('field_sales.daily_summary.time')),
            'daily_summary_last_sent_on' => $stored['daily_summary_last_sent_on'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $values */
    public static function save(array $values): void
    {
        Setting::put(self::KEY, array_merge(static::all(), $values));
    }
}
