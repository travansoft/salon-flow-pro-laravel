<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateSuperAdminProfileRequest;
use App\Services\SuperAdmin\SuperAdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SuperAdminProfileController extends Controller
{
    public function __construct(private SuperAdminActivityLogger $activityLogger) {}

    public function edit(): View
    {
        return view('super-admin.profile.edit', ['platformAdmin' => Auth::guard('super_admin')->user()]);
    }

    public function update(UpdateSuperAdminProfileRequest $request): RedirectResponse
    {
        $platformAdmin = Auth::guard('super_admin')->user();
        $data = $request->validated();

        if (! empty($data['password']) && ! Hash::check($data['current_password'], $platformAdmin->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->onlyInput('name', 'username', 'email');
        }

        $profileData = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
        ];

        if (! empty($data['password'])) {
            $profileData['password'] = Hash::make($data['password']);
        }

        $platformAdmin->update($profileData);

        $this->activityLogger->log('platform_admin.profile_updated', 'Updated their own profile', $platformAdmin);

        return back()->with('status', 'Profile updated.');
    }
}
