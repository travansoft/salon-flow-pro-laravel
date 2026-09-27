<?php

namespace App\Http\Middleware;

use App\Services\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchRequest
{
    public function __construct(private BranchContext $branchContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->branchContext->has() && ! $this->branchContext->isBypassed()) {
            abort(404);
        }

        return $next($request);
    }
}
