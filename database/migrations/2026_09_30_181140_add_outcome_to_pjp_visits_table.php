<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beat-plan visit outcome: an "effective" visit took an order (lines + value),
 * a "non_effective" one records why no order was placed. Visits logged before
 * this existed keep a null outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pjp_visits', function (Blueprint $table) {
            $table->string('outcome', 16)->nullable()->after('accuracy');
            $table->json('order_items')->nullable()->after('outcome');
            $table->decimal('order_value', 14, 2)->nullable()->after('order_items');
            $table->string('no_order_reason', 60)->nullable()->after('order_value');
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::table('pjp_visits', function (Blueprint $table) {
            $table->dropIndex(['outcome']);
            $table->dropColumn(['outcome', 'order_items', 'order_value', 'no_order_reason']);
        });
    }
};
