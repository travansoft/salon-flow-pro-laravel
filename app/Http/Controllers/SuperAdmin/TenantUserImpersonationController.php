<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SuperAdmin\SuperAdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantUserImpersonationController extends Controller
{
    public function __construct(private SuperAdminActivityLogger $activityLogger) {}

    public function store(Request $request, Tenant $tenant, User $tenantUser): RedirectResponse
    {
        abort_unless($tenantUser->tenant_id === $tenant->id, 404);
        abort_unless($tenantUser->isLoginEnabled(), 403, 'This user\'s login is disabled.');

        $impersonator = Auth::guard('super_admin')->user();

        $this->activityLogger->log(
            'tenant_user.impersonation_started',
            "Started impersonating \"{$tenantUser->username}\" for tenant \"{$tenant->name}\"",
            $tenantUser
        );

        $request->session()->put('impersonator_platform_admin_id', $impersonator->id);
        $request->session()->put('impersonator_platform_admin_name', $impersonator->name);

        Auth::guard('web')->login($tenantUser);
        $request->session()->regenerate();

        return redirect()->to("{$request->getScheme()}://{$tenant->subdomain}.".config('tenancy.main_domain').'/dashboard');
    }
}
