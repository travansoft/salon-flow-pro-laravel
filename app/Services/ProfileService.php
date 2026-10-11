<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;

class ProfileService
{
    public function __construct(private TenantUserRepositoryInterface $users) {}

    public function changePassword(User $user, string $newPassword): User
    {
        return $this->users->update($user, ['password' => $newPassword]);
    }
}
