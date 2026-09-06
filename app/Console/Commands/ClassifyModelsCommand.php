<?php

namespace App\Console\Commands;

use App\Support\ModelClassifier;
use Illuminate\Console\Command;

class ClassifyModelsCommand extends Command
{
    protected $signature = 'models:classify';

    protected $description = 'Mark each device model running / out from config/models.php';

    public function handle(): int
    {
        $counts = ModelClassifier::applyAll();

        $this->info("Running: {$counts['running']}   Out: {$counts['out']}");

        return self::SUCCESS;
    }
}
