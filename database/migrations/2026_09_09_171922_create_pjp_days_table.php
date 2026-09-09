<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjp_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjp_id')->constrained('pjps')->cascadeOnDelete();
            $table->date('plan_date');
            $table->string('day_status', 20)->default('no_plan'); // planned | leave | weekly_off | holiday | no_plan
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pjp_id', 'plan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjp_days');
    }
};
