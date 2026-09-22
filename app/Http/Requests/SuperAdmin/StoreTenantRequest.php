<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'subdomain' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,subdomain'],
            'custom_domain' => ['nullable', 'string', 'max:255', 'unique:tenants,custom_domain'],
            'is_active' => ['sometimes', 'boolean'],
            'print_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:500'],
            'ui_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:500'],
        ];
    }
}
