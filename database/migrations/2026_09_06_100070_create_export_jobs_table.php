<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();

            $table->string('type', 40);              // records | rd_report | rt_report | ...
            $table->string('format', 10)->default('csv'); // csv | xlsx
            $table->json('filters')->nullable();

            $table->string('status', 15)->default('pending'); // pending | processing | completed | failed
            $table->unsignedBigInteger('total_rows')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);

            $table->string('disk', 40)->default('local');
            $table->string('stored_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
