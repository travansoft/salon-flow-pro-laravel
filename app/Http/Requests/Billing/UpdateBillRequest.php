<?php

namespace App\Http\Requests\Billing;

use App\Models\Bill;
use App\Services\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
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
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:20'],
            'client_gst_number' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'bill_date' => ['nullable', 'date', 'before_or_equal:today'],
            'items' => [
                'nullable', 'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $billLineIds = $this->bill()->lineItems()->pluck('id')->all();

                    if (array_diff(array_keys((array) $value), $billLineIds) !== []) {
                        $fail('One or more items do not belong to this bill.');
                    }
                },
            ],
            'items.*.staff_profile_id' => [
                'required', 'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $lineItem = $this->bill()->lineItems()->find(explode('.', $attribute)[1]);

                    if (! $lineItem || ! $lineItem->service_id || (int) $value === $lineItem->staff_profile_id) {
                        return;
                    }

                    $isEligible = $lineItem->service?->staff()->where('staff_profiles.id', $value)->exists();

                    if (! $isEligible) {
                        $fail('The selected staff member is not eligible to perform this service.');
                    }
                },
            ],
            'items.*.referred_by_staff_profile_id' => [
                'nullable', 'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }

    private function bill(): Bill
    {
        /** @var Bill $bill */
        $bill = $this->route('bill');

        return $bill;
    }
}
