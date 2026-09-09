<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjp_day_retailers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjp_day_id')->constrained('pjp_days')->cascadeOnDelete();
            $table->string('rt_code', 40);
            $table->string('rt_name', 191)->nullable();
            $table->timestamps();

            $table->unique(['pjp_day_id', 'rt_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjp_day_retailers');
    }
};
