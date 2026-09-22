<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\SuperAdmin\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenantsController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantService $tenantService,
    ) {}

    public function index(): View
    {
        return view('super-admin.tenants.index', ['tenants' => $this->tenantRepository->getAll()]);
    }

    public function create(): View
    {
        return view('super-admin.tenants.create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = $this->tenantService->create($request->validated());

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

        return redirect()->route('superAdmin.tenants.show', $tenant)->with('status', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->tenantRepository->update($tenant, ['is_active' => ! $tenant->is_active]);

        return redirect()->route('superAdmin.tenants.index')->with('status', $tenant->is_active ? 'Tenant activated.' : 'Tenant deactivated.');
    }
}
