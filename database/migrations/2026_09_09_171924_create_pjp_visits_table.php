<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actual retailer visit logged by the TSO against a PJP day, with GPS. Feeds the
 * planned-vs-actual achievement figure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjp_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjp_day_id')->nullable()->constrained('pjp_days')->cascadeOnDelete();
            $table->foreignId('pjp_id')->nullable()->constrained('pjps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rt_code', 40);
            $table->timestamp('visited_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('accuracy')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['pjp_day_id', 'rt_code']);
            $table->index(['pjp_id', 'rt_code']);
            $table->index(['user_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjp_visits');
    }
};
