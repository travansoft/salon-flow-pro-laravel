<?php

namespace App\Http\Requests\BridalEngagements;

use App\Models\BillPayment;
use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBridalEngagementBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()->id;

        return [
            'bill_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in([BillPayment::MethodCash, BillPayment::MethodCard, BillPayment::MethodUpi])],
            'staff' => ['required', 'array', 'min:1'],
            'staff.*.staff_profile_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'staff.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
