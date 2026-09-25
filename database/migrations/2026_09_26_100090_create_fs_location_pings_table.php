<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GPS points sent by the phone during duty hours. High volume: kept narrow,
 * pruned after field_sales.tracking.retention_days by fs:prune-locations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->timestamp('recorded_at');                    // device time of the fix
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy')->nullable();
            $table->float('speed')->nullable();                  // m/s as reported by the device
            $table->unsignedTinyInteger('battery')->nullable();  // percent
            $table->string('source', 12)->default('tracker');    // tracker | check_in | check_out
            $table->boolean('is_suspect')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_location_pings');
    }
};
