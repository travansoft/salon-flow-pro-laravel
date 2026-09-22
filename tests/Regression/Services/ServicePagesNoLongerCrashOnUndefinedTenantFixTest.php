<?php

namespace Tests\Regression\Services;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ServicePagesNoLongerCrashOnUndefinedTenantFixTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Bug: services/index and services/show display the GST-inclusive price via
     * $tenant->default_gst_rate, but $tenant is only bound by a View::composer
     * scoped to the literal 'layouts.admin' view name. That composer's data
     * never reaches @section('content') in a page that @extends it, so every
     * request to these pages threw "Undefined variable $tenant" (500). Fixed by
     * having ServicesController pass 'tenant' explicitly to every view that
     * needs it, the same way TenantSettingsController already did.
     */
    public function test_services_index_and_show_render_without_a_tenant_error(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('Owner');
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 300, 'tax_rate' => null]);

        $indexResponse = $this->actingAs($owner)->getFromTenant('/services');
        $showResponse = $this->actingAs($owner)->getFromTenant("/services/{$service->id}");

        $indexResponse->assertOk();
        $showResponse->assertOk();
        $showResponse->assertSee('354.00');
    }
}
