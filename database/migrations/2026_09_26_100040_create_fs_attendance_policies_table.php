<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duty rules. The row with no area is the company default; a row per Area
 * overrides it for field users in that Area.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->unique()->constrained('fs_areas')->cascadeOnDelete();
            $table->time('duty_start');
            $table->time('duty_end');
            $table->unsignedSmallInteger('late_grace_minutes')->default(15);
            $table->unsignedSmallInteger('half_day_below_minutes')->default(240);
            $table->json('weekly_off');
            $table->string('geofence_mode', 10)->default('flag'); // off | flag | block
            $table->unsignedSmallInteger('ping_interval_minutes')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_attendance_policies');
    }
};
