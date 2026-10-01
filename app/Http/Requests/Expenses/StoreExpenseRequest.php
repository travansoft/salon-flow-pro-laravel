<?php

namespace App\Http\Requests\Expenses;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', 'in:cash,upi,card'],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_interval' => ['required_if:is_recurring,true', 'nullable', 'in:weekly,monthly,yearly'],
            'expense_date' => ['required', 'date'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
