<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical raw data table: exactly one row per IMEI.
 *
 * Column list is the permanent reference format for this module
 * (IMEI, Model, TSO, RD Code, RD Name, RTCode, RT Name, ST Date, Activation, Source)
 * plus audit + generated reporting helpers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_activation_records', function (Blueprint $table) {
            $table->bigIncrements('id');

            // IMEI kept as string: preserves leading zeros, tolerates 16-digit IMEISV
            // and malformed values without silent numeric truncation.
            $table->string('imei', 20);

            $table->string('model', 100)->nullable();
            $table->string('tso', 120)->nullable();
            $table->string('rd_code', 40)->nullable();
            $table->string('rd_name', 191)->nullable();
            $table->string('rt_code', 40)->nullable();
            $table->string('rt_name', 191)->nullable();

            $table->date('st_date')->nullable();          // sell-through transaction date
            $table->date('activation_date')->nullable();   // device activation date

            $table->string('source', 40)->nullable();

            // DB-native reporting helpers — kept on indexes, never computed in PHP.
            $table->smallInteger('activation_days')->nullable()
                ->storedAs('(to_days(`activation_date`) - to_days(`st_date`))');
            $table->boolean('is_activated')
                ->storedAs('(`activation_date` is not null)');

            // Audit / traceability — every record links back to its import batch(es).
            $table->unsignedBigInteger('first_import_batch_id')->nullable();
            $table->unsignedBigInteger('last_import_batch_id')->nullable();

            $table->timestamps();

            // --- Indexes (each justified in docs/ARCHITECTURE.md §2) ---
            $table->unique('imei');                          // instant search + upsert key
            $table->index('st_date');                        // date-wise / range filters
            $table->index('activation_date');                // activation report
            $table->index(['rd_code', 'st_date']);           // RD-wise (+ date), RD-only via prefix
            $table->index(['rt_code', 'st_date']);           // RT-wise
            $table->index(['model', 'st_date']);             // model-wise
            $table->index(['tso', 'st_date']);               // TSO-wise
            $table->index(['is_activated', 'st_date']);      // sold-not-activated trend
            $table->index('activation_days');                // ST->activation lag buckets
            $table->index('last_import_batch_id');           // batch audit / correction
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_activation_records');
    }
};
