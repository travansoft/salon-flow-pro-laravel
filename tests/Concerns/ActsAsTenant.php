<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\MainDomain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

trait ActsAsTenant
{
    protected ?Tenant $tenant = null;

    protected ?MainDomain $mainDomain = null;

    protected ?Branch $branch = null;

    protected function setUpTenant(): Tenant
    {
        $this->mainDomain = MainDomain::factory()->create([
            'domain' => 'salonflow.test',
        ]);

        $this->tenant = Tenant::factory()->create([
            'subdomain' => 'mejora',
        ]);

        return $this->tenant;
    }

    /**
     * Creates a branch for the current tenant. Branch-scoped models require a
     * resolved BranchContext (set for real by ResolveBranch middleware during
     * an actual HTTP request, mirroring how TenantContext is resolved), which
     * in turn requires the acting user to be assigned to a branch — call
     * assignToBranch() with that user after this.
     */
    protected function setUpBranch(): Branch
    {
        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);

        return $this->branch;
    }

    protected function assignToBranch(User $user, ?Branch $branch = null): void
    {
        $user->branches()->syncWithoutDetaching([($branch ?? $this->branch)->id]);
    }

    protected function tenantUrl(string $uri): string
    {
        return 'http://mejora.salonflow.test'.$uri;
    }

    protected function bySlugUrl(string $uri): string
    {
        return 'http://salonflow.test/'.$this->tenant->slug.$uri;
    }

    protected function getFromTenant(string $uri): TestResponse
    {
        return $this->get($this->tenantUrl($uri));
    }

    protected function postToTenant(string $uri, array $data = []): TestResponse
    {
        return $this->post($this->tenantUrl($uri), $data);
    }

    protected function putToTenant(string $uri, array $data = []): TestResponse
    {
        return $this->put($this->tenantUrl($uri), $data);
    }

    protected function deleteFromTenant(string $uri): TestResponse
    {
        return $this->delete($this->tenantUrl($uri));
    }
}
