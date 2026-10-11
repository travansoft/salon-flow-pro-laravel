<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProductUsage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class ServiceProductsTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_owner_can_add_a_product_to_a_service_and_it_is_saved_immediately(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->userWithRole('Owner'))->postToTenant("/services/{$service->id}/products", [
            'product_id' => $product->id,
            'quantity_used' => 12.5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_product_usages', [
            'service_id' => $service->id,
            'product_id' => $product->id,
            'quantity_used' => 12.5,
        ]);
    }

    public function test_service_page_lists_linked_products(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Keratin Mask']);
        ServiceProductUsage::factory()->create(['tenant_id' => $this->tenant->id, 'service_id' => $service->id, 'product_id' => $product->id]);

        $response = $this->actingAs($this->userWithRole('Owner'))->getFromTenant("/services/{$service->id}");

        $response->assertOk()->assertSee('Keratin Mask');
    }

    public function test_the_same_product_cannot_be_added_twice(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        ServiceProductUsage::factory()->create(['tenant_id' => $this->tenant->id, 'service_id' => $service->id, 'product_id' => $product->id]);

        $response = $this->actingAs($this->userWithRole('Owner'))->postToTenant("/services/{$service->id}/products", [
            'product_id' => $product->id,
            'quantity_used' => 5,
        ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertSame(1, ServiceProductUsage::query()->where('service_id', $service->id)->count());
    }

    public function test_quantity_must_be_positive(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->userWithRole('Owner'))->postToTenant("/services/{$service->id}/products", [
            'product_id' => $product->id,
            'quantity_used' => 0,
        ]);

        $response->assertSessionHasErrors('quantity_used');
    }

    public function test_owner_can_change_the_quantity_used(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $usage = ServiceProductUsage::factory()->create(['tenant_id' => $this->tenant->id, 'service_id' => $service->id, 'quantity_used' => 10]);

        $this->actingAs($this->userWithRole('Owner'))->putToTenant("/services/{$service->id}/products/{$usage->id}", [
            'quantity_used' => 4,
        ])->assertRedirect();

        $this->assertSame('4.00', $usage->fresh()->quantity_used);
    }

    public function test_owner_can_remove_a_product_from_a_service(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $usage = ServiceProductUsage::factory()->create(['tenant_id' => $this->tenant->id, 'service_id' => $service->id]);

        $this->actingAs($this->userWithRole('Owner'))->deleteFromTenant("/services/{$service->id}/products/{$usage->id}")->assertRedirect();

        $this->assertDatabaseMissing('service_product_usages', ['id' => $usage->id]);
    }

    public function test_usage_of_another_service_cannot_be_edited_through_this_service(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherService = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $usage = ServiceProductUsage::factory()->create(['tenant_id' => $this->tenant->id, 'service_id' => $otherService->id, 'quantity_used' => 10]);

        $this->actingAs($this->userWithRole('Owner'))->putToTenant("/services/{$service->id}/products/{$usage->id}", [
            'quantity_used' => 1,
        ])->assertNotFound();

        $this->assertSame('10.00', $usage->fresh()->quantity_used);
    }

    public function test_front_desk_cannot_change_service_products(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->userWithRole('FrontDesk'))->postToTenant("/services/{$service->id}/products", [
            'product_id' => $product->id,
            'quantity_used' => 5,
        ])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postToTenant("/services/{$service->id}/products", [])->assertRedirect('/login');
    }
}
