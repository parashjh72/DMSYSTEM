<?php

namespace App\Console\Commands;

use App\Services\Reporting\FilterOptions;
use App\Support\ModelClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild the master tables (distributors, retailers, models, TSOs) from the
 * canonical data in sales_activation_records. A retailer's distributor is the
 * one it has the most records under (not MAX(rd_code), which one stray row can
 * skew). Use this to repair drift.
 */
class ResyncMasterDataCommand extends Command
{
    protected $signature = 'masterdata:resync {--prune : delete master rows no longer present in the data}';

    protected $description = 'Rebuild distributor / retailer / model / TSO master tables from the records';

    public function handle(FilterOptions $filters): int
    {
        DB::statement("
            INSERT INTO territory_officers (name, created_at, updated_at)
            SELECT DISTINCT tso, NOW(), NOW() FROM sales_activation_records
            WHERE tso IS NOT NULL AND tso <> ''
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");

        DB::statement("
            INSERT INTO device_models (name, created_at, updated_at)
            SELECT DISTINCT model, NOW(), NOW() FROM sales_activation_records
            WHERE model IS NOT NULL AND model <> ''
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");

        DB::statement("
            INSERT INTO retail_distributors (code, name, created_at, updated_at)
            SELECT rd_code, name, NOW(), NOW() FROM (
                SELECT rd_code,
                       SUBSTRING_INDEX(GROUP_CONCAT(rd_name ORDER BY cnt DESC), ',', 1) AS name
                FROM (
                    SELECT rd_code, rd_name, COUNT(*) cnt FROM sales_activation_records
                    WHERE rd_code IS NOT NULL AND rd_code <> ''
                    GROUP BY rd_code, rd_name
                ) a GROUP BY rd_code
            ) b
            ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = NOW()
        ");

        // retailer -> the distributor it has the most records under
        DB::statement("
            INSERT INTO retailers (code, name, rd_code, created_at, updated_at)
            SELECT rt_code, rt_name, rd_code, NOW(), NOW() FROM (
                SELECT rt_code, rd_code, rt_name,
                       ROW_NUMBER() OVER (PARTITION BY rt_code ORDER BY cnt DESC, rd_code) rn
                FROM (
                    SELECT rt_code, rd_code, MAX(rt_name) rt_name, COUNT(*) cnt
                    FROM sales_activation_records
                    WHERE rt_code IS NOT NULL AND rt_code <> ''
                    GROUP BY rt_code, rd_code
                ) a
            ) b WHERE rn = 1
            ON DUPLICATE KEY UPDATE name = VALUES(name), rd_code = VALUES(rd_code), updated_at = NOW()
        ");

        if ($this->option('prune')) {
            foreach ([
                ['retailers', 'code', 'rt_code'],
                ['retail_distributors', 'code', 'rd_code'],
                ['device_models', 'name', 'model'],
                ['territory_officers', 'name', 'tso'],
            ] as [$table, $key, $col]) {
                $n = DB::table($table)->whereNotIn($key, fn ($q) => $q
                    ->select($col)->from('sales_activation_records')->whereNotNull($col)->where($col, '<>', ''))
                    ->delete();
                if ($n) {
                    $this->line("  pruned {$n} from {$table}");
                }
            }
        }

        ModelClassifier::applyAll();
        $filters->forget();

        $this->info('Master data resynced from records.');

        return self::SUCCESS;
    }
}
