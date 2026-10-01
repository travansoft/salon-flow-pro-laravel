<?php

namespace App\Http\Requests\Incentive;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncentiveSlabRequest extends FormRequest
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
            'min_achievement_percent' => [
                'required', 'numeric', 'min:0', 'max:1000',
                Rule::unique('incentive_slabs', 'min_achievement_percent')->where('tenant_id', $tenantId),
            ],
            'incentive_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
