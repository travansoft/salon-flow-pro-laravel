<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\SaveRoleRequest;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\PermissionMatrix;
use App\Services\RoleService;
use App\Services\SuperAdmin\SuperAdminActivityLogger;
use App\Services\SuperAdminUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class RolesController extends Controller
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private RoleService $roleService,
        private PermissionMatrix $permissionMatrix,
        private SuperAdminActivityLogger $activityLogger,
        private SuperAdminUrl $superAdminUrl,
    ) {}

    public function index(): View
    {
        return view('super-admin.roles.index', [
            'roles' => $this->roleRepository->getSystemRoles(),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.roles.create', [
            'matrix' => $this->permissionMatrix->build($this->roleRepository->getAllPermissions()),
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $role = $this->roleService->create(null, $request->validated());

        $this->activityLogger->log('role.created', "Created system role \"{$role->name}\"");

        return redirect($this->superAdminUrl->route('superAdmin.roles.index'))->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        abort_unless($role->isSystem(), 404);

        return view('super-admin.roles.edit', [
            'role' => $role,
            'matrix' => $this->permissionMatrix->build($this->roleRepository->getAllPermissions()),
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        abort_unless($role->isSystem(), 404);

        $this->roleService->update($role, $request->validated());

        $this->activityLogger->log('role.updated', "Updated system role \"{$role->name}\"");

        return redirect($this->superAdminUrl->route('superAdmin.roles.index'))->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($role->isSystem(), 404);

        try {
            $this->roleService->delete($role);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        $this->activityLogger->log('role.deleted', "Deleted system role \"{$role->name}\"");

        return redirect($this->superAdminUrl->route('superAdmin.roles.index'))->with('status', 'Role deleted.');
    }
}
