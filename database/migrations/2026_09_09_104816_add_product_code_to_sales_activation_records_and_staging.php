<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_activation_records', function (Blueprint $table) {
            $table->string('product_code', 60)->nullable()->after('model')->index();
        });

        Schema::table('import_staging_rows', function (Blueprint $table) {
            $table->string('product_code', 60)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('sales_activation_records', function (Blueprint $table) {
            $table->dropIndex(['product_code']);
            $table->dropColumn('product_code');
        });

        Schema::table('import_staging_rows', function (Blueprint $table) {
            $table->dropColumn('product_code');
        });
    }
};
