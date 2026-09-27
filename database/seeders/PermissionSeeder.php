<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /** @var array<int, string> */
    public const Modules = [
        'staff',
        'services',
        'appointments',
        'billing',
        'inventory',
        'clients',
        'expenses',
        'dashboard',
        'commissions',
        'settings',
        'branches',
    ];

    /** @var array<int, string> */
    public const Actions = ['view', 'create', 'edit', 'delete'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [];

        foreach (self::Modules as $module) {
            foreach (self::Actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        $permissions[] = 'reports.consolidated.view';
        $permissions[] = 'billing.backfill';

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $owner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $owner->syncPermissions($permissions);

        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $manager->syncPermissions($permissions);

        $frontDesk = Role::firstOrCreate(['name' => 'FrontDesk', 'guard_name' => 'web']);
        $frontDesk->syncPermissions([
            'staff.view',
            'services.view',
            'appointments.view', 'appointments.create', 'appointments.edit',
            'billing.view', 'billing.create',
            'inventory.view',
            'clients.view', 'clients.create', 'clients.edit',
            'dashboard.view',
        ]);

        $stylist = Role::firstOrCreate(['name' => 'Stylist', 'guard_name' => 'web']);
        $stylist->syncPermissions([
            'appointments.view',
            'clients.view',
            'commissions.view',
        ]);
    }
}
