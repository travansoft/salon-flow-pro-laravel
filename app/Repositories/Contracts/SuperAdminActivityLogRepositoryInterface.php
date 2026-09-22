<?php

namespace App\Repositories\Contracts;

use App\Models\SuperAdminActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SuperAdminActivityLogRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): SuperAdminActivityLog;

    /** @return LengthAwarePaginator<int, SuperAdminActivityLog> */
    public function paginateLatest(int $perPage = 25): LengthAwarePaginator;
}
