<?php

namespace App\Http\Requests\BridalEngagements;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachBridalEngagementBillRequest extends FormRequest
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
            'bill_id' => [
                'required', 'integer',
                Rule::exists('bills', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
