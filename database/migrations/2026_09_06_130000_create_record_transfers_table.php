<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for manual IMEI / retailer transfers (Settings → Transfer).
 * Every reassignment of records to a different retailer / distributor is logged
 * here for traceability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();

            $table->string('mode', 20);              // imei_list | retailer
            $table->boolean('only_in_stock')->default(false); // retailer mode: skip activated units
            $table->boolean('move_distributor')->default(false);

            $table->string('from_rt_code', 40)->nullable();
            $table->string('from_rd_code', 40)->nullable();
            $table->string('to_rt_code', 40)->nullable();
            $table->string('to_rt_name', 191)->nullable();
            $table->string('to_rd_code', 40)->nullable();
            $table->string('to_rd_name', 191)->nullable();

            $table->unsignedBigInteger('requested_count')->default(0); // IMEIs asked for
            $table->unsignedBigInteger('affected_count')->default(0);  // records actually updated
            $table->json('imeis')->nullable();       // affected IMEIs (capped) for imei_list mode
            $table->json('not_found')->nullable();    // requested IMEIs that matched nothing

            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['from_rt_code']);
            $table->index(['to_rt_code']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_transfers');
    }
};
