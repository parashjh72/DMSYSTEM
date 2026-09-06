<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Effective-dated price list per device model.
 *
 * Each row = "from this date onward, this model costs this much". The next row's
 * effective_from implicitly ends the previous price. A value report prices every
 * device by the row that was in effect on the device's activation (or ST) date,
 * so a report spanning a price change blends the old and new rates correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('model', 100);
            $table->decimal('price', 12, 2);
            $table->date('effective_from');
            $table->string('note', 191)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['model', 'effective_from']);
            $table->index('model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_prices');
    }
};
