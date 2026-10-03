<?php

namespace Tests\Unit\Services;

use App\Models\Service;
use App\Models\User;
use App\Services\ServiceCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\ActsAsTenant;
use Tests\Concerns\CreatesIncentiveBills;
use Tests\TestCase;

class ComboCatalogServiceTest extends TestCase
{
    use ActsAsTenant, CreatesIncentiveBills, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();
        $this->useTenantAndBranchContext();
        $this->user = User::factory()->for($this->tenant)->create();
    }

    /** @return array<string, mixed> */
    private function comboData(array $overrides = []): array
    {
        $first = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $second = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        return [
            'name' => 'Bridal Combo',
            'duration_minutes' => 120,
            'is_combo' => true,
            'combo_items' => [
                ['service_id' => $first->id, 'price' => 700],
                ['service_id' => $second->id, 'price' => 300],
            ],
            ...$overrides,
        ];
    }

    public function test_combo_price_defaults_to_the_sum_of_component_prices(): void
    {
        $combo = app(ServiceCatalogService::class)->create($this->comboData(), $this->user->id);

        $this->assertTrue($combo->is_combo);
        $this->assertSame('1000.00', (string) $combo->price);
        $this->assertCount(2, $combo->comboItems);
    }

    public function test_combo_price_can_be_overridden(): void
    {
        $combo = app(ServiceCatalogService::class)->create($this->comboData(['price' => 850]), $this->user->id);

        $this->assertSame('850.00', (string) $combo->price);
    }

    public function test_combo_with_fewer_than_two_services_is_rejected(): void
    {
        $data = $this->comboData();
        $data['combo_items'] = [$data['combo_items'][0]];

        $this->expectException(InvalidArgumentException::class);

        app(ServiceCatalogService::class)->create($data, $this->user->id);
    }

    public function test_the_same_service_cannot_be_added_twice(): void
    {
        $data = $this->comboData();
        $data['combo_items'][1]['service_id'] = $data['combo_items'][0]['service_id'];

        $this->expectException(InvalidArgumentException::class);

        app(ServiceCatalogService::class)->create($data, $this->user->id);
    }

    public function test_updating_a_combo_replaces_its_components(): void
    {
        $service = app(ServiceCatalogService::class)->create($this->comboData(), $this->user->id);
        $replacement = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $keep = $service->comboItems->first();

        $updated = app(ServiceCatalogService::class)->update($service, [
            'combo_items' => [
                ['service_id' => $keep->component_service_id, 'price' => 400],
                ['service_id' => $replacement->id, 'price' => 100],
            ],
        ], $this->user->id);

        $this->assertSame('500.00', (string) $updated->price);
        $this->assertSame(
            [$keep->component_service_id, $replacement->id],
            $updated->comboItems()->pluck('component_service_id')->all(),
        );
    }

    public function test_combo_price_change_is_recorded_in_price_history(): void
    {
        $service = app(ServiceCatalogService::class)->create($this->comboData(), $this->user->id);

        app(ServiceCatalogService::class)->update($service, ['price' => 900], $this->user->id);

        $this->assertSame(2, $service->priceHistories()->count());
    }
}
