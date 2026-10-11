<?php

namespace Tests\Unit\Inventory;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryServiceStockMovementsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        app(BranchContext::class)->set(Branch::factory()->create(['tenant_id' => $this->tenant->id]));
        $this->user = User::factory()->for($this->tenant)->create();
    }

    public function test_adjust_stock_defaults_to_the_manual_movement_type(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 10]);

        app(InventoryService::class)->adjustStock($product, 2, 'Found stock', $this->user->id);

        $this->assertSame(StockMovementType::Manual, $product->stockAdjustments()->first()->type);
    }

    public function test_write_off_deducts_a_positive_quantity_and_records_the_type(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 10]);

        $updated = app(InventoryService::class)->writeOffStock($product, 3, StockMovementType::Expired, null, $this->user->id);

        $this->assertSame('7.00', $updated->quantity_on_hand);
        $movement = $product->stockAdjustments()->first();
        $this->assertSame('-3.00', $movement->quantity_delta);
        $this->assertSame(StockMovementType::Expired, $movement->type);
        $this->assertSame('Expired', $movement->reason);
    }

    public function test_write_off_rejects_a_non_write_off_type(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->writeOffStock($product, 1, StockMovementType::Purchase, null, $this->user->id);
    }

    public function test_write_off_rejects_a_zero_quantity(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->writeOffStock($product, 0, StockMovementType::Damaged, null, $this->user->id);
    }

    public function test_record_count_above_system_stock_adds_a_positive_correction(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 4]);

        app(InventoryService::class)->recordCount($product, 9, $this->user->id);

        $this->assertSame('9.00', $product->fresh()->quantity_on_hand);
        $movement = $product->stockAdjustments()->first();
        $this->assertSame('5.00', $movement->quantity_delta);
        $this->assertSame(StockMovementType::CountCorrection, $movement->type);
    }

    public function test_record_count_below_system_stock_adds_a_negative_correction(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => 10]);

        app(InventoryService::class)->recordCount($product, 6, $this->user->id);

        $this->assertSame('6.00', $product->fresh()->quantity_on_hand);
        $this->assertSame('-4.00', $product->stockAdjustments()->first()->quantity_delta);
    }

    public function test_record_count_resets_a_negative_estimate_to_the_counted_quantity(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'quantity_on_hand' => -3]);

        app(InventoryService::class)->recordCount($product, 5, $this->user->id);

        $this->assertSame('5.00', $product->fresh()->quantity_on_hand);
    }
}
