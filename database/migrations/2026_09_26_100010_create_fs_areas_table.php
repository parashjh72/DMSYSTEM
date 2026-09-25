<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('fs_regions')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_areas');
    }
};
