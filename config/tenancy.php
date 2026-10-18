<?php

return [
    /*
     * The main/root domain routes are registered against, e.g. salonflow.com.
     * Tenant subdomains and the admin subdomain resolve under this domain.
     * Additional main domains beyond this one are managed at runtime via the
     * main_domains table for host-based tenant resolution, but route
     * registration itself is bound to this single configured domain.
     */
    'main_domain' => env('MAIN_DOMAIN', 'localhost'),

    /*
     * Subdomains that can never be claimed by a tenant, under any main domain.
     * The 'admin' subdomain is reserved for the super-admin panel.
     */
    'reserved_subdomains' => [
        'admin',
        'www',
        'api',
        'mail',
        'ftp',
    ],

    /*
     * Default plan limits applied to every tenant. A tenant row's max_users /
     * max_branches columns (set by a super admin) override these per tenant.
     */
    'limits' => [
        'max_users' => (int) env('TENANT_MAX_USERS', 5),
        'max_branches' => (int) env('TENANT_MAX_BRANCHES', 1),
    ],
];
