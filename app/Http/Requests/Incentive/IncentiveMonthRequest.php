<?php

namespace App\Http\Requests\Incentive;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class IncentiveMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function month(): Carbon
    {
        $month = $this->validated('month');

        if (! $month) {
            return Carbon::now()->startOfMonth();
        }

        return Carbon::createFromFormat('!Y-m', $month);
    }
}
