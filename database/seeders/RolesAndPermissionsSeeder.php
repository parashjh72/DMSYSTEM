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
        'returns.request',           // RD: ask to pull devices back from a retailer
        'returns.review',            // Admin / NSM / Super Admin: approve or reject those requests
        'promoters.manage',          // manage promoters (RA) and track their monthly achievement
        'promoter_requests.create',      // TSO: raise an RA request for a retailer
        'promoter_requests.approve_asm', // ASM: first-level approval
        'promoter_requests.approve_nsm', // NSM: final approval (creates the promoter)

        'attendance.check',          // TSO only: own GPS check-in / check-out
        'attendance.view_all',       // Admin / NSM / ASM: attendance reports

        'schemes.enrol',             // enrol / deactivate retailers in a scheme

        'pjp.create',                // TSO: own monthly Planned Journey Plan
        'pjp.submit',                // TSO: submit for approval
        'pjp.asm_review',            // ASM: approve / forward / request revision
        'pjp.nsm_final_approve',     // NSM: final approve / reject / request revision
        'pjp.report',                // Admin / NSM / ASM: PJP reports

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
        'explorer.view', 'exports.view', 'exports.create', 'returns.review',
        'promoters.manage', 'attendance.view_all', 'schemes.enrol', 'pjp.report',
        'masterdata.view', 'settings.manage',
    ];

    /**
     * View + export, plus the Sell-through (RD → RT) import for their own
     * devices — these roles are also limited to their assigned RD codes.
     */
    private const RD_SCOPED = ['reports.view', 'exports.view', 'exports.create', 'imports.sell_through'];

    public const ROLES = [
        'Super Admin' => self::PERMISSIONS,          // controls the whole system
        'Admin' => self::NATIONAL,                    // National Distributor level
        'NSM' => [...self::NATIONAL, 'promoter_requests.approve_nsm', 'pjp.nsm_final_approve'],
        'ASM' => [...self::RD_SCOPED, 'promoter_requests.approve_asm',
            'attendance.view_all', 'pjp.asm_review', 'pjp.report'],
        'TSO' => [...self::RD_SCOPED, 'promoter_requests.create',
            'attendance.check', 'pjp.create', 'pjp.submit'],
        'RD' => [...self::RD_SCOPED, 'returns.request'],                // single distributor login — can raise returns
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
