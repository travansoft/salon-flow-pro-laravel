<?php

namespace App\Repositories\Eloquent;

use App\Models\StaffTarget;
use App\Repositories\Contracts\StaffTargetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class StaffTargetRepository implements StaffTargetRepositoryInterface
{
    public function __construct(private StaffTarget $model) {}

    /** @return Collection<int, StaffTarget> */
    public function getForMonth(Carbon $month): Collection
    {
        return $this->model->whereDate('month', $month->copy()->startOfMonth()->toDateString())->get();
    }

    public function upsert(int $tenantId, int $staffProfileId, Carbon $month, string $targetAmount): StaffTarget
    {
        return $this->model->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'staff_profile_id' => $staffProfileId,
                'month' => $month->copy()->startOfMonth()->toDateString(),
            ],
            ['target_amount' => $targetAmount],
        );
    }

    public function deleteFor(int $staffProfileId, Carbon $month): void
    {
        $this->model
            ->where('staff_profile_id', $staffProfileId)
            ->whereDate('month', $month->copy()->startOfMonth()->toDateString())
            ->delete();
    }
}
