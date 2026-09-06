<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();                 // public identifier for URLs / polling

            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('disk', 40)->default('local');
            $table->string('file_type', 10);               // csv | xlsx
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64)->nullable()->index(); // sha256 — detect re-upload

            $table->json('column_map')->nullable();         // resolved header -> field

            $table->string('import_mode', 20)->default('upsert');
            // insert_new | skip_existing | update_existing | upsert
            $table->string('duplicate_strategy', 20)->default('update');
            // skip | update | error

            $table->string('status', 25)->default('pending')->index();
            // pending | queued | processing | completed | completed_with_errors | failed | cancelled

            $table->unsignedBigInteger('chunk_size')->default(5000);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->unsignedInteger('completed_chunks')->default(0);

            // Counters — summed from import_batch_chunks at finalize.
            $table->unsignedBigInteger('total_rows')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('valid_rows')->default(0);
            $table->unsignedBigInteger('invalid_rows')->default(0);
            $table->unsignedBigInteger('inserted_rows')->default(0);
            $table->unsignedBigInteger('updated_rows')->default(0);
            $table->unsignedBigInteger('skipped_rows')->default(0);
            $table->unsignedBigInteger('duplicate_rows')->default(0); // duplicate IMEI within the file
            $table->unsignedBigInteger('failed_rows')->default(0);

            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
