<?php

namespace App\Http\Requests\Incentive;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class CopyStaffTargetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'from_month' => ['required', 'date_format:Y-m'],
            'to_month' => ['required', 'date_format:Y-m'],
        ];
    }

    public function fromMonth(): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $this->validated('from_month'));
    }

    public function toMonth(): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $this->validated('to_month'));
    }
}
