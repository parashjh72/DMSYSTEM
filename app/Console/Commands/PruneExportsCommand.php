<?php

namespace App\Console\Commands;

use App\Models\ExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExportsCommand extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete export files past their retention window';

    public function handle(): int
    {
        $expired = ExportJob::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $export) {
            if ($export->stored_path) {
                Storage::disk($export->disk)->delete($export->stored_path);
            }
            $export->update(['status' => 'expired', 'stored_path' => null]);
        }

        $this->info("Pruned {$expired->count()} expired export(s).");

        return self::SUCCESS;
    }
}
