<?php

namespace App\Services;

use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class PermissionMatrix
{
    /**
     * Splits permissions into a module x action grid plus the one-off
     * permissions (e.g. "billing.cancel") that don't fit the CRUD columns.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return array{
     *     actions: array<int, string>,
     *     modules: array<string, array<string, string>>,
     *     extras: array<string, string>,
     * }
     */
    public function build(Collection $permissions): array
    {
        $names = $permissions->pluck('name');
        $modules = [];
        $extras = [];

        foreach (PermissionSeeder::Modules as $module) {
            foreach (PermissionSeeder::Actions as $action) {
                $name = "{$module}.{$action}";

                if ($names->contains($name)) {
                    $modules[$module][$action] = $name;
                }
            }
        }

        foreach ($names as $name) {
            if (! $this->isGridPermission($name)) {
                $extras[$name] = Str::headline(str_replace('.', ' ', $name));
            }
        }

        return [
            'actions' => PermissionSeeder::Actions,
            'modules' => $modules,
            'extras' => $extras,
        ];
    }

    private function isGridPermission(string $name): bool
    {
        [$module, $action] = array_pad(explode('.', $name, 2), 2, null);

        return in_array($module, PermissionSeeder::Modules, true)
            && in_array($action, PermissionSeeder::Actions, true);
    }
}
