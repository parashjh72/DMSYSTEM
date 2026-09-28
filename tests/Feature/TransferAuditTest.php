<?php

namespace Tests\Feature;

use App\Livewire\Transfer;
use App\Models\DeviceAudit;
use App\Models\RecordTransfer;
use App\Models\SalesActivationRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TransferAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(Permission::findOrCreate('settings.manage'));
        DB::table('retailers')->insert(['code' => 'NP065585', 'name' => 'New dipesh mobile gallery', 'rd_code' => 'MD001061', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('retail_distributors')->insert(['code' => 'MD001061', 'name' => 'New Rameshworam Suppliers', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_imei_transfer_keeps_original_st_date_and_records_dated_event(): void
    {
        $device = SalesActivationRecord::factory()->atRetailer('NP057002', '2026-08-10')->create();

        Livewire::actingAs($this->admin)
            ->test(Transfer::class)
            ->set('targetRt', 'NP065585')
            ->set('transferDate', '2026-09-20')
            ->set('imeis', $device->imei."\n860000000000000")
            ->call('transferImeis')
            ->assertHasNoErrors();

        $device->refresh();
        $this->assertSame('NP065585', $device->rt_code);
        $this->assertSame('MD001061', $device->rd_code);
        $this->assertSame('2026-08-10', $device->st_date->toDateString());
        $this->assertSame('2026-09-20', $device->last_transfer_date->toDateString());

        $transfer = RecordTransfer::sole();
        $this->assertSame('2026-09-20', $transfer->transfer_date->toDateString());
        $this->assertSame(1, $transfer->affected_count);
        $this->assertSame(['860000000000000'], $transfer->not_found);

        $audits = DeviceAudit::orderBy('field')->get();
        $this->assertSame([['rd_code', 'MDDX2803', 'MD001061'], ['rt_code', 'NP057002', 'NP065585']],
            $audits->map(fn ($a) => [$a->field, $a->old_value, $a->new_value])->all());
        $this->assertTrue($audits->every(fn ($a) => $a->record_transfer_id === $transfer->id
            && $a->source === DeviceAudit::SOURCE_TRANSFER && $a->user_id === $this->admin->id));
    }

    public function test_imeis_can_come_from_an_uploaded_csv(): void
    {
        $first = SalesActivationRecord::factory()->atRetailer()->create();
        $second = SalesActivationRecord::factory()->atRetailer()->create();
        $file = UploadedFile::fake()->createWithContent('imeis.csv', "Model,IMEI\nNote 80,{$first->imei}\nC100i,{$second->imei}\n");

        Livewire::actingAs($this->admin)
            ->test(Transfer::class)
            ->set('targetRt', 'NP065585')
            ->set('imeiFile', $file)
            ->call('transferImeis')
            ->assertHasNoErrors();

        $this->assertSame(2, RecordTransfer::sole()->affected_count);
        $this->assertSame('NP065585', $second->refresh()->rt_code);
    }

    public function test_retailer_transfer_of_stock_only_skips_activated_and_audits(): void
    {
        $stock = SalesActivationRecord::factory()->atRetailer('NP057002')->create();
        $sold = SalesActivationRecord::factory()->atRetailer('NP057002')->activated('2026-09-01')->create();

        Livewire::actingAs($this->admin)
            ->test(Transfer::class)
            ->set('mode', 'retailer')
            ->set('sourceRt', 'NP057002')
            ->set('targetRt', 'NP065585')
            ->set('onlyInStock', true)
            ->call('transferRetailer')
            ->assertHasNoErrors();

        $this->assertSame('NP065585', $stock->refresh()->rt_code);
        $this->assertSame('NP057002', $sold->refresh()->rt_code);
        $this->assertSame([$stock->imei], DeviceAudit::where('field', 'rt_code')->pluck('imei')->all());
        $this->assertNotNull($stock->last_transfer_date);
    }

    public function test_future_transfer_date_is_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Transfer::class)
            ->set('targetRt', 'NP065585')
            ->set('transferDate', now()->addDay()->toDateString())
            ->set('imeis', '863222207290410')
            ->call('transferImeis')
            ->assertHasErrors(['transferDate' => 'before_or_equal']);
    }
}
