<?php

namespace App\Http\Requests\SuperAdmin;

use App\Rules\NotReservedSubdomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant), new NotReservedSubdomain],
            'subdomain' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('tenants', 'subdomain')->ignore($tenant), new NotReservedSubdomain],
            'custom_domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants', 'custom_domain')->ignore($tenant)],
            'is_active' => ['sometimes', 'boolean'],
            'print_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:500'],
            'ui_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:500'],
            'remove_print_logo' => ['sometimes', 'boolean'],
            'remove_ui_logo' => ['sometimes', 'boolean'],
        ];
    }
}
