<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Immutable approval history for a PJP (mirrors device_events). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjp_id')->constrained('pjps')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['pjp_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjp_events');
    }
};
