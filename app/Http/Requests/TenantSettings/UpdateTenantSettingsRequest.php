<?php

namespace App\Http\Requests\TenantSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
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
        return [
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gst_number' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'gst_state_code' => ['nullable', 'string', 'size:2'],
            'default_gst_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'print_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:500'],
            'ui_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:500'],
            'remove_print_logo' => ['sometimes', 'boolean'],
            'remove_ui_logo' => ['sometimes', 'boolean'],
        ];
    }
}
