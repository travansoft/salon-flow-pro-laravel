<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unlike tenant context (always re-resolved from the request host), there is
 * no per-branch host signal, so "current branch" is session-stored but
 * re-validated against the user's actual branch assignments on every
 * request — a stale/foreign session value is never trusted, mirroring
 * EnsureUserBelongsToTenant's rationale but applied to a different source of
 * truth. Must run after EnsureUserBelongsToTenant, so a tenant mismatch
 * logout is already reflected in Auth::user() by the time this runs.
 */
class ResolveBranch
{
    public function __construct(
        private BranchContext $branchContext,
        private TenantContext $tenantContext,
        private BranchRepositoryInterface $branches,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantContext->isBypassed()) {
            $this->branchContext->bypass();

            return $next($request);
        }

        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $tenant = $this->tenantContext->get();

        if (! $tenant) {
            return $next($request);
        }

        $assignedBranchIds = $user->branches()->pluck('branches.id');

        if ($assignedBranchIds->isEmpty()) {
            $this->branchContext->markNoneResolved();

            return $next($request);
        }

        if ($assignedBranchIds->count() === 1) {
            $resolvedId = $assignedBranchIds->first();
        } else {
            $sessionId = $request->session()->get('current_branch_id');
            $resolvedId = $assignedBranchIds->contains($sessionId) ? $sessionId : null;
        }

        if ($resolvedId === null) {
            $this->branchContext->markNoneResolved();

            return $next($request);
        }

        $branch = $this->branches->findById($resolvedId);

        if (! $branch || $branch->tenant_id !== $tenant->id) {
            $request->session()->forget('current_branch_id');
            $this->branchContext->markNoneResolved();

            return $next($request);
        }

        $this->branchContext->set($branch);
        $request->session()->put('current_branch_id', $branch->id);

        return $next($request);
    }
}
