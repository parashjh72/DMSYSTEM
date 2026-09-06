<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual enrolment of retailers into a scheme, each with a chosen payout plan
 * and category (which sets the minimum slab they must reach to be eligible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheme_retailers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('scheme_id')->constrained('schemes')->cascadeOnDelete();
            $table->string('rt_code', 40);
            $table->string('rt_name', 191)->nullable();

            $table->string('plan', 20)->default('option_one');       // option_one | option_two
            $table->string('category', 20)->default('other');        // ra | conditional_ra | contracted | other
            $table->unsignedTinyInteger('min_slab')->nullable();     // override the category default
            $table->string('note', 191)->nullable();

            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scheme_id', 'rt_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_retailers');
    }
};
