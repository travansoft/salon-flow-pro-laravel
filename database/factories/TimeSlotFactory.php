<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TimeSlot;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hour = fake()->numberBetween(9, 19);
        $minute = fake()->randomElement([0, 30]);

        $start = sprintf('%02d:%02d:00', $hour, $minute);
        $endMinute = $minute + 30;
        $end = $endMinute >= 60
            ? sprintf('%02d:%02d:00', $hour + 1, $endMinute - 60)
            : sprintf('%02d:%02d:00', $hour, $endMinute);

        return [
            'tenant_id' => fn () => Tenant::factory()->create()->id,
            'branch_id' => function (array $attributes) {
                $branch = app(BranchContext::class)->get();

                if ($branch && $branch->tenant_id === $attributes['tenant_id']) {
                    return $branch->id;
                }

                return Branch::defaultForTenant($attributes['tenant_id'])->id;
            },
            'start_time' => $start,
            'end_time' => $end,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
