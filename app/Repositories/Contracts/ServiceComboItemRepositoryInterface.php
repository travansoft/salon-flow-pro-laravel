<?php

namespace App\Repositories\Contracts;

use App\Models\Service;

interface ServiceComboItemRepositoryInterface
{
    /** @param array<int, array{service_id: int|string, price: float|int|string}> $items */
    public function syncForCombo(Service $combo, array $items): void;
}
