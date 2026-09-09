<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit reporting line for the field hierarchy: a TSO reports to an ASM, an
 * ASM reports to an NSM. Used for PJP approval routing (frozen onto each PJP at
 * submit time). Backfilled from scoped_rd_codes overlap where unambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('reports_to_id')->nullable()->after('scoped_rd_codes')
                ->constrained('users')->nullOnDelete();
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        $roleId = fn (string $name) => DB::table('roles')->where('name', $name)->value('id');

        $usersWithRole = fn (string $name) => DB::table('users')
            ->join('model_has_roles as mr', fn ($j) => $j->on('mr.model_id', 'users.id')
                ->where('mr.model_type', 'App\\Models\\User')->where('mr.role_id', $roleId($name)))
            ->get(['users.id', 'users.scoped_rd_codes']);

        $codes = fn ($json) => array_values(array_filter((array) json_decode((string) $json, true)));

        $asms = $usersWithRole('ASM')->map(fn ($u) => ['id' => $u->id, 'codes' => $codes($u->scoped_rd_codes)]);
        $nsms = $usersWithRole('NSM');
        $singleNsm = $nsms->count() === 1 ? $nsms->first()->id : null;

        // TSO -> the one ASM whose RD codes overlap
        foreach ($usersWithRole('TSO') as $tso) {
            $tsoCodes = $codes($tso->scoped_rd_codes);
            $match = $asms->filter(fn ($a) => array_intersect($a['codes'], $tsoCodes) !== []);
            if ($match->count() === 1) {
                DB::table('users')->where('id', $tso->id)->update(['reports_to_id' => $match->first()['id']]);
            }
        }

        // ASM -> the single NSM if there is exactly one
        if ($singleNsm) {
            foreach ($asms as $asm) {
                DB::table('users')->where('id', $asm['id'])->update(['reports_to_id' => $singleNsm]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_id');
        });
    }
};
