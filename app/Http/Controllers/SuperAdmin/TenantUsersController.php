<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantUserRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Services\SuperAdmin\SuperAdminActivityLogger;
use App\Services\SuperAdmin\TenantUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class TenantUsersController extends Controller
{
    public function __construct(
        private TenantUserRepositoryInterface $tenantUserRepository,
        private TenantUserService $tenantUserService,
        private SuperAdminActivityLogger $activityLogger,
    ) {}

    public function index(Request $request, Tenant $tenant): View
    {
        $search = $request->query('search');
        $users = $search
            ? $this->tenantUserRepository->searchInTenant($tenant, $search)
            : $this->tenantUserRepository->getByTenant($tenant);

        return view('super-admin.tenant-users.index', [
            'tenant' => $tenant,
            'users' => $users,
            'search' => $search,
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
        $user = $this->tenantUserService->create($tenant, $request->validated());

        $this->activityLogger->log('tenant_user.created', "Created user \"{$user->username}\" for tenant \"{$tenant->name}\"", $user);

        return redirect()->route('superAdmin.tenants.users.index', $tenant)->with('status', 'User created.');
    }

    public function edit(Tenant $tenant, User $tenantUser): View
    {
        abort_unless($tenantUser->tenant_id === $tenant->id, 404);

        return view('super-admin.tenant-users.edit', [
            'tenant' => $tenant,
            'tenantUser' => $tenantUser,
            'roles' => Role::all(),
        ]);
    }

    public function update(UpdateTenantUserRequest $request, Tenant $tenant, User $tenantUser): RedirectResponse
    {
        abort_unless($tenantUser->tenant_id === $tenant->id, 404);

        $this->tenantUserService->update($tenantUser, $request->validated());

        $this->activityLogger->log('tenant_user.updated', "Updated user \"{$tenantUser->username}\" for tenant \"{$tenant->name}\"", $tenantUser);

        return redirect()->route('superAdmin.tenants.users.index', $tenant)->with('status', 'User updated.');
    }

    public function destroy(Tenant $tenant, User $tenantUser): RedirectResponse
    {
        abort_unless($tenantUser->tenant_id === $tenant->id, 404);

        if ($tenantUser->isLoginEnabled()) {
            $this->tenantUserService->disableLogin($tenantUser);
            $this->activityLogger->log('tenant_user.login_disabled', "Disabled login for \"{$tenantUser->username}\" ({$tenant->name})", $tenantUser);
        } else {
            $this->tenantUserService->enableLogin($tenantUser);
            $this->activityLogger->log('tenant_user.login_enabled', "Enabled login for \"{$tenantUser->username}\" ({$tenant->name})", $tenantUser);
        }

        return redirect()->route('superAdmin.tenants.users.index', $tenant)->with('status', 'User login access updated.');
    }
}
