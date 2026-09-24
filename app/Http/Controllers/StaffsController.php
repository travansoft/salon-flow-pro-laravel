<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Models\StaffProfile;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\DesignationRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use App\Services\StaffService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class StaffsController extends Controller
{
    public function __construct(
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private DesignationRepositoryInterface $designationRepository,
        private ServiceRepositoryInterface $serviceRepository,
        private BranchRepositoryInterface $branchRepository,
        private StaffService $staffService,
        private TenantUrl $tenantUrl,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('staff.view'), 403);

        $staff = $this->staffProfileRepository->getActive();

        return view('admin.staff.index', ['staff' => $staff]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('staff.create'), 403);

        return view('admin.staff.create', [
            'designations' => $this->designationRepository->getActive(),
            'roles' => Role::all(),
            'services' => $this->serviceRepository->getActive(),
            'branches' => $this->branchRepository->getActive(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('staff.create'), 403);

        $staffProfile = $this->staffService->create($request->validated());

        return redirect($this->tenantUrl->route('staff.show', ['staff' => $staffProfile]))->with('status', 'Staff member created.');
    }

    public function show(Request $request, string $subdomain, StaffProfile $staff): View
    {
        abort_unless($request->user()->can('staff.view'), 403);

        return view('admin.staff.show', ['staff' => $staff]);
    }

    public function edit(Request $request, string $subdomain, StaffProfile $staff): View
    {
        abort_unless($request->user()->can('staff.edit'), 403);

        return view('admin.staff.edit', [
            'staff' => $staff,
            'designations' => $this->designationRepository->getActive(),
            'roles' => Role::all(),
            'services' => $this->serviceRepository->getActive(),
            'branches' => $this->branchRepository->getActive(),
        ]);
    }

    public function update(UpdateStaffRequest $request, string $subdomain, StaffProfile $staff): RedirectResponse
    {
        abort_unless($request->user()->can('staff.edit'), 403);

        $this->staffService->update($staff, $request->validated());

        return redirect($this->tenantUrl->route('staff.show', ['staff' => $staff]))->with('status', 'Staff member updated.');
    }

    public function destroy(Request $request, string $subdomain, StaffProfile $staff): RedirectResponse
    {
        abort_unless($request->user()->can('staff.delete'), 403);

        $this->staffProfileRepository->delete($staff);

        return redirect($this->tenantUrl->route('staff.index'))->with('status', 'Staff member removed.');
    }
}
