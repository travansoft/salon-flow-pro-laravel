<?php

namespace App\Repositories\Eloquent;

use App\Models\StaffIncentive;
use App\Models\StaffProfile;
use App\Repositories\Contracts\StaffIncentiveRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class StaffIncentiveRepository implements StaffIncentiveRepositoryInterface
{
    public function __construct(private StaffIncentive $model) {}

    public function findById(int $id): ?StaffIncentive
    {
        return $this->model->find($id);
    }

    /** @return Collection<int, StaffIncentive> */
    public function getAll(): Collection
    {
        return $this->model->with(['staffProfile'])
            ->orderByDesc('awarded_date')
            ->get();
    }

    /** @return Collection<int, StaffIncentive> */
    public function getForStaffBetweenDates(StaffProfile $staff, Carbon $from, Carbon $to): Collection
    {
        return $this->model->where('staff_profile_id', $staff->id)
            ->whereBetween('awarded_date', [$from->toDateString(), $to->toDateString()])
            ->get();
    }

    /** @return Collection<int, StaffIncentive> */
    public function getBetweenDates(Carbon $from, Carbon $to): Collection
    {
        return $this->model
            ->whereBetween('awarded_date', [$from->toDateString(), $to->toDateString()])
            ->get();
    }

    /** @return Collection<int, StaffIncentive> */
    public function getListBetweenDates(Carbon $from, Carbon $to, ?int $staffProfileId = null): Collection
    {
        return $this->model->with(['staffProfile', 'awardedBy'])
            ->whereBetween('awarded_date', [$from->toDateString(), $to->toDateString()])
            ->when($staffProfileId !== null, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->orderByDesc('awarded_date')
            ->orderByDesc('id')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): StaffIncentive
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(StaffIncentive $incentive, array $data): StaffIncentive
    {
        $incentive->update($data);

        return $incentive;
    }

    public function delete(StaffIncentive $incentive): bool
    {
        return (bool) $incentive->delete();
    }
}
