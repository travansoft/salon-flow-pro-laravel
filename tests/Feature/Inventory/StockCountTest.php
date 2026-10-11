<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class StockCountTest extends TestCase
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

    public function test_manager_can_reset_stock_to_the_counted_quantity(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 2]);

        $response = $this->actingAs($this->userWithRole('Manager'))->postToTenant("/products/{$product->id}/stock-counts", [
            'counted_quantity' => 8,
        ]);

        $response->assertRedirect();
        $this->assertSame('8.00', $product->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'type' => 'count_correction',
            'quantity_delta' => 6,
        ]);
    }

    public function test_counted_quantity_is_required_and_cannot_be_negative(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager = $this->userWithRole('Manager');

        $this->actingAs($manager)->postToTenant("/products/{$product->id}/stock-counts", [])->assertSessionHasErrors('counted_quantity');
        $this->actingAs($manager)->postToTenant("/products/{$product->id}/stock-counts", ['counted_quantity' => -1])->assertSessionHasErrors('counted_quantity');
    }

    public function test_front_desk_cannot_record_a_count(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 2]);

        $this->actingAs($this->userWithRole('FrontDesk'))->postToTenant("/products/{$product->id}/stock-counts", [
            'counted_quantity' => 8,
        ])->assertForbidden();

        $this->assertSame('2.00', $product->fresh()->quantity_on_hand);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postToTenant("/products/{$product->id}/stock-counts", ['counted_quantity' => 1])->assertRedirect('/login');
    }
}
