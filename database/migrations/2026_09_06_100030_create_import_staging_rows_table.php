<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-chunk landing zone. A chunk job bulk-inserts its validated rows here, then a
 * single INSERT..SELECT..ON DUPLICATE KEY UPDATE moves them into
 * sales_activation_records. Rows are keyed by (import_batch_id, row_number) so a
 * chunk retry overwrites its own staging rows rather than duplicating them.
 *
 * Kept intentionally lean and un-indexed beyond the natural key: it is written in
 * bulk, read once via a full-range scan per chunk, then deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_staging_rows', function (Blueprint $table) {
            $table->unsignedBigInteger('import_batch_id');
            $table->unsignedBigInteger('row_number');   // 1-based source data row

            $table->string('imei', 20);
            $table->string('model', 100)->nullable();
            $table->string('tso', 120)->nullable();
            $table->string('rd_code', 40)->nullable();
            $table->string('rd_name', 191)->nullable();
            $table->string('rt_code', 40)->nullable();
            $table->string('rt_name', 191)->nullable();
            $table->date('st_date')->nullable();
            $table->date('activation_date')->nullable();
            $table->string('source', 40)->nullable();

            $table->primary(['import_batch_id', 'row_number']);
            $table->index(['import_batch_id', 'imei']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging_rows');
    }
};
