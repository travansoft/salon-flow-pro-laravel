<?php

namespace App\Services;

use App\Models\BillDraft;
use App\Models\User;
use App\Repositories\Contracts\BillDraftRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BillDraftService
{
    public function __construct(
        private BillDraftRepositoryInterface $billDraftRepository,
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
    ) {}

    /** @return Collection<int, BillDraft> */
    public function listForUser(User $user): Collection
    {
        return $this->billDraftRepository->forUser($user->id);
    }

    public function findForUser(User $user, int $draftId): ?BillDraft
    {
        return $this->billDraftRepository->findForUser($draftId, $user->id);
    }

    /** @param array<string, mixed> $payload */
    public function save(User $user, array $payload, ?int $draftId = null): ?BillDraft
    {
        $attributes = [
            'client_name' => $this->nullIfBlank($payload['client_name'] ?? null),
            'client_phone' => $this->nullIfBlank($payload['client_phone'] ?? null),
            'item_count' => count($payload['lines'] ?? []),
            'total' => $this->totalOf($payload),
            'payload' => $payload,
        ];

        if ($draftId === null) {
            return $this->billDraftRepository->create([
                ...$attributes,
                'tenant_id' => $this->tenantContext->get()->id,
                'branch_id' => $this->branchContext->get()->id,
                'user_id' => $user->id,
            ]);
        }

        $draft = $this->billDraftRepository->findForUser($draftId, $user->id);

        if (! $draft) {
            return null;
        }

        return $this->billDraftRepository->update($draft, $attributes);
    }

    public function discard(User $user, int $draftId): bool
    {
        $draft = $this->billDraftRepository->findForUser($draftId, $user->id);

        if (! $draft) {
            return false;
        }

        $this->billDraftRepository->delete($draft);

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function totalOf(array $payload): float
    {
        $subtotal = collect($payload['lines'] ?? [])->sum(
            fn (array $line): float => (float) ($line['priceInclusive'] ?? 0) * (int) ($line['quantity'] ?? 1)
        );

        $discountValue = (float) ($payload['discount_value'] ?? 0);

        $discount = ($payload['discount_mode'] ?? 'percent') === 'amount'
            ? min($discountValue, $subtotal)
            : $subtotal * (min($discountValue, 100) / 100);

        return round($subtotal - $discount, 2);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
