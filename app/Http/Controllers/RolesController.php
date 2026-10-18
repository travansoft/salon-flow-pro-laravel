<?php

namespace App\Http\Controllers;

use App\Http\Requests\Roles\SaveRoleRequest;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\PermissionMatrix;
use App\Services\RoleService;
use App\Services\TenantContext;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class RolesController extends Controller
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private RoleService $roleService,
        private PermissionMatrix $permissionMatrix,
        private TenantContext $tenantContext,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('roles.view'), 403);

        $tenantId = $this->tenantContext->get()->id;

        return view('admin.roles.index', [
            'systemRoles' => $this->roleRepository->getSystemRoles(),
            'customRoles' => $this->roleRepository->getOwnedByTenant($tenantId),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('roles.create'), 403);

        return view('admin.roles.create', [
            'matrix' => $this->grantableMatrix($request),
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.create'), 403);

        $role = $this->roleService->create($this->tenantContext->get(), $request->validated());

        return redirect($this->tenantUrl->route('roles.show', ['role' => $role]))->with('status', 'Role created.');
    }

    public function show(Request $request, string $subdomain, Role $role): View
    {
        abort_unless($request->user()->can('roles.view'), 403);
        $this->abortUnlessVisible($role);

        return view('admin.roles.show', [
            'role' => $role,
            'matrix' => $this->permissionMatrix->build($this->roleRepository->getAllPermissions()),
            'granted' => $role->permissions->pluck('name')->all(),
            'userCount' => $this->roleRepository->countUsers($role),
        ]);
    }

    public function edit(Request $request, string $subdomain, Role $role): View
    {
        abort_unless($request->user()->can('roles.edit'), 403);
        $this->abortUnlessOwned($role);

        return view('admin.roles.edit', [
            'role' => $role,
            'matrix' => $this->grantableMatrix($request),
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(SaveRoleRequest $request, string $subdomain, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.edit'), 403);
        $this->abortUnlessOwned($role);

        $data = $request->validated();
        $outOfReach = $role->permissions->pluck('name')->reject(fn (string $name): bool => $request->user()->can($name));
        $data['permissions'] = $outOfReach->merge($data['permissions'])->unique()->values()->all();

        $this->roleService->update($role, $data);

        return redirect($this->tenantUrl->route('roles.show', ['role' => $role]))->with('status', 'Role updated.');
    }

    public function destroy(Request $request, string $subdomain, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.delete'), 403);
        $this->abortUnlessOwned($role);

        try {
            $this->roleService->delete($role);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        return redirect($this->tenantUrl->route('roles.index'))->with('status', 'Role deleted.');
    }

    /**
     * @return array{
     *     actions: array<int, string>,
     *     modules: array<string, array<string, string>>,
     *     extras: array<string, string>,
     * }
     */
    private function grantableMatrix(Request $request): array
    {
        $grantable = $this->roleRepository->getAllPermissions()
            ->filter(fn ($permission): bool => $request->user()->can($permission->name));

        return $this->permissionMatrix->build($grantable);
    }

    private function abortUnlessVisible(Role $role): void
    {
        $isVisible = $role->isSystem() || $role->tenant_id === $this->tenantContext->get()->id;

        abort_unless($isVisible, 404);
    }

    private function abortUnlessOwned(Role $role): void
    {
        abort_unless($role->tenant_id === $this->tenantContext->get()->id, 404);
    }
}
