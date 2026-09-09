<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an RD-scoped user (ASM / TSO / RD) starts a sell-through import, the
 * distributor codes they are limited to are frozen onto the batch so the queue
 * worker can restrict the update to their own devices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->json('scope_rd_codes')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('scope_rd_codes');
        });
    }
};
