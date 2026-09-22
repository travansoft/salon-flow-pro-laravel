<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuperAdmin\SuperAdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SuperAdminLoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::guard('super_admin')->check()) {
            return redirect($this->pathFor($request, ''));
        }

        return view('super-admin.login');
    }

    public function store(SuperAdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::guard('super_admin')->attempt($credentials)) {
            return back()->withErrors(['username' => 'Invalid credentials.'])->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->pathFor($request, ''));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $loginPath = $this->pathFor($request, 'login');

        Auth::guard('super_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($loginPath);
    }

    private function pathFor(Request $request, string $tail): string
    {
        $slugPrefix = $request->attributes->get('tenant_slug_prefix');

        if ($slugPrefix) {
            return $request->getSchemeAndHttpHost()."/{$slugPrefix}/{$tail}";
        }

        return rtrim($request->getSchemeAndHttpHost()."/{$tail}", '/');
    }
}
