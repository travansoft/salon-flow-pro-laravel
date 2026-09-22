<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $user = $this->route('tenantUser');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->where('tenant_id', $tenant->id)->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->where('tenant_id', $tenant->id)->ignore($user)],
            'password' => ['nullable', 'string', 'min:4'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
