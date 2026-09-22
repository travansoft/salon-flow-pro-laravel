<?php

namespace App\Http\Requests\Services;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:20',
                Rule::unique('services', 'code')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('service_categories', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'requires_rate_confirmation' => ['sometimes', 'boolean'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hsn_sac_code' => ['nullable', 'string', 'max:10'],

            'staff_ids' => ['sometimes', 'array'],
            'staff_ids.*' => [
                'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
