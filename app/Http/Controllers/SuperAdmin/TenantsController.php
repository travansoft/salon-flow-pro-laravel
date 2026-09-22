<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\SuperAdmin\SuperAdminActivityLogger;
use App\Services\SuperAdmin\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantsController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantService $tenantService,
        private SuperAdminActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->query('search');
        $tenants = $search
            ? $this->tenantRepository->search($search)
            : $this->tenantRepository->getAll();

        return view('super-admin.tenants.index', ['tenants' => $tenants, 'search' => $search]);
    }

    public function create(): View
    {
        return view('super-admin.tenants.create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = $this->tenantService->create($request->validated());

        $this->activityLogger->log('tenant.created', "Created tenant \"{$tenant->name}\"", $tenant);

        return redirect()->route('superAdmin.tenants.show', $tenant)->with('status', 'Tenant created.');
    }

    public function show(Tenant $tenant): View
    {
        return view('super-admin.tenants.show', ['tenant' => $tenant]);
    }

    public function edit(Tenant $tenant): View
    {
        return view('super-admin.tenants.edit', ['tenant' => $tenant]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->tenantService->update($tenant, $request->validated());

        $this->activityLogger->log('tenant.updated', "Updated tenant \"{$tenant->name}\"", $tenant);

        return redirect()->route('superAdmin.tenants.show', $tenant)->with('status', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->tenantRepository->update($tenant, ['is_active' => ! $tenant->is_active]);

        $action = $tenant->is_active ? 'tenant.activated' : 'tenant.deactivated';
        $this->activityLogger->log($action, ($tenant->is_active ? 'Activated' : 'Deactivated')." tenant \"{$tenant->name}\"", $tenant);

        return redirect()->route('superAdmin.tenants.index')->with('status', $tenant->is_active ? 'Tenant activated.' : 'Tenant deactivated.');
    }
}
