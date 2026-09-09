<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TSO-raised request for a promoter (RA) at a retailer, routed for approval:
 * TSO submits -> ASM approves -> NSM approves -> a Promoter row is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoter_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('type', 20);                 // conditional_ra | real_ra
            $table->string('rt_code', 40)->index();
            $table->string('rt_name', 191)->nullable();
            $table->string('rd_code', 40)->index();
            $table->string('promoter_name', 120)->nullable();  // proposed person (optional)
            $table->unsignedInteger('proposed_target')->default(0);
            $table->json('sales_snapshot');             // last 3 months, frozen at submit time
            $table->text('note')->nullable();

            // pending_asm | pending_nsm | approved | rejected
            $table->string('status', 16)->default('pending_asm')->index();
            $table->string('rejected_stage', 8)->nullable();   // asm | nsm

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asm_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('asm_at')->nullable();
            $table->text('asm_note')->nullable();
            $table->foreignId('nsm_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('nsm_at')->nullable();
            $table->text('nsm_note')->nullable();
            $table->foreignId('promoter_id')->nullable()->constrained('promoters')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoter_requests');
    }
};
