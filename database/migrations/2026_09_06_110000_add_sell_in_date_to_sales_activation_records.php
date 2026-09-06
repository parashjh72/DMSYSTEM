<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sell-In date (ND -> RD). Sits between Activation and Source in the canonical
 * column order. Nullable — not every feed carries it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_activation_records', function (Blueprint $table) {
            $table->date('sell_in_date')->nullable()->after('activation_date');
            $table->index('sell_in_date');
        });

        Schema::table('import_staging_rows', function (Blueprint $table) {
            $table->date('sell_in_date')->nullable()->after('activation_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales_activation_records', function (Blueprint $table) {
            $table->dropIndex(['sell_in_date']);
            $table->dropColumn('sell_in_date');
        });
        Schema::table('import_staging_rows', function (Blueprint $table) {
            $table->dropColumn('sell_in_date');
        });
    }
};
