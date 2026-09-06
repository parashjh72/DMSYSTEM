<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunk-level progress ledger. Enables fail-safe resume: if a worker dies mid-import,
 * completed chunks stay completed and only pending/failed chunks are retried. Every
 * chunk apply is idempotent, so a retry cannot double-insert into the raw table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batch_chunks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();

            $table->unsignedInteger('chunk_number');
            $table->unsignedBigInteger('start_row');   // 1-based data row (excludes header)
            $table->unsignedBigInteger('end_row');

            $table->string('status', 15)->default('pending'); // pending | processing | completed | failed
            $table->unsignedInteger('attempts')->default(0);

            $table->unsignedBigInteger('read_rows')->default(0);
            $table->unsignedBigInteger('inserted')->default(0);
            $table->unsignedBigInteger('updated')->default(0);
            $table->unsignedBigInteger('skipped')->default(0);
            $table->unsignedBigInteger('duplicates')->default(0);
            $table->unsignedBigInteger('invalid')->default(0);
            $table->unsignedBigInteger('failed')->default(0);

            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'chunk_number']);
            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_chunks');
    }
};
