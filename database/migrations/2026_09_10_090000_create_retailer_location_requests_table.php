<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A TSO gets one automatic shot at a retailer's coordinates — the first visit
 * check-in stamps them. Any later change goes through this request queue for an
 * Admin to approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retailer_location_requests', function (Blueprint $table) {
            $table->id();
            $table->string('rt_code', 40)->index();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('proposed_latitude', 10, 7);
            $table->decimal('proposed_longitude', 10, 7);
            $table->decimal('previous_latitude', 10, 7)->nullable();
            $table->decimal('previous_longitude', 10, 7)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 12)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_location_requests');
    }
};
