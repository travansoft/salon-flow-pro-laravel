<?php

namespace App\Repositories\Contracts;

use App\Models\IncentiveSetting;

interface IncentiveSettingRepositoryInterface
{
    public function findForTenant(): ?IncentiveSetting;

    /** @param array<string, mixed> $data */
    public function create(array $data): IncentiveSetting;

    /** @param array<string, mixed> $data */
    public function update(IncentiveSetting $setting, array $data): IncentiveSetting;
}
