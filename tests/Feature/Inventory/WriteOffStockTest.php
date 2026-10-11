<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class WriteOffStockTest extends TestCase
{
    use ActsAsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->seed(PermissionSeeder::class);
    }

    private function manager(): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Manager');

        return $user;
    }

    public function test_manager_can_write_off_expired_stock_without_a_reason(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 10]);

        $response = $this->actingAs($this->manager())->postToTenant("/products/{$product->id}/stock-adjustments", [
            'type' => 'expired',
            'quantity_delta' => 4,
        ]);

        $response->assertRedirect();
        $this->assertSame('6.00', $product->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'type' => 'expired',
            'quantity_delta' => -4,
        ]);
    }

    public function test_manager_can_write_off_damaged_stock(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 10]);

        $this->actingAs($this->manager())->postToTenant("/products/{$product->id}/stock-adjustments", [
            'type' => 'damaged',
            'quantity_delta' => 1,
            'reason' => 'Dropped bottle',
        ])->assertRedirect();

        $this->assertDatabaseHas('stock_adjustments', ['product_id' => $product->id, 'type' => 'damaged', 'reason' => 'Dropped bottle']);
    }

    public function test_write_off_quantity_must_be_positive(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 10]);

        $response = $this->actingAs($this->manager())->postToTenant("/products/{$product->id}/stock-adjustments", [
            'type' => 'expired',
            'quantity_delta' => -4,
        ]);

        $response->assertSessionHasErrors('quantity_delta');
        $this->assertSame('10.00', $product->fresh()->quantity_on_hand);
    }

    public function test_unknown_adjustment_type_is_rejected(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->manager())->postToTenant("/products/{$product->id}/stock-adjustments", [
            'type' => 'usage',
            'quantity_delta' => 1,
            'reason' => 'x',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_front_desk_cannot_write_off_stock(): void
    {
        $frontDesk = User::factory()->for($this->tenant)->create();
        $frontDesk->assignRole('FrontDesk');
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 10]);

        $this->actingAs($frontDesk)->postToTenant("/products/{$product->id}/stock-adjustments", [
            'type' => 'expired',
            'quantity_delta' => 4,
        ])->assertForbidden();

        $this->assertSame('10.00', $product->fresh()->quantity_on_hand);
    }
}
