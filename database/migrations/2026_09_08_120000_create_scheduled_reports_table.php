<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('export_type');                 // ExportDefinition type
            $table->string('format', 8)->default('xlsx');   // csv | xlsx
            $table->string('period', 32)->default('yesterday');
            $table->string('date_basis', 32)->default('st_date');
            $table->json('filters')->nullable();            // static extra filters + frozen tso scope

            $table->string('frequency', 16)->default('daily'); // daily | weekly | monthly
            $table->unsignedTinyInteger('day_of_week')->nullable();  // 0=Sun .. 6=Sat (weekly)
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1..28 (monthly)
            $table->string('time', 5)->default('07:00');    // HH:MM in config('reports.timezone')

            $table->json('recipients');                     // list<string> email addresses
            $table->boolean('is_active')->default(true);

            $table->date('last_run_on')->nullable();        // guard against double-send in a day
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 16)->nullable();  // ok | failed
            $table->text('last_error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
