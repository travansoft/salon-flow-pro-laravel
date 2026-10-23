<?php

namespace App\Actions;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\TenantContext;

class ResolveClientForBooking
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private TenantContext $tenantContext,
    ) {}

    public function handle(?int $clientId, ?string $name, ?string $phone): Client
    {
        if ($clientId !== null) {
            return $this->clientRepository->findById($clientId);
        }

        $phone = trim((string) $phone);
        $existing = $this->clientRepository->findByPhone($phone);

        if ($existing !== null) {
            return $existing;
        }

        return $this->clientRepository->create([
            'tenant_id' => $this->tenantContext->get()->id,
            'name' => trim((string) $name),
            'phone' => $phone,
        ]);
    }
}
