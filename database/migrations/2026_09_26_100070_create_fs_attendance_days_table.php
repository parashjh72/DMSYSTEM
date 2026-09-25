<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per field user per day with the final attendance status, worked out
 * from tso_attendances (left unchanged), leave, holidays and the duty policy.
 * Geofence results are written at punch time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->foreignId('tso_attendance_id')->nullable()->constrained('tso_attendances')->nullOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained('fs_leave_requests')->nullOnDelete();
            $table->string('status', 12)->nullable();            // present | late | half_day | absent | leave | weekly_off | holiday
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedInteger('working_minutes')->nullable();
            $table->boolean('missed_checkout')->default(false);
            $table->string('check_in_geofence', 10)->nullable();  // inside | outside | none
            $table->foreignId('check_in_geofence_id')->nullable()->constrained('fs_geofences')->nullOnDelete();
            $table->unsignedInteger('check_in_distance_metres')->nullable();
            $table->string('check_out_geofence', 10)->nullable();
            $table->foreignId('check_out_geofence_id')->nullable()->constrained('fs_geofences')->nullOnDelete();
            $table->unsignedInteger('check_out_distance_metres')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_attendance_days');
    }
};
