<?php

namespace App\Services;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;

class BranchService
{
    public function __construct(
        private BranchRepositoryInterface $branchRepository,
        private TenantContext $tenantContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Branch
    {
        $tenant = $this->tenantContext->get();

        return $this->branchRepository->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'invoice_prefix' => $data['invoice_prefix'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'gst_state_code' => $data['gst_state_code'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(Branch $branch, array $data): Branch
    {
        return $this->branchRepository->update($branch, [
            'name' => $data['name'] ?? $branch->name,
            'slug' => $data['slug'] ?? $branch->slug,
            'invoice_prefix' => $data['invoice_prefix'] ?? $branch->invoice_prefix,
            'address' => array_key_exists('address', $data) ? $data['address'] : $branch->address,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $branch->phone,
            'gst_state_code' => array_key_exists('gst_state_code', $data) ? $data['gst_state_code'] : $branch->gst_state_code,
            'is_active' => $data['is_active'] ?? $branch->is_active,
        ]);
    }

    public function deactivate(Branch $branch): Branch
    {
        return $this->branchRepository->update($branch, ['is_active' => false]);
    }
}
