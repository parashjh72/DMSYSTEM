<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field attributes for retailers, used by the PJP retailer picker and the
 * attendance / visit map. All nullable — populated by hand in Master Data or a
 * later import; nothing existing depends on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retailers', function (Blueprint $table) {
            $table->string('area', 120)->nullable()->after('rd_code')->index();
            $table->string('address', 255)->nullable()->after('area');
            $table->string('phone', 30)->nullable()->after('address');
            $table->decimal('latitude', 10, 7)->nullable()->after('phone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('retailers', function (Blueprint $table) {
            $table->dropIndex(['area']);
            $table->dropColumn(['area', 'address', 'phone', 'latitude', 'longitude']);
        });
    }
};
