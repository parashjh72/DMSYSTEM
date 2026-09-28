<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * The import pipeline uses MySQL-only SQL (UPDATE ... JOIN, <=>). Tests using it
 * are skipped on the default SQLite run; run them with:
 * DB_CONNECTION=mysql DB_DATABASE=dmsystem_testing php artisan test
 */
trait RequiresMysql
{
    protected function skipUnlessMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs MySQL: DB_CONNECTION=mysql DB_DATABASE=dmsystem_testing php artisan test');
        }
    }
}
