<?php

namespace App\Repositories\Contracts;

use App\Models\BillDraft;
use Illuminate\Database\Eloquent\Collection;

interface BillDraftRepositoryInterface
{
    /** @return Collection<int, BillDraft> */
    public function forUser(int $userId): Collection;

    public function findForUser(int $id, int $userId): ?BillDraft;

    /** @param array<string, mixed> $data */
    public function create(array $data): BillDraft;

    /** @param array<string, mixed> $data */
    public function update(BillDraft $draft, array $data): BillDraft;

    public function delete(BillDraft $draft): void;
}
