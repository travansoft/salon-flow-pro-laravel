<?php

namespace App\Http\Requests\Staff;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
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
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('staff_profiles', 'email')->where('tenant_id', $tenantId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation_id' => [
                'nullable', 'integer',
                Rule::exists('designations', 'id')->where('tenant_id', $tenantId),
            ],
            'is_active' => ['sometimes', 'boolean'],

            'create_login' => ['sometimes', 'nullable', 'boolean'],
            'username' => [
                'required_if:create_login,1', 'nullable', 'string', 'max:255',
                Rule::unique('users', 'username')->where('tenant_id', $tenantId),
            ],
            'password' => ['required_if:create_login,1', 'nullable', 'string', 'min:8'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],

            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            'employee_code' => ['nullable', 'string', 'max:100'],
            'date_of_joining' => ['nullable', 'date'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'reporting_manager_id' => [
                'nullable', 'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId),
            ],

            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],

            'government_id_number' => ['nullable', 'string', 'max:100'],

            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => [
                'integer',
                Rule::exists('services', 'id')->where('tenant_id', $tenantId),
            ],

            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
