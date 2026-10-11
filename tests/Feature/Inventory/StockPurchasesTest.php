<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\StockPurchase;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class StockPurchasesTest extends TestCase
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

    public function test_manager_can_record_a_purchase_and_stock_increases(): void
    {
        $manager = $this->userWithRole('Manager');
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 5]);

        $response = $this->actingAs($manager)->postToTenant('/stock-purchases', [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 120,
            'supplier_name' => 'Acme Beauty',
            'purchased_at' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertSame('15.00', $product->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_purchases', ['product_id' => $product->id, 'supplier_name' => 'Acme Beauty']);
    }

    public function test_purchase_requires_product_and_positive_quantity(): void
    {
        $manager = $this->userWithRole('Manager');

        $response = $this->actingAs($manager)->postToTenant('/stock-purchases', [
            'quantity' => 0,
            'purchased_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['product_id', 'quantity']);
    }

    public function test_purchase_date_cannot_be_in_the_future(): void
    {
        $manager = $this->userWithRole('Manager');
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($manager)->postToTenant('/stock-purchases', [
            'product_id' => $product->id,
            'quantity' => 1,
            'purchased_at' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('purchased_at');
    }

    public function test_purchase_list_shows_recorded_purchases(): void
    {
        $manager = $this->userWithRole('Manager');
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Argan Oil']);
        StockPurchase::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id]);

        $response = $this->actingAs($manager)->getFromTenant('/stock-purchases');

        $response->assertOk()->assertSee('Argan Oil');
    }

    public function test_create_form_loads_for_manager(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->actingAs($manager)->getFromTenant('/stock-purchases/create')->assertOk();
    }

    public function test_front_desk_can_view_but_not_record_purchases(): void
    {
        $frontDesk = $this->userWithRole('FrontDesk');
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 5]);

        $this->actingAs($frontDesk)->getFromTenant('/stock-purchases')->assertOk();
        $this->actingAs($frontDesk)->getFromTenant('/stock-purchases/create')->assertForbidden();
        $this->actingAs($frontDesk)->postToTenant('/stock-purchases', [
            'product_id' => $product->id,
            'quantity' => 3,
            'purchased_at' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame('5.00', $product->fresh()->quantity_on_hand);
    }

    public function test_stylist_cannot_view_purchases(): void
    {
        $stylist = $this->userWithRole('Stylist');

        $this->actingAs($stylist)->getFromTenant('/stock-purchases')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/stock-purchases')->assertRedirect('/login');
    }
}
