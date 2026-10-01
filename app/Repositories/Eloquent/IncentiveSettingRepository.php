<?php

namespace App\Repositories\Eloquent;

use App\Models\IncentiveSetting;
use App\Repositories\Contracts\IncentiveSettingRepositoryInterface;

class IncentiveSettingRepository implements IncentiveSettingRepositoryInterface
{
    public function __construct(private IncentiveSetting $model) {}

    public function findForTenant(): ?IncentiveSetting
    {
        return $this->model->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): IncentiveSetting
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(IncentiveSetting $setting, array $data): IncentiveSetting
    {
        $setting->update($data);

        return $setting;
    }
}
