<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retailer incentive schemes. A scheme pays a % of a retailer's qualified
 * sell-out value (device price summed over qualifying-model activations in the
 * scheme period), stepped by target-value slabs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->date('effective_from');
            $table->date('effective_to');

            // which device date counts as "sell-out"
            $table->string('sellout_basis', 20)->default('activation_date'); // activation_date | st_date
            // which models qualify: running | out | all
            $table->string('qualified_models', 10)->default('running');

            $table->string('status', 15)->default('draft'); // draft | active | closed
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('scheme_slabs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('scheme_id')->constrained('schemes')->cascadeOnDelete();
            $table->unsignedInteger('slab_no');
            $table->string('label', 80)->nullable();        // "3 Lakh - 499999"
            $table->decimal('min_value', 14, 2);
            $table->decimal('max_value', 14, 2)->nullable(); // null = "& above"
            $table->decimal('payout_percent', 5, 2);
            $table->string('reward', 191)->nullable();       // "option two"
            $table->timestamps();

            $table->unique(['scheme_id', 'slab_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_slabs');
        Schema::dropIfExists('schemes');
    }
};
