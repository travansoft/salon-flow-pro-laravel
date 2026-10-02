<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class GstReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function month(): Carbon
    {
        $month = $this->validated('month') ?? now()->format('Y-m');

        return Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
    }
}
