<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Row-level data scoping moves from TSO name to RD code: an ASM / TSO / RD login
 * is limited to the distributor codes assigned to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('scoped_rd_codes')->nullable()->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scoped_tsos');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('scoped_tsos')->nullable()->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scoped_rd_codes');
        });
    }
};
