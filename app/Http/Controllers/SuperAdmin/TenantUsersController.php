<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantUserRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Services\SuperAdmin\TenantUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class TenantUsersController extends Controller
{
    public function __construct(
        private TenantUserRepositoryInterface $tenantUserRepository,
        private TenantUserService $tenantUserService,
    ) {}

    public function index(Tenant $tenant): View
    {
        return view('super-admin.tenant-users.index', [
            'tenant' => $tenant,
            'users' => $this->tenantUserRepository->getByTenant($tenant),
        ]);
    }

    public function create(Tenant $tenant): View
    {
        return view('super-admin.tenant-users.create', [
            'tenant' => $tenant,
            'roles' => Role::all(),
        ]);
    }

    public function store(StoreTenantUserRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->tenantUserService->create($tenant, $request->validated());

        return redirect()->route('superAdmin.tenants.users.index', $tenant)->with('status', 'User created.');
    }

    public function edit(Tenant $tenant, User $tenantUser): View
    {
        return view('super-admin.tenant-users.edit', [
            'tenant' => $tenant,
            'tenantUser' => $tenantUser,
            'roles' => Role::all(),
        ]);
    }

    public function update(UpdateTenantUserRequest $request, Tenant $tenant, User $tenantUser): RedirectResponse
    {
        $this->tenantUserService->update($tenantUser, $request->validated());

        return redirect()->route('superAdmin.tenants.users.index', $tenant)->with('status', 'User updated.');
    }

    public function destroy(Tenant $tenant, User $tenantUser): RedirectResponse
    {
        if ($tenantUser->isLoginEnabled()) {
            $this->tenantUserService->disableLogin($tenantUser);
        } else {
            $this->tenantUserService->enableLogin($tenantUser);
        }

        return redirect()->route('superAdmin.tenants.users.index', $tenant)->with('status', 'User login access updated.');
    }
}
