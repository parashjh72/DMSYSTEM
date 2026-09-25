<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('name', 120);
            $table->foreignId('region_id')->nullable()->constrained('fs_regions')->cascadeOnDelete(); // null = every region
            $table->timestamps();

            $table->unique(['holiday_date', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_holidays');
    }
};
