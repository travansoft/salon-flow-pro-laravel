<?php

namespace App\Http\Requests\BridalEngagements;

use App\Enums\BridalDressType;
use App\Enums\BridalVenueType;
use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBridalEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_studio_trial' => $this->boolean('has_studio_trial'),
            'groom_makeup' => $this->boolean('groom_makeup'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()->id;

        return [
            'contact_number' => ['required', 'string', 'max:20'],
            'bride_name' => ['required', 'string', 'max:255'],
            'client_id' => [
                'nullable', 'integer',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'event_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'venue_type' => ['required', Rule::enum(BridalVenueType::class)],
            'home_location' => ['nullable', 'required_if:venue_type,home', 'string', 'max:1000'],
            'has_studio_trial' => ['boolean'],
            'trial_date' => ['nullable', 'required_if:has_studio_trial,true', 'date', 'before_or_equal:event_date'],
            'ready_time' => ['required', 'date_format:H:i'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'advance_amount' => ['nullable', 'numeric', 'min:0', 'lte:total_amount'],
            'guest_makeup_count' => ['nullable', 'integer', 'min:0'],
            'groom_makeup' => ['boolean'],
            'dress_type' => ['required', Rule::enum(BridalDressType::class)],
            'saree_drapist_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
