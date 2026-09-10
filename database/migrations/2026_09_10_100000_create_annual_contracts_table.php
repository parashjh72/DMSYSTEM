<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annual sales-volume contracts: an admin commits a retailer to a target
 * volume over a period in exchange for an incentive percentage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('rt_code', 40)->index();
            $table->string('rt_name', 191)->nullable();
            $table->string('rd_code', 40)->nullable()->index();
            $table->unsignedInteger('target_volume')->default(0);
            $table->decimal('incentive_pct', 5, 2)->default(0);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 12)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_contracts');
    }
};
