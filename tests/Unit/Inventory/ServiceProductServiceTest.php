<?php

namespace Tests\Unit\Inventory;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\BranchContext;
use App\Services\ServiceProductService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $this->tenant->id]));
    }

    public function test_add_product_links_it_with_the_quantity_used(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $usage = app(ServiceProductService::class)->addProduct($service, $product->id, 15);

        $this->assertSame('15.00', $usage->fresh()->quantity_used);
        $this->assertSame($this->tenant->id, $usage->tenant_id);
        $this->assertCount(1, app(ServiceProductService::class)->getForService($service));
    }

    public function test_update_quantity_changes_the_quantity_used(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $serviceProducts = app(ServiceProductService::class);
        $usage = $serviceProducts->addProduct($service, $product->id, 15);

        $serviceProducts->updateQuantity($usage, 7.5);

        $this->assertSame('7.50', $usage->fresh()->quantity_used);
    }

    public function test_remove_product_unlinks_it_from_the_service(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $serviceProducts = app(ServiceProductService::class);
        $usage = $serviceProducts->addProduct($service, $product->id, 15);

        $serviceProducts->removeProduct($usage);

        $this->assertCount(0, $serviceProducts->getForService($service));
    }
}
