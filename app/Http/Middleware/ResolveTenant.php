<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\MainDomainRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantRepositoryInterface $tenants,
        private MainDomainRepositoryInterface $mainDomains,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $tenant = $this->tenants->findActiveByCustomDomain($host);

        if ($tenant) {
            $this->tenantContext->set($tenant);

            return $next($request);
        }

        foreach ($this->mainDomains->activeDomains() as $mainDomain) {
            if ($host === $mainDomain) {
                return $this->handleMainDomainRequest($request, $next);
            }

            if (! str_ends_with($host, '.'.$mainDomain)) {
                continue;
            }

            $subdomain = substr($host, 0, -strlen('.'.$mainDomain));

            if ($subdomain === 'admin') {
                $request->attributes->set('is_super_admin_request', true);
                $this->tenantContext->bypass();

                return $next($request);
            }

            $tenant = $this->tenants->findActiveBySubdomain($subdomain);

            if (! $tenant) {
                abort(404);
            }

            $this->tenantContext->set($tenant);

            return $next($request);
        }

        abort(404);
    }

    private function handleMainDomainRequest(Request $request, Closure $next): Response
    {
        $slug = $request->segment(1);

        if (! $slug) {
            $this->tenantContext->markNoneResolved();

            return $next($request);
        }

        if ($slug === 'admin') {
            $request->attributes->set('is_super_admin_request', true);
            $this->tenantContext->bypass();

            return $next($request);
        }

        $tenant = $this->tenants->findActiveBySlug($slug);

        if (! $tenant) {
            $this->tenantContext->markNoneResolved();

            return $next($request);
        }

        $this->tenantContext->set($tenant);
        $request->attributes->set('tenant_slug_prefix', $slug);

        return $next($request);
    }
}
