<?php

namespace App\Repositories\Contracts;

use App\Models\StaffTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface StaffTargetRepositoryInterface
{
    /** @return Collection<int, StaffTarget> */
    public function getForMonth(Carbon $month): Collection;

    public function upsert(int $tenantId, int $staffProfileId, Carbon $month, string $targetAmount): StaffTarget;

    public function deleteFor(int $staffProfileId, Carbon $month): void;
}
