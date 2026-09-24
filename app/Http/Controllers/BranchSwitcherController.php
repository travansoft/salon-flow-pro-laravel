<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitcherController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $branchId = (int) $request->validate(['branch_id' => ['required', 'integer']])['branch_id'];

        $allowed = $request->user()->branches()->pluck('branches.id');

        abort_unless($allowed->contains($branchId), 403);

        $request->session()->put('current_branch_id', $branchId);

        return redirect()->back();
    }
}
