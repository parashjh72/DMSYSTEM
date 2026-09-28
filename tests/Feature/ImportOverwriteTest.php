<?php

namespace Tests\Feature;

use App\Models\DeviceAudit;
use App\Models\SalesActivationRecord;
use App\Models\User;
use App\Services\Import\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RequiresMysql;
use Tests\Concerns\RunsImports;
use Tests\TestCase;

class ImportOverwriteTest extends TestCase
{
    use RefreshDatabase, RequiresMysql, RunsImports;

    private const SELL_THROUGH_HEADER = ['IMEI', 'RD Code', 'RTCode', 'ST Date'];

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipUnlessMysql();
        Storage::fake(config('import.disk'));

        $this->admin = User::factory()->create();
        DB::table('retailers')->insert([
            ['code' => 'NP057002', 'name' => 'Ashish electronics & mobile', 'rd_code' => 'MDDX2803', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'NP065585', 'name' => 'New dipesh mobile gallery', 'rd_code' => 'MD001061', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('retail_distributors')->insert([
            ['code' => 'MDDX2803', 'name' => 'BRAHMA DIGITAL PVT LTD', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MD001061', 'name' => 'New Rameshworam Suppliers', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_sell_through_without_overwrite_still_skips_assigned_devices(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();

        $batch = $this->runImport([
            self::SELL_THROUGH_HEADER,
            [$device->imei, 'MD001061', 'NP065585', '2026-08-20'],
        ], 'sell_through', userId: $this->admin->id);

        $this->assertSame(1, $batch->skipped_rows);
        $this->assertSame('NP057002', $device->refresh()->rt_code);
        $this->assertSame(0, DeviceAudit::count());
    }

    public function test_sell_through_overwrite_updates_changed_rows_and_audits_each_field(): void
    {
        $moved = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();
        $unchanged = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();
        $fresh = SalesActivationRecord::factory()->create();

        $batch = $this->runImport([
            self::SELL_THROUGH_HEADER,
            [$moved->imei, 'MD001061', 'NP065585', '20-08-2026'],
            [$unchanged->imei, 'MDDX2803', 'NP057002', '2026-08-10'],
            [$fresh->imei, '', 'NP057002', '2026-08-12'],
        ], 'sell_through', overwrite: true, userId: $this->admin->id);

        $this->assertTrue($batch->overwrite_existing);
        $this->assertSame(2, $batch->updated_rows);
        $this->assertSame(1, $batch->skipped_rows);

        $moved->refresh();
        $this->assertSame('NP065585', $moved->rt_code);
        $this->assertSame('New dipesh mobile gallery', $moved->rt_name);
        $this->assertSame('MD001061', $moved->rd_code);
        $this->assertSame('New Rameshworam Suppliers', $moved->rd_name);
        $this->assertSame('2026-08-20', $moved->st_date->toDateString());

        $audits = DeviceAudit::where('imei', $moved->imei)->orderBy('field')->get();
        $this->assertSame(
            [['rd_code', 'MDDX2803', 'MD001061'], ['rt_code', 'NP057002', 'NP065585'], ['st_date', '2026-08-10', '2026-08-20']],
            $audits->map(fn ($a) => [$a->field, $a->old_value, $a->new_value])->all(),
        );
        $this->assertTrue($audits->every(fn ($a) => $a->import_batch_id === $batch->id
            && $a->user_id === $this->admin->id
            && $a->source === DeviceAudit::SOURCE_SELL_THROUGH));

        // Filling an unassigned device is a normal import, not an overwrite.
        $this->assertSame('NP057002', $fresh->refresh()->rt_code);
        $this->assertSame(0, DeviceAudit::where('imei', $fresh->imei)->count());
    }

    public function test_sell_through_overwrite_never_blanks_rd_when_cell_is_empty(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();

        $this->runImport([
            self::SELL_THROUGH_HEADER,
            [$device->imei, '', 'NP065585', '2026-08-20'],
        ], 'sell_through', overwrite: true, userId: $this->admin->id);

        $device->refresh();
        $this->assertSame('NP065585', $device->rt_code);
        $this->assertSame('MDDX2803', $device->rd_code);
        $this->assertSame('BRAHMA DIGITAL PVT LTD', $device->rd_name);
        $this->assertSame(0, DeviceAudit::where('field', 'rd_code')->count());
    }

    public function test_activation_overwrite_corrects_dates_with_audit(): void
    {
        $corrected = SalesActivationRecord::factory()->atRetailer()->activated('2026-09-01')->create();
        $blank = SalesActivationRecord::factory()->atRetailer()->create();

        $batch = $this->runImport([
            ['IMEI', 'Activation Date'],
            [$corrected->imei, '2026-09-03'],
            [$blank->imei, '2026-09-05'],
        ], 'activation', overwrite: true, userId: $this->admin->id);

        $this->assertSame(2, $batch->updated_rows);
        $this->assertSame('2026-09-03', $corrected->refresh()->activation_date->toDateString());
        $this->assertSame('2026-09-05', $blank->refresh()->activation_date->toDateString());

        $audit = DeviceAudit::sole();
        $this->assertSame([$corrected->imei, 'activation_date', '2026-09-01', '2026-09-03', DeviceAudit::SOURCE_ACTIVATION],
            [$audit->imei, $audit->field, $audit->old_value, $audit->new_value, $audit->source]);
    }

    public function test_activation_without_overwrite_keeps_existing_date(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer()->activated('2026-09-01')->create();

        $batch = $this->runImport([
            ['IMEI', 'Activation Date'],
            [$device->imei, '2026-09-03'],
        ], 'activation', userId: $this->admin->id);

        $this->assertSame(1, $batch->skipped_rows);
        $this->assertSame('2026-09-01', $device->refresh()->activation_date->toDateString());
    }

    public function test_scoped_import_cannot_overwrite(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();
        $path = tempnam(sys_get_temp_dir(), 'dms').'.csv';
        file_put_contents($path, "IMEI,RD Code,RTCode,ST Date\n{$device->imei},MDDX2803,NP065585,2026-08-20\n");

        $service = app(ImportService::class);
        $batch = $service->createFromPath($path, $this->admin->id, 'sell_through');
        $batch->update(['scope_rd_codes' => ['MDDX2803']]);
        $service->start($batch, overwrite: true);

        $this->assertFalse($batch->refresh()->overwrite_existing);
        $this->assertSame('NP057002', $device->refresh()->rt_code);
    }
}
