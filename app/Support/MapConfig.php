<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Google Maps JS API key, entered by a Super Admin under Settings → Maps.
 * The key is used client-side (Maps JS API) so it is not a secret — it should be
 * restricted by HTTP referrer in the Google Cloud console. Falls back to
 * GOOGLE_MAPS_API_KEY in .env when nothing is stored.
 */
class MapConfig
{
    public const KEY = 'maps';

    public static function apiKey(): string
    {
        try {
            $stored = (string) (Setting::get(self::KEY)['google_api_key'] ?? '');
        } catch (Throwable) {
            $stored = '';
        }

        return $stored !== '' ? $stored : (string) env('GOOGLE_MAPS_API_KEY', '');
    }

    public static function enabled(): bool
    {
        return static::apiKey() !== '';
    }

    public static function save(string $key): void
    {
        Setting::put(self::KEY, ['google_api_key' => trim($key)]);
    }

    /** Default map centre when a retailer has no coordinates yet. */
    public static function defaultCentre(): array
    {
        return [
            'lat' => (float) env('MAP_DEFAULT_LAT', 28.3949),   // Nepal
            'lng' => (float) env('MAP_DEFAULT_LNG', 84.1240),
        ];
    }
}
