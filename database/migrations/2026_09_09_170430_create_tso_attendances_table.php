<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per field user per working day: GPS check-in / check-out captured from
 * the browser, with working duration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tso_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');

            $table->timestamp('check_in_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->float('check_in_accuracy')->nullable();
            $table->string('check_in_address', 255)->nullable();

            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->float('check_out_accuracy')->nullable();
            $table->string('check_out_address', 255)->nullable();

            $table->unsignedInteger('working_minutes')->nullable();
            $table->string('status', 16)->default('checked_in'); // checked_in | checked_out
            $table->timestamps();

            $table->unique(['user_id', 'attendance_date']);
            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tso_attendances');
    }
};
