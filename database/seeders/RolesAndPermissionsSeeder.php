<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view',
        'imports.view', 'imports.create',
        'reports.view',
        'explorer.view',
        'exports.view', 'exports.create',
        'masterdata.view',
        'settings.manage',
        'users.manage',
    ];

    public const ROLES = [
        // Import permission is deliberately separate from report permission (§21).
        'Super Admin' => self::PERMISSIONS,
        'Admin' => [
            'dashboard.view', 'imports.view', 'imports.create', 'reports.view',
            'explorer.view', 'exports.view', 'exports.create', 'masterdata.view', 'settings.manage',
        ],
        'Manager' => [
            'dashboard.view', 'imports.view', 'reports.view', 'explorer.view',
            'exports.view', 'exports.create', 'masterdata.view',
        ],
        'Report User' => ['dashboard.view', 'reports.view', 'exports.view'],
        'Import User' => ['dashboard.view', 'imports.view', 'imports.create'],
        // Field role: sees only its own TSO's rows (users.scoped_tsos). No dashboard.
        'TSO' => ['reports.view', 'exports.view', 'exports.create'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role)->syncPermissions($permissions);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@dmsystem.local'],
            ['name' => 'System Administrator', 'password' => Hash::make('password')],
        );
        $admin->syncRoles(['Super Admin']);
    }
}
