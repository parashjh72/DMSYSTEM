<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-store promoters ("RA" — retail assistants). Each is attached to one
 * retailer and carries a monthly unit target; achievement is that retailer's
 * activations in the month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('type', 20)->default('real_ra'); // conditional_ra | real_ra
            $table->string('rt_code', 40)->index();
            $table->string('rt_name', 191)->nullable();
            $table->string('rd_code', 40)->nullable()->index();
            $table->unsignedInteger('monthly_target')->default(0);
            $table->boolean('active')->default(true);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoters');
    }
};
