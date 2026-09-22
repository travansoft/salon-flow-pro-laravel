<?php

namespace App\Repositories\Eloquent;

use App\Models\SuperAdminActivityLog;
use App\Repositories\Contracts\SuperAdminActivityLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SuperAdminActivityLogRepository implements SuperAdminActivityLogRepositoryInterface
{
    public function __construct(private SuperAdminActivityLog $model) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): SuperAdminActivityLog
    {
        return $this->model->create($data);
    }

    /** @return LengthAwarePaginator<int, SuperAdminActivityLog> */
    public function paginateLatest(int $perPage = 25): LengthAwarePaginator
    {
        return $this->model->with('platformAdmin')->latest()->paginate($perPage);
    }
}
