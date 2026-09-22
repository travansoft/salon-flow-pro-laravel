<?php

namespace App\Services\SuperAdmin;

use App\Repositories\Contracts\SuperAdminActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SuperAdminActivityLogger
{
    public function __construct(private SuperAdminActivityLogRepositoryInterface $activityLogRepository) {}

    public function log(string $action, string $description, ?Model $subject = null): void
    {
        $admin = Auth::guard('super_admin')->user();

        $this->logAs($admin?->id, $admin?->name ?? 'Unknown', $action, $description, $subject);
    }

    public function logAs(?int $platformAdminId, string $platformAdminName, string $action, string $description, ?Model $subject = null): void
    {
        $this->activityLogRepository->create([
            'platform_admin_id' => $platformAdminId,
            'platform_admin_name' => $platformAdminName,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
        ]);
    }
}
