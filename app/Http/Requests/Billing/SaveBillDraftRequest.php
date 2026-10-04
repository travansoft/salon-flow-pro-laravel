<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBillDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_gst_number' => ['nullable', 'string', 'max:20'],
            'discount_mode' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'upi'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required_without_all:client_name,client_phone', 'nullable', 'array'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.serviceId' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.priceInclusive' => ['nullable', 'numeric', 'min:0'],
            'lines.*.taxRate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1'],
            'lines.*.staffProfileId' => ['nullable', 'integer'],
            'lines.*.referredByStaffProfileId' => ['nullable', 'integer'],
            'lines.*.requiresRateConfirmation' => ['nullable', 'boolean'],
            'lines.*.isCombo' => ['nullable', 'boolean'],
            'lines.*.components' => ['nullable', 'array'],
            'lines.*.components.*.serviceId' => ['nullable', 'integer'],
            'lines.*.components.*.name' => ['nullable', 'string', 'max:255'],
            'lines.*.components.*.staffProfileId' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required_without_all' => 'Add a client or at least one item before saving a draft.',
        ];
    }
}
