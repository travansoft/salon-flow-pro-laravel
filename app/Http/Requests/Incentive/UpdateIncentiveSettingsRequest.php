<?php

namespace App\Http\Requests\Incentive;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateIncentiveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'servicing_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'referring_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = bcadd((string) $this->input('servicing_share_percent'), (string) $this->input('referring_share_percent'), 2);

                if (bccomp($total, '100', 2) !== 0) {
                    $validator->errors()->add('referring_share_percent', 'The servicing and referring shares must add up to 100%.');
                }
            },
        ];
    }
}
