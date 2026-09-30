<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Background GPS breadcrumbs sent by a field user's browser while they are
 * checked in. Feeds the manager's live map, the day's route path and the
 * distance travelled. `segment_metres` is set only on "anchor" pings — the ones
 * far enough from the previous anchor to count as real movement — so standing
 * still with GPS jitter adds nothing to the distance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tso_attendance_id')->constrained('tso_attendances')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy')->nullable();
            $table->float('speed')->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->float('segment_metres')->nullable();
            $table->timestamps();

            $table->index(['tso_attendance_id', 'recorded_at']);
            $table->index(['user_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_pings');
    }
};
