<?php

namespace App\Http\Controllers;

use App\Services\SuperAdmin\SuperAdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StopImpersonationController extends Controller
{
    public function __construct(private SuperAdminActivityLogger $activityLogger) {}

    public function destroy(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('impersonator_platform_admin_id');
        $adminName = $request->session()->get('impersonator_platform_admin_name', 'Unknown');

        abort_unless($adminId, 404);

        Auth::guard('web')->logout();
        $request->session()->forget(['impersonator_platform_admin_id', 'impersonator_platform_admin_name']);
        $request->session()->regenerate();

        $this->activityLogger->logAs((int) $adminId, $adminName, 'tenant_user.impersonation_ended', 'Returned to the platform panel from impersonation.');

        return redirect()->to("{$request->getScheme()}://admin.".config('tenancy.main_domain').'/');
    }
}
