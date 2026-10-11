<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('admin.profile.show', [
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
        ]);
    }
}
