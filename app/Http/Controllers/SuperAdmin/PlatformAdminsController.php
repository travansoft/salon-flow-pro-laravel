<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StorePlatformAdminRequest;
use App\Http\Requests\SuperAdmin\UpdatePlatformAdminRequest;
use App\Models\PlatformAdmin;
use App\Repositories\Contracts\PlatformAdminRepositoryInterface;
use App\Services\SuperAdmin\PlatformAdminService;
use App\Services\SuperAdmin\SuperAdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformAdminsController extends Controller
{
    public function __construct(
        private PlatformAdminRepositoryInterface $platformAdminRepository,
        private PlatformAdminService $platformAdminService,
        private SuperAdminActivityLogger $activityLogger,
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
        $platformAdmin = $this->platformAdminService->create($request->validated());

        $this->activityLogger->log('platform_admin.created', "Created admin user \"{$platformAdmin->username}\"", $platformAdmin);

        return redirect()->route('superAdmin.platformAdmins.index')->with('status', 'Admin user created.');
    }

    public function edit(PlatformAdmin $platformAdmin): View
    {
        return view('super-admin.platform-admins.edit', ['platformAdmin' => $platformAdmin]);
    }

    public function update(UpdatePlatformAdminRequest $request, PlatformAdmin $platformAdmin): RedirectResponse
    {
        $this->platformAdminService->update($platformAdmin, $request->validated());

        $this->activityLogger->log('platform_admin.updated', "Updated admin user \"{$platformAdmin->username}\"", $platformAdmin);

        return redirect()->route('superAdmin.platformAdmins.index')->with('status', 'Admin user updated.');
    }

    public function destroy(PlatformAdmin $platformAdmin): RedirectResponse
    {
        abort_if(auth('super_admin')->id() === $platformAdmin->id, 403, 'You cannot delete your own account.');

        $username = $platformAdmin->username;
        $this->platformAdminService->delete($platformAdmin);

        $this->activityLogger->log('platform_admin.deleted', "Removed admin user \"{$username}\"");

        return redirect()->route('superAdmin.platformAdmins.index')->with('status', 'Admin user removed.');
    }
}
