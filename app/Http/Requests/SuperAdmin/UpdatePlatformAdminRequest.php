<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $platformAdmin = $this->route('platformAdmin');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('platform_admins', 'username')->ignore($platformAdmin)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('platform_admins', 'email')->ignore($platformAdmin)],
            'password' => ['nullable', 'string', 'min:4'],
        ];
    }
}
