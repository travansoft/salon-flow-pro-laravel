<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCategory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\TestCase;

class LowStockReportTest extends TestCase
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

    public function test_report_lists_only_products_at_or_below_their_threshold(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nearly Empty Serum', 'quantity_on_hand' => 2, 'reorder_level' => 10]);
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Plenty Shampoo', 'quantity_on_hand' => 50, 'reorder_level' => 10]);

        $response = $this->actingAs($this->userWithRole('Manager'))->getFromTenant('/inventory/low-stock');

        $response->assertOk()->assertSee('Nearly Empty Serum')->assertDontSee('Plenty Shampoo');
    }

    public function test_report_flags_estimates_that_have_gone_below_zero(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Overused Gel', 'quantity_on_hand' => -3, 'reorder_level' => 5]);

        $response = $this->actingAs($this->userWithRole('Manager'))->getFromTenant('/inventory/low-stock');

        $response->assertOk()->assertSee('Overused Gel')->assertSee('needs a count');
    }

    public function test_report_excludes_inactive_products(): void
    {
        Product::factory()->inactive()->lowStock()->create(['tenant_id' => $this->tenant->id, 'name' => 'Retired Wax']);

        $response = $this->actingAs($this->userWithRole('Manager'))->getFromTenant('/inventory/low-stock');

        $response->assertOk()->assertDontSee('Retired Wax');
    }

    public function test_report_can_be_filtered_by_category(): void
    {
        $skin = InventoryCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $hair = InventoryCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Product::factory()->lowStock()->create(['tenant_id' => $this->tenant->id, 'name' => 'Skin Cream', 'category_id' => $skin->id]);
        Product::factory()->lowStock()->create(['tenant_id' => $this->tenant->id, 'name' => 'Hair Dye', 'category_id' => $hair->id]);

        $response = $this->actingAs($this->userWithRole('Manager'))->getFromTenant("/inventory/low-stock?category={$skin->id}");

        $response->assertOk()->assertSee('Skin Cream')->assertDontSee('Hair Dye');
    }

    public function test_front_desk_can_view_the_report_but_not_the_count_form(): void
    {
        Product::factory()->lowStock()->create(['tenant_id' => $this->tenant->id, 'name' => 'Skin Cream']);

        $response = $this->actingAs($this->userWithRole('FrontDesk'))->getFromTenant('/inventory/low-stock');

        $response->assertOk()->assertSee('Skin Cream')->assertDontSee('counted_quantity');
    }

    public function test_stylist_cannot_view_the_report(): void
    {
        $this->actingAs($this->userWithRole('Stylist'))->getFromTenant('/inventory/low-stock')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getFromTenant('/inventory/low-stock')->assertRedirect('/login');
    }
}
