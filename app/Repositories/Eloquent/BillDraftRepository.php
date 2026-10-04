<?php

namespace App\Repositories\Eloquent;

use App\Models\BillDraft;
use App\Repositories\Contracts\BillDraftRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BillDraftRepository implements BillDraftRepositoryInterface
{
    public function __construct(private BillDraft $model) {}

    /** @return Collection<int, BillDraft> */
    public function forUser(int $userId): Collection
    {
        return $this->model->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findForUser(int $id, int $userId): ?BillDraft
    {
        return $this->model->where('user_id', $userId)->find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): BillDraft
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(BillDraft $draft, array $data): BillDraft
    {
        $draft->update($data);

        return $draft;
    }

    public function delete(BillDraft $draft): void
    {
        $draft->delete();
    }
}
