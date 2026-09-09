<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A TSO's monthly Planned Journey Plan. Approval chain frozen onto the row at
 * submit: TSO owns it, ASM reviews, NSM gives final approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tso_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asm_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('nsm_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('planned_days')->default(0);
            $table->unsignedInteger('planned_visits')->default(0);
            $table->unsignedInteger('revision_count')->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('asm_reviewed_at')->nullable();
            $table->timestamp('asm_approved_at')->nullable();
            $table->timestamp('forwarded_to_nsm_at')->nullable();
            $table->timestamp('nsm_reviewed_at')->nullable();
            $table->timestamp('final_approved_at')->nullable();
            $table->foreignId('final_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();

            $table->timestamps();

            $table->unique(['tso_id', 'year', 'month']);
            $table->index('status');
            $table->index(['asm_id', 'status']);
            $table->index(['nsm_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjps');
    }
};
