<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated reporting tables. Rebuilt incrementally at import finalize (only the
 * affected keys) and fully via `php artisan reports:rebuild-summaries`. The dashboard
 * and standard reports read ONLY these — response time is independent of raw table size.
 *
 * Lag buckets: d0 (0 days), d1_7, d8_15, d16_30, d31_plus. "activated" = has an
 * activation_date; "not_activated" = total - activated.
 */
return new class extends Migration
{
    private function bucketColumns(Blueprint $table): void
    {
        $table->unsignedBigInteger('total_imei')->default(0);
        $table->unsignedBigInteger('activated')->default(0);
        $table->unsignedBigInteger('not_activated')->default(0);
        $table->unsignedBigInteger('lag_d0')->default(0);
        $table->unsignedBigInteger('lag_d1_7')->default(0);
        $table->unsignedBigInteger('lag_d8_15')->default(0);
        $table->unsignedBigInteger('lag_d16_30')->default(0);
        $table->unsignedBigInteger('lag_d31_plus')->default(0);
        $table->timestamp('recalculated_at')->nullable();
    }

    public function up(): void
    {
        Schema::create('daily_activation_summary', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('st_date');
            $table->string('model', 100)->default('');
            $this->bucketColumns($table);
            $table->unique(['st_date', 'model']);
            $table->index('st_date');
        });

        Schema::create('rd_summary', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rd_code', 40);
            $table->string('rd_name', 191)->nullable();
            $this->bucketColumns($table);
            $table->unique('rd_code');
        });

        Schema::create('rt_summary', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rt_code', 40);
            $table->string('rt_name', 191)->nullable();
            $table->string('rd_code', 40)->nullable()->index();
            $table->string('rd_name', 191)->nullable();
            $this->bucketColumns($table);
            $table->unique('rt_code');
        });

        Schema::create('model_summary', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('model', 100);
            $this->bucketColumns($table);
            $table->unique('model');
        });

        Schema::create('tso_summary', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tso', 120);
            $this->bucketColumns($table);
            $table->unique('tso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tso_summary');
        Schema::dropIfExists('model_summary');
        Schema::dropIfExists('rt_summary');
        Schema::dropIfExists('rd_summary');
        Schema::dropIfExists('daily_activation_summary');
    }
};
