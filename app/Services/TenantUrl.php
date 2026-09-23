<?php

namespace App\Services;

class TenantUrl
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * Route to a tenant-scoped named route, picking the subdomain or
     * slug-prefixed variant depending on how the current request arrived,
     * so callers never need to know which mode is active.
     *
     * Accepts the same shapes Laravel's own route() helper does: a single
     * model/scalar (implicit route-model binding), or an array of named
     * parameters.
     */
    public function route(string $name, mixed $parameters = []): string
    {
        $tenant = $this->tenantContext->get();
        $slugPrefix = request()->attributes->get('tenant_slug_prefix');

        if (! is_array($parameters)) {
            $parameters = [$parameters];
        }

        if ($slugPrefix && $tenant) {
            return route($name.'.bySlug', ['slug' => $tenant->slug, ...$parameters]);
        }

        $path = parse_url(route($name, $parameters), PHP_URL_PATH);

        return request()->getScheme().'://'.request()->getHost().$path;
    }
}
