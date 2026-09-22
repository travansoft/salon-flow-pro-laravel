<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\PlatformAdminRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private PlatformAdminRepositoryInterface $platformAdminRepository,
    ) {}

    public function index(): View
    {
        return view('super-admin.dashboard', [
            'tenants' => $this->tenantRepository->getAll(),
            'platformAdmins' => $this->platformAdminRepository->getAll(),
        ]);
    }
}
