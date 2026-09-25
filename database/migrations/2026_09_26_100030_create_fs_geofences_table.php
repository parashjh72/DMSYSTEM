<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allowed check-in points. A point linked to a distributor applies to every
 * field user scoped to that distributor; an unlinked point (e.g. an office)
 * applies to everyone in its Area, or to everyone when no Area is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_geofences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('retail_distributor_id')->nullable()->constrained('retail_distributors')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('fs_areas')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_metres')->default(200);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_geofences');
    }
};
