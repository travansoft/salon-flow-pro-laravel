<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StorePlatformAdminRequest;
use App\Http\Requests\SuperAdmin\UpdatePlatformAdminRequest;
use App\Models\PlatformAdmin;
use App\Repositories\Contracts\PlatformAdminRepositoryInterface;
use App\Services\SuperAdmin\PlatformAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformAdminsController extends Controller
{
    public function __construct(
        private PlatformAdminRepositoryInterface $platformAdminRepository,
        private PlatformAdminService $platformAdminService,
    ) {}

    public function index(): View
    {
        return view('super-admin.platform-admins.index', ['platformAdmins' => $this->platformAdminRepository->getAll()]);
    }

    public function create(): View
    {
        return view('super-admin.platform-admins.create');
    }

    public function store(StorePlatformAdminRequest $request): RedirectResponse
    {
        $this->platformAdminService->create($request->validated());

        return redirect()->route('superAdmin.platformAdmins.index')->with('status', 'Admin user created.');
    }

    public function edit(PlatformAdmin $platformAdmin): View
    {
        return view('super-admin.platform-admins.edit', ['platformAdmin' => $platformAdmin]);
    }

    public function update(UpdatePlatformAdminRequest $request, PlatformAdmin $platformAdmin): RedirectResponse
    {
        $this->platformAdminService->update($platformAdmin, $request->validated());

        return redirect()->route('superAdmin.platformAdmins.index')->with('status', 'Admin user updated.');
    }

    public function destroy(PlatformAdmin $platformAdmin): RedirectResponse
    {
        abort_if(auth('super_admin')->id() === $platformAdmin->id, 403, 'You cannot delete your own account.');

        $this->platformAdminService->delete($platformAdmin);

        return redirect()->route('superAdmin.platformAdmins.index')->with('status', 'Admin user removed.');
    }
}
