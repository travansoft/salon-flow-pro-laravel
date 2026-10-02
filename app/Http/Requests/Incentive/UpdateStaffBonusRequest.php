<?php

namespace App\Http\Requests\Incentive;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()->id;

        return [
            'staff_profile_id' => [
                'required', 'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
            'awarded_date' => ['required', 'date'],
        ];
    }
}
