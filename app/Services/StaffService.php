<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\User;
use App\Repositories\Contracts\StaffProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class StaffService
{
    private const ProfileFields = [
        'name', 'email', 'designation_id', 'phone', 'photo_path', 'is_active',
        'date_of_birth', 'gender', 'address', 'emergency_contact_name', 'emergency_contact_phone',
        'employee_code', 'date_of_joining', 'employment_type', 'reporting_manager_id',
        'base_salary', 'bank_account_number', 'bank_ifsc',
        'government_id_number', 'id_document_path', 'contract_document_path',
    ];

    public function __construct(
        private StaffProfileRepositoryInterface $staffProfileRepository,
        private TenantContext $tenantContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): StaffProfile
    {
        $tenant = $this->tenantContext->get();

        return DB::transaction(function () use ($data, $tenant): StaffProfile {
            $profileData = array_intersect_key($data, array_flip(self::ProfileFields));
            $profileData['tenant_id'] = $tenant->id;

            if (! empty($data['create_login'])) {
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'email' => $data['email'] ?? null,
                    'password' => Hash::make($data['password']),
                ]);

                $user->syncRoles($data['roles'] ?? []);
                $user->branches()->sync($data['branch_ids'] ?? []);

                $profileData['user_id'] = $user->id;
            }

            $staffProfile = $this->staffProfileRepository->create($profileData);

            if (! empty($data['service_ids'])) {
                $staffProfile->services()->sync($data['service_ids']);
            }

            return $staffProfile;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(StaffProfile $staffProfile, array $data): StaffProfile
    {
        return DB::transaction(function () use ($staffProfile, $data): StaffProfile {
            $profileData = array_intersect_key($data, array_flip(self::ProfileFields));

            $this->staffProfileRepository->update($staffProfile, $profileData);

            if (array_key_exists('service_ids', $data)) {
                $staffProfile->services()->sync($data['service_ids']);
            }

            if ($staffProfile->hasLogin() && array_key_exists('roles', $data)) {
                $staffProfile->user->syncRoles($data['roles']);
            }

            if ($staffProfile->hasLogin() && array_key_exists('branch_ids', $data)) {
                $staffProfile->user->branches()->sync($data['branch_ids']);
            }

            return $staffProfile->refresh();
        });
    }

    public function deactivate(StaffProfile $staffProfile): StaffProfile
    {
        return $this->staffProfileRepository->update($staffProfile, ['is_active' => false]);
    }

    public function disableLogin(StaffProfile $staffProfile): StaffProfile
    {
        if (! $staffProfile->hasLogin()) {
            throw new InvalidArgumentException('This staff member has no login to disable.');
        }

        return DB::transaction(function () use ($staffProfile): StaffProfile {
            $staffProfile->user->update(['disabled_at' => now()]);

            DB::table('sessions')->where('user_id', $staffProfile->user_id)->delete();

            return $staffProfile->refresh();
        });
    }

    public function enableLogin(StaffProfile $staffProfile): StaffProfile
    {
        if (! $staffProfile->hasLogin()) {
            throw new InvalidArgumentException('This staff member has no login to enable.');
        }

        $staffProfile->user->update(['disabled_at' => null]);

        return $staffProfile->refresh();
    }
}
