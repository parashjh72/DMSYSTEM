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
        'imports.sell_through',      // run only the Sell-through (RD → RT) import, scoped to own RDs
        'reports.view',
        'explorer.view',
        'exports.view', 'exports.create',
        'masterdata.view',
        'settings.manage',
        'users.manage',
    ];

    /**
     * The full-access read/write set given to Admin and NSM (everything except
     * user management, which stays Super-Admin only).
     */
    private const NATIONAL = [
        'dashboard.view', 'imports.view', 'imports.create', 'reports.view',
        'explorer.view', 'exports.view', 'exports.create', 'masterdata.view', 'settings.manage',
    ];

    /**
     * View + export, plus the Sell-through (RD → RT) import for their own
     * devices — these roles are also limited to their assigned RD codes.
     */
    private const RD_SCOPED = ['reports.view', 'exports.view', 'exports.create', 'imports.sell_through'];

    public const ROLES = [
        'Super Admin' => self::PERMISSIONS,          // controls the whole system
        'Admin' => self::NATIONAL,                    // National Distributor level
        'NSM' => self::NATIONAL,                      // National Sales Manager — same as Admin
        'ASM' => self::RD_SCOPED,                     // handles particular RD(s) + their TSOs
        'TSO' => self::RD_SCOPED,                     // handles some RD(s)
        'RD' => self::RD_SCOPED,                      // single distributor login
    ];

    /** Roles whose users must be assigned one or more RD codes (users.scoped_rd_codes). */
    public const SCOPED_ROLES = ['ASM', 'TSO', 'RD'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role)->syncPermissions($permissions);
        }

        // Drop roles that are no longer part of the hierarchy (Manager, Report
        // User, Import User). Any user still holding one loses it and must be
        // re-assigned in Users.
        Role::whereNotIn('name', array_keys(self::ROLES))->delete();

        $admin = User::firstOrCreate(
            ['email' => 'admin@dmsystem.local'],
            ['name' => 'System Administrator', 'password' => Hash::make('password')],
        );
        $admin->syncRoles(['Super Admin']);
    }
}
