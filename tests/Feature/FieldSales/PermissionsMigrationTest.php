<?php

namespace Tests\Feature\FieldSales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Live installs get the Field Sales permissions from a migration (not the
 * seeder, which would also reset the default admin).
 */
class PermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_grants_field_sales_permissions_to_existing_roles_and_rolls_back(): void
    {
        foreach (['Super Admin', 'Admin', 'NSM', 'ASM', 'TSO', 'RD'] as $name) {
            Role::findOrCreate($name);
        }
        Permission::query()->where('name', 'like', 'fs.%')->delete();
        $migration = require database_path('migrations/2026_09_26_100110_add_field_sales_permissions.php');

        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue(Role::findByName('ASM')->hasPermissionTo('fs.leave.approve'));
        $this->assertTrue(Role::findByName('TSO')->hasPermissionTo('fs.leave.request'));
        $this->assertFalse(Role::findByName('TSO')->hasPermissionTo('fs.map.view'));
        $this->assertSame([], Role::findByName('RD')->permissions->pluck('name')->filter(fn ($p) => str_starts_with($p, 'fs.'))->values()->all());

        $migration->down();

        $this->assertSame(0, Permission::query()->where('name', 'like', 'fs.%')->count());
    }
}
