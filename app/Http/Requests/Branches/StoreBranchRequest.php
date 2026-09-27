<?php

namespace App\Http\Requests\Branches;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invoice_prefix' => Str::upper((string) $this->input('invoice_prefix')),
        ]);
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
            'slug' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('branches', 'slug')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'invoice_prefix' => [
                'required', 'string', 'regex:/^[A-Z0-9\-]{2,10}$/',
                Rule::unique('branches', 'invoice_prefix')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gst_state_code' => ['nullable', 'string', 'size:2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
