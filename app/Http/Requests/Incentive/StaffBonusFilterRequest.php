<?php

namespace App\Http\Requests\Incentive;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StaffBonusFilterRequest extends IncentiveMonthRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()->id;

        return [
            ...parent::rules(),
            'staff_profile_id' => [
                'nullable', 'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    public function staffProfileId(): ?int
    {
        $id = $this->validated('staff_profile_id');

        return $id === null ? null : (int) $id;
    }
}
