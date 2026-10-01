<?php

namespace App\Http\Requests\Incentive;

use App\Models\StaffProfile;
use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class SetStaffTargetsRequest extends IncentiveMonthRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'targets' => ['required', 'array'],
            'targets.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $staffIds = array_keys((array) $this->input('targets', []));
                $tenantId = app(TenantContext::class)->get()->id;

                $validCount = StaffProfile::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', $staffIds)
                    ->count();

                if ($validCount !== count($staffIds)) {
                    $validator->errors()->add('targets', 'One or more staff members are invalid.');
                }
            },
        ];
    }

    /** @return array<int, ?string> */
    public function targets(): array
    {
        $targets = [];

        foreach ($this->validated('targets') as $staffProfileId => $amount) {
            $targets[(int) $staffProfileId] = $amount;
        }

        return $targets;
    }
}
