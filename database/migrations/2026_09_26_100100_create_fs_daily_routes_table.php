<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user daily travel summary built from fs_location_pings: distance,
 * idle time and a simplified route for playback. Travel detail only — no
 * allowance amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_daily_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('route_date');
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('ping_count')->default(0);
            $table->unsignedInteger('kept_count')->default(0);
            $table->unsignedInteger('idle_minutes')->default(0);
            $table->timestamp('first_ping_at')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->json('path')->nullable();                    // [[lat, lng, unix_ts], …]
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'route_date']);
            $table->index('route_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_daily_routes');
    }
};
