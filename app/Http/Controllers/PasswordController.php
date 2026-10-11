<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Services\ProfileService;
use App\Services\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
        private TenantUrl $tenantUrl,
    ) {}

    public function edit(): View
    {
        return view('admin.profile.change-password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->profileService->changePassword($request->user(), $request->validated('password'));

        return redirect($this->tenantUrl->route('profile.show'))->with('status', 'Password changed.');
    }
}
