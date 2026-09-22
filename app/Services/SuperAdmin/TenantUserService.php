<?php

namespace App\Services\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantUserService
{
    public function __construct(private TenantUserRepositoryInterface $tenantUserRepository) {}

    /** @param array<string, mixed> $data */
    public function create(Tenant $tenant, array $data): User
    {
        return DB::transaction(function () use ($tenant, $data): User {
            $user = $this->tenantUserRepository->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
            ]);

            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $profileData = [
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
            ];

            if (! empty($data['password'])) {
                $profileData['password'] = Hash::make($data['password']);
            }

            $this->tenantUserRepository->update($user, $profileData);

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles']);
            }

            return $user->refresh();
        });
    }

    public function disableLogin(User $user): User
    {
        $this->tenantUserRepository->update($user, ['disabled_at' => now()]);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        return $user->refresh();
    }

    public function enableLogin(User $user): User
    {
        $this->tenantUserRepository->update($user, ['disabled_at' => null]);

        return $user->refresh();
    }
}
