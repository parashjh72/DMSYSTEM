<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RT scheme-enrolment lifecycle: when the retailer was enrolled, the window it
 * applies for, and whether it is currently active (so an enrolment can be
 * deactivated without losing the history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheme_retailers', function (Blueprint $table) {
            $table->date('enrolled_on')->nullable()->after('rt_name');
            $table->date('effective_from')->nullable()->after('enrolled_on');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->string('status', 12)->default('active')->after('effective_to')->index();
            $table->timestamp('deactivated_at')->nullable()->after('status');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')
                ->constrained('users')->nullOnDelete();
        });

        // Existing rows: treat as enrolled on their created date, active.
        DB::statement('UPDATE scheme_retailers SET enrolled_on = DATE(created_at) WHERE enrolled_on IS NULL');
    }

    public function down(): void
    {
        Schema::table('scheme_retailers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn(['enrolled_on', 'effective_from', 'effective_to', 'status', 'deactivated_at']);
        });
    }
};
