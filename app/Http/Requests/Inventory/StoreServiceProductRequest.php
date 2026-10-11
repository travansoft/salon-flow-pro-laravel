<?php

namespace App\Http\Requests\Inventory;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceProductRequest extends FormRequest
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
        $serviceId = $this->route('service')?->id;

        return [
            'product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                Rule::unique('service_product_usages', 'product_id')->where('service_id', $serviceId),
            ],
            'quantity_used' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.unique' => 'This product is already linked to the service.',
        ];
    }
}
