<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Classifies a device model as "running" (currently sold) or "out" (discontinued)
 * by matching its cleaned name against config('models.running_series').
 */
class ModelClassifier
{
    public static function statusFor(string $modelName): string
    {
        $name = self::clean($modelName);

        foreach (config('models.running_series', []) as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return 'running';
            }
        }

        return config('models.default_status', 'out');
    }

    /** Strip the trailing " (8+128GB)" style suffix and any leading brand word. */
    public static function clean(string $name): string
    {
        $name = preg_replace('/\s*\([^)]*\)\s*$/', '', trim($name)) ?? $name;
        $name = preg_replace('/^(realme|narzo)\s+/i', '', $name) ?? $name;

        return trim($name);
    }

    /**
     * Re-classify every row in device_models. Returns [running, out] counts.
     *
     * @return array{running:int, out:int}
     */
    public static function applyAll(): array
    {
        $rows = DB::table('device_models')->select('id', 'name', 'status')->get();

        $running = $out = 0;
        $updates = [];
        foreach ($rows as $row) {
            $status = self::statusFor($row->name);
            $status === 'running' ? $running++ : $out++;
            if ($row->status !== $status) {
                $updates[$status][] = $row->id;
            }
        }

        foreach ($updates as $status => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('device_models')->whereIn('id', $chunk)->update(['status' => $status]);
            }
        }

        return ['running' => $running, 'out' => $out];
    }
}
