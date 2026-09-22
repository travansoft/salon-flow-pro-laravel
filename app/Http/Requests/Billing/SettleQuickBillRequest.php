<?php

namespace App\Http\Requests\Billing;

use App\Models\Service;
use App\Services\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettleQuickBillRequest extends FormRequest
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
            'client_id' => [
                'nullable', 'integer',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_gst_number' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.service_id' => [
                'nullable', 'integer',
                Rule::exists('services', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.staff_profile_id' => [
                'nullable', 'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $index = explode('.', $attribute)[1];
                    $serviceId = $this->input("items.{$index}.service_id");

                    if (! $serviceId) {
                        return;
                    }

                    $isEligible = Service::query()->find($serviceId)
                        ?->staff()->where('staff_profiles.id', $value)->exists();

                    if (! $isEligible) {
                        $fail('The selected staff member is not eligible to perform this service.');
                    }
                },
            ],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'items.*.unit_price' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'upi'])],
        ];
    }
}
