<?php

namespace App\Services;

class SuperAdminUrl
{
    /**
     * Route to a super-admin-scoped named route, picking the admin subdomain
     * or /admin path variant depending on how the current request arrived,
     * mirroring TenantUrl::route() for the tenant panel.
     */
    public function route(string $name, mixed $parameters = []): string
    {
        if (request()->getHost() === config('tenancy.main_domain')) {
            return route($name.'.byPath', $parameters);
        }

        return route($name, $parameters);
    }
}
