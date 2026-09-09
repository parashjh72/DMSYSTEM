<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An RD login's request to pull devices back from a retailer into its own
        // stock. Approved by Admin / Super Admin.
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('rd_code', 40)->index();
            $table->json('imeis');                 // valid IMEIs in this request
            $table->json('rejected')->nullable();  // { imei: reason } skipped at submit time
            $table->unsignedInteger('requested_count')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->string('status', 12)->default('pending')->index(); // pending | approved | rejected
            $table->text('note')->nullable();          // requester's reason
            $table->text('review_note')->nullable();   // reviewer's note
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // Immutable per-device history, shown as a timeline in IMEI Search.
        Schema::create('device_events', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 32);
            $table->string('event', 40);          // return_requested | return_approved | return_rejected | transferred
            $table->string('description', 255);
            $table->json('meta')->nullable();
            $table->string('rd_code', 40)->nullable();
            $table->string('rt_code', 40)->nullable();
            $table->foreignId('caused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['imei', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_events');
        Schema::dropIfExists('return_requests');
    }
};
