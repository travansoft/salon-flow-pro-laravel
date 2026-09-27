<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EnsureUserBelongsToTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_out_and_redirects_when_authenticated_users_tenant_does_not_match_resolved_tenant(): void
    {
        $ownTenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->for($ownTenant)->create();

        app(TenantContext::class)->set($ownTenant);
        $ownBranch = Branch::factory()->create(['tenant_id' => $ownTenant->id]);
        app(BranchContext::class)->set($ownBranch);
        Auth::login($user);

        app(TenantContext::class)->set($otherTenant);
        $otherBranch = Branch::factory()->create(['tenant_id' => $otherTenant->id]);
        app(BranchContext::class)->set($otherBranch);
        $request = Request::create('http://other.salonflow.test/dashboard');
        $this->attachSession($request);

        $middleware = app(EnsureUserBelongsToTenant::class);
        $response = $middleware->handle($request, fn ($req) => new Response('should not reach here'));

        $this->assertFalse(Auth::check());
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://other.salonflow.test/login', $response->headers->get('Location'));
    }

    public function test_passes_through_when_users_tenant_matches_resolved_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);
        Auth::login($user);

        $request = Request::create('http://mejora.salonflow.test/dashboard');
        $this->attachSession($request);

        $middleware = app(EnsureUserBelongsToTenant::class);
        $sentinel = new Response('passed through');
        $result = $middleware->handle($request, fn ($req) => $sentinel);

        $this->assertSame($sentinel, $result);
    }

    public function test_passes_through_for_guests(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        app(BranchContext::class)->set($branch);

        $request = Request::create('http://mejora.salonflow.test/login');
        $this->attachSession($request);

        $middleware = app(EnsureUserBelongsToTenant::class);
        $sentinel = new Response('passed through');
        $result = $middleware->handle($request, fn ($req) => $sentinel);

        $this->assertSame($sentinel, $result);
    }

    private function attachSession(Request $request): void
    {
        $request->setLaravelSession($this->app['session']->driver());
    }
}
