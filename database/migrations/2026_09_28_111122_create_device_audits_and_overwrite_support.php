<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field-level audit trail for every change made to an existing device by an
 * overwrite import, a manual edit or a transfer (old value -> new value, who,
 * when, and which batch / transfer caused it).
 *
 * Also adds the per-import "overwrite existing" switch and a dated transfer
 * event, so a transfer no longer hides a device's original ST date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_audits', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 20);
            $table->string('field', 40);
            $table->string('old_value', 191)->nullable();
            $table->string('new_value', 191)->nullable();
            $table->string('source', 30); // sell_through_import | activation_import | manual_edit | transfer
            $table->unsignedBigInteger('import_batch_id')->nullable()->index();
            $table->unsignedBigInteger('record_transfer_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['imei', 'created_at']);
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->boolean('overwrite_existing')->default(false)->after('duplicate_strategy');
        });

        Schema::table('sales_activation_records', function (Blueprint $table) {
            // Latest transfer's effective date; st_date keeps the original sell-through date.
            $table->date('last_transfer_date')->nullable()->after('sell_in_date');
            $table->index('last_transfer_date');
        });

        Schema::table('record_transfers', function (Blueprint $table) {
            $table->date('transfer_date')->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('record_transfers', fn (Blueprint $table) => $table->dropColumn('transfer_date'));
        Schema::table('sales_activation_records', function (Blueprint $table) {
            $table->dropIndex(['last_transfer_date']);
            $table->dropColumn('last_transfer_date');
        });
        Schema::table('import_batches', fn (Blueprint $table) => $table->dropColumn('overwrite_existing'));
        Schema::dropIfExists('device_audits');
    }
};
