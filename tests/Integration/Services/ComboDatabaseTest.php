<?php

namespace Tests\Integration\Services;

use App\Models\Service;
use App\Models\ServiceComboItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesCombos;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ComboDatabaseTest extends TestCase
{
    use ActsAsTenant, CreatesCombos, CreatesIncentiveBills, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
    }

    public function test_combo_items_persist_with_their_prices_and_order(): void
    {
        $combo = $this->comboWith([500, 300]);

        $items = $combo->comboItems()->get();

        $this->assertSame(['500.00', '300.00'], $items->map(fn (ServiceComboItem $item) => (string) $item->price)->all());
        $this->assertTrue($items[0]->combo->is($combo));
        $this->assertDatabaseHas('services', ['id' => $combo->id, 'is_combo' => true]);
    }

    public function test_deleting_a_combo_removes_its_items_but_keeps_the_component_services(): void
    {
        $combo = $this->comboWith([500, 300]);
        $componentId = $combo->comboItems[0]->component_service_id;

        $combo->forceDelete();

        $this->assertDatabaseCount('service_combo_items', 0);
        $this->assertDatabaseHas('services', ['id' => $componentId]);
    }

    public function test_a_component_service_can_belong_to_several_combos(): void
    {
        $combo = $this->comboWith([500, 300]);
        $component = Service::query()->findOrFail($combo->comboItems[0]->component_service_id);
        ServiceComboItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $combo->branch_id,
            'component_service_id' => $component->id,
        ]);

        $this->assertSame(2, $component->includedInCombos()->count());
    }

    public function test_combo_items_are_isolated_per_tenant(): void
    {
        $this->comboWith([500, 300]);
        $otherTenantItem = ServiceComboItem::factory()->create();

        $this->assertSame(2, ServiceComboItem::query()->count());
        $this->assertDatabaseHas('service_combo_items', ['id' => $otherTenantItem->id]);
    }
}
