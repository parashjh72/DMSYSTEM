<?php

namespace Tests\Feature;

use App\Enums\ImportMode;
use App\Models\SalesActivationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RequiresMysql;
use Tests\Concerns\RunsImports;
use Tests\TestCase;

class ImportDateConflictTest extends TestCase
{
    use RefreshDatabase, RequiresMysql, RunsImports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipUnlessMysql();
        Storage::fake(config('import.disk'));
    }

    public function test_activation_before_st_date_is_held_back_with_an_error(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();
        $ok = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();

        $batch = $this->runImport([
            ['IMEI', 'Activation Date'],
            [$device->imei, '2026-08-01'],
            [$ok->imei, '2026-08-15'],
        ], 'activation');

        $this->assertNull($device->refresh()->activation_date);
        $this->assertSame('2026-08-15', $ok->refresh()->activation_date->toDateString());
        $this->assertSame(1, $batch->updated_rows);

        $error = DB::table('import_row_errors')->where('import_batch_id', $batch->id)->sole();
        $this->assertSame('date_conflict', $error->error_type);
        $this->assertStringContainsString('activation date 2026-08-01 is before ST date 2026-08-10', $error->error_message);
    }

    public function test_st_date_before_sell_in_is_held_back(): void
    {
        $device = SalesActivationRecord::factory()->create(['sell_in_date' => '2026-08-01']);

        $batch = $this->runImport([
            ['IMEI', 'RD Code', 'RTCode', 'ST Date'],
            [$device->imei, 'MDDX2803', 'NP057002', '2026-07-25'],
        ], 'sell_through');

        $this->assertNull($device->refresh()->rt_code);
        $this->assertSame('date_conflict', DB::table('import_row_errors')->where('import_batch_id', $batch->id)->value('error_type'));
    }

    public function test_records_import_rejects_row_whose_own_dates_conflict(): void
    {
        $batch = $this->runImport([
            ['IMEI', 'Model', 'TSO', 'RD Code', 'RD Name', 'RTCode', 'RT Name', 'ST Date', 'Activation'],
            ['863222207290410', 'C63 (8+128GB)', 'Roshan Singh', 'MDDX2803', 'BRAHMA DIGITAL PVT LTD', 'NP057002', 'Ashish electronics & mobile', '2025-03-03', '2025-02-01'],
            ['863222072207893', 'C63 (8+128GB)', 'Roshan Singh', 'MD001061', 'New Rameshworam Suppliers', 'NP065585', 'New dipesh mobile gallery', '2025-03-02', '2026-07-08'],
        ], 'records', mode: ImportMode::Upsert);

        $this->assertDatabaseMissing('sales_activation_records', ['imei' => '863222207290410']);
        $this->assertDatabaseHas('sales_activation_records', ['imei' => '863222072207893']);
        $this->assertSame(1, $batch->inserted_rows);
    }
}
