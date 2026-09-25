<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Places each existing distributor (retail_distributors) in exactly one Area.
 * The distributor table itself is not altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_area_distributors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('fs_areas')->cascadeOnDelete();
            $table->foreignId('retail_distributor_id')->unique()->constrained('retail_distributors')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_area_distributors');
    }
};
