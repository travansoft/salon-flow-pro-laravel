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
        'incentives',
        'settings',
        'branches',
        'users',
        'roles',
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
        $permissions[] = 'billing.editBill';
        $permissions[] = 'billing.cancel';

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $owner = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web'], [
            'description' => 'Full access to every module, including users and roles.',
        ]);
        $owner->syncPermissions($permissions);

        $accessManagement = array_merge(
            $this->modulePermissions('users'),
            $this->modulePermissions('roles'),
        );

        $this->seedRole('Manager', 'Runs the salon day to day; cannot manage users or roles.', array_diff(
            $permissions,
            ['billing.editBill', 'billing.cancel'],
            $accessManagement,
        ));

        $this->seedRole('FrontDesk', 'Books appointments, bills clients and manages client records.', [
            'staff.view',
            'services.view',
            'appointments.view', 'appointments.create', 'appointments.edit',
            'billing.view', 'billing.create',
            'inventory.view',
            'clients.view', 'clients.create', 'clients.edit',
            'dashboard.view',
        ]);

        $this->seedRole('Stylist', 'Sees own appointments, clients and incentives.', [
            'appointments.view',
            'clients.view',
            'incentives.view',
        ]);

        $this->seedRole('Director', 'Read-only oversight for directors. Can view reports and records but cannot change anything.', [
            'dashboard.view',
            'reports.consolidated.view',
            'billing.view',
            'expenses.view',
            'inventory.view',
            'clients.view',
            'appointments.view',
            'services.view',
            'staff.view',
            'incentives.view',
            'branches.view',
        ]);
    }

    /**
     * Built-in roles other than Owner are only given their default permissions
     * on first creation, so re-seeding never overwrites edits a super admin
     * made through the role manager.
     *
     * @param  array<int, string>  $permissions
     */
    private function seedRole(string $name, string $description, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['description' => $description]);

        if ($role->wasRecentlyCreated) {
            $role->syncPermissions($permissions);
        }
    }

    /** @return array<int, string> */
    private function modulePermissions(string $module): array
    {
        return array_map(fn (string $action): string => "{$module}.{$action}", self::Actions);
    }
}
