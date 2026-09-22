<?php

namespace App\Services\SuperAdmin;

use App\Models\PlatformAdmin;
use App\Repositories\Contracts\PlatformAdminRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class PlatformAdminService
{
    public function __construct(private PlatformAdminRepositoryInterface $platformAdminRepository) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): PlatformAdmin
    {
        return $this->platformAdminRepository->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(PlatformAdmin $platformAdmin, array $data): PlatformAdmin
    {
        $profileData = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
        ];

        if (! empty($data['password'])) {
            $profileData['password'] = Hash::make($data['password']);
        }

        return $this->platformAdminRepository->update($platformAdmin, $profileData);
    }

    public function delete(PlatformAdmin $platformAdmin): bool
    {
        return $this->platformAdminRepository->delete($platformAdmin);
    }
}
