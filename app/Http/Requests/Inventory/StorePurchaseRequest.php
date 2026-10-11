<?php

namespace App\Http\Requests\Inventory;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
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
            'product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
