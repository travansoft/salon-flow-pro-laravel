<?php

namespace App\Services\SuperAdmin;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Http\UploadedFile;

class TenantService
{
    public function __construct(private TenantRepositoryInterface $tenantRepository) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Tenant
    {
        $data['print_logo'] = $this->encodeLogo($data['print_logo'] ?? null);
        $data['ui_logo'] = $this->encodeLogo($data['ui_logo'] ?? null);

        return $this->tenantRepository->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $data['print_logo'] = $this->resolveLogo($tenant, $data['print_logo'] ?? null, $data['remove_print_logo'] ?? false, 'print_logo');
        $data['ui_logo'] = $this->resolveLogo($tenant, $data['ui_logo'] ?? null, $data['remove_ui_logo'] ?? false, 'ui_logo');

        unset($data['remove_print_logo'], $data['remove_ui_logo']);

        return $this->tenantRepository->update($tenant, $data);
    }

    private function resolveLogo(Tenant $tenant, ?UploadedFile $file, bool $remove, string $attribute): ?string
    {
        if ($remove) {
            return null;
        }

        if (! $file) {
            return $tenant->{$attribute};
        }

        return $this->encodeLogo($file);
    }

    private function encodeLogo(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        return "data:{$file->getMimeType()};base64,{$base64}";
    }
}
