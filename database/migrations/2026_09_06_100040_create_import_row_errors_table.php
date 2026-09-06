<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bad rows never abort a million-row import — they land here for later download.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_row_errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('chunk_number')->nullable();
            $table->unsignedBigInteger('row_number')->nullable();

            $table->string('error_type', 30);
            // missing_imei | invalid_imei | duplicate_in_file | invalid_date |
            // validation | db_error | other
            $table->string('error_message', 500);
            $table->json('row_payload')->nullable(); // the raw mapped row for context

            $table->timestamp('created_at')->nullable();

            $table->index(['import_batch_id', 'error_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_row_errors');
    }
};
