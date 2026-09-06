<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master / lookup tables. Populated (upserted) from each import so the UI can offer
 * filter dropdowns without SELECT DISTINCT over the raw table. The raw table keeps its
 * denormalized name columns, so reports never need to join these — they exist for
 * filtering, master-data screens, and a future FK normalization step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territory_officers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120)->unique();
            $table->timestamps();
        });

        Schema::create('device_models', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('retail_distributors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 40)->unique();
            $table->string('name', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('retailers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 40)->unique();
            $table->string('name', 191)->nullable();
            $table->string('rd_code', 40)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailers');
        Schema::dropIfExists('retail_distributors');
        Schema::dropIfExists('device_models');
        Schema::dropIfExists('territory_officers');
    }
};
