<?php

namespace App\Repositories\Contracts;

use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface StaffIncentiveRepositoryInterface
{
    public function findById(int $id): ?StaffIncentive;

    /** @return Collection<int, StaffIncentive> */
    public function getAll(): Collection;

    /** @return Collection<int, StaffIncentive> */
    public function getForStaffBetweenDates(StaffProfile $staff, Carbon $from, Carbon $to): Collection;

    /** @return Collection<int, StaffIncentive> */
    public function getBetweenDates(Carbon $from, Carbon $to): Collection;

    /** @return Collection<int, StaffIncentive> */
    public function getListBetweenDates(Carbon $from, Carbon $to, ?int $staffProfileId = null): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): StaffIncentive;

    /** @param array<string, mixed> $data */
    public function update(StaffIncentive $incentive, array $data): StaffIncentive;

    public function delete(StaffIncentive $incentive): bool;
}
