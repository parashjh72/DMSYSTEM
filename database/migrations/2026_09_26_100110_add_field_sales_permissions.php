<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the Field Sales permissions to the existing roles on a live install
 * without re-running RolesAndPermissionsSeeder (which also resets the default
 * admin account). Fresh installs get the same grants from the seeder.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const GRANTS = [
        'Super Admin' => ['fs.map.view', 'fs.reports.view', 'fs.leave.approve', 'fs.setup.manage', 'fs.leave.request'],
        'Admin' => ['fs.map.view', 'fs.reports.view', 'fs.leave.approve', 'fs.setup.manage'],
        'NSM' => ['fs.map.view', 'fs.reports.view', 'fs.leave.approve', 'fs.setup.manage'],
        'ASM' => ['fs.map.view', 'fs.reports.view', 'fs.leave.approve'],
        'TSO' => ['fs.leave.request'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_unique(array_merge(...array_values(self::GRANTS))) as $name) {
            Permission::findOrCreate($name);
        }

        foreach (self::GRANTS as $roleName => $permissions) {
            Role::query()->where('name', $roleName)->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'like', 'fs.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
