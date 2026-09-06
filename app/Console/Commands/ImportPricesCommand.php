<?php

namespace App\Console\Commands;

use App\Models\ModelPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-load a model price list: a CSV / TSV where each line is
 *   <model name><tab or comma><price>
 * Prices may carry thousands separators (12,856 or Indian 1,04,760).
 *
 *   php artisan prices:import prices.tsv --from=2020-01-01 --note="Launch list"
 *
 * Model names are matched to device_models (exact, then case-insensitive,
 * then whitespace-normalised); unmatched rows are reported, not guessed.
 */
class ImportPricesCommand extends Command
{
    protected $signature = 'prices:import
        {file : path to the price list}
        {--from= : effective-from date (default: 2000-01-01, i.e. the baseline price)}
        {--note= : note stored on each price row}
        {--dry : show what would happen without writing}';

    protected $description = 'Load a model => price list into model_prices';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $from = $this->option('from') ?: '2000-01-01';
        $note = $this->option('note');
        $dry = (bool) $this->option('dry');

        $models = DB::table('device_models')->pluck('name');
        $byExact = $models->mapWithKeys(fn ($n) => [$n => $n]);
        $byLower = $models->mapWithKeys(fn ($n) => [mb_strtolower($n) => $n]);
        $byNorm = $models->mapWithKeys(fn ($n) => [$this->norm($n) => $n]);

        $matched = 0;
        $unmatched = [];
        $rows = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            // split on the last tab or comma-then-digits: model | price
            if (! preg_match('/^(.*?)[\t,]\s*([\d,]+(?:\.\d+)?)\s*$/', $line, $m)) {
                continue;
            }
            $rawName = trim($m[1]);
            if ($rawName === '' || str_contains($rawName, '#NAME')) {
                continue;
            }
            $price = (float) str_replace(',', '', $m[2]);

            $model = $byExact[$rawName]
                ?? $byLower[mb_strtolower($rawName)]
                ?? $byNorm[$this->norm($rawName)]
                ?? null;

            if ($model === null) {
                $unmatched[] = "{$rawName} = {$price}";

                continue;
            }

            $matched++;
            $rows[] = compact('model', 'price');
        }

        $this->info("Matched {$matched} model(s); ".count($unmatched).' unmatched.');
        if ($unmatched) {
            $this->warn("Unmatched (no device_models row):\n  ".implode("\n  ", $unmatched));
        }

        if ($dry || $rows === []) {
            return self::SUCCESS;
        }

        foreach ($rows as $r) {
            ModelPrice::updateOrCreate(
                ['model' => $r['model'], 'effective_from' => $from],
                ['price' => $r['price'], 'note' => $note, 'created_by' => null],
            );
        }

        $this->info("Wrote {$matched} price row(s) effective {$from}.");

        return self::SUCCESS;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/^(offline\s+realme|offline|realme|narzo)\s+/', '', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $s)) ?? '');
    }
}
