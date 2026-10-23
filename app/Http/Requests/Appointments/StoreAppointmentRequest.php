<?php

namespace App\Http\Requests\Appointments;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
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
            'client_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'client_phone' => ['required_without:client_id', 'nullable', 'string', 'max:30'],
            'start_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => [
                'required', 'integer',
                Rule::exists('services', 'id')->where('tenant_id', $tenantId),
            ],
            'services.*.staff_profile_id' => [
                'required', 'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
