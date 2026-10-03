<?php

namespace App\Http\Requests\Expenses;

use App\Models\BillPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExpenseFilterRequest extends FormRequest
{
    public const UncategorisedFilter = 'none';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'month' => ['nullable', 'date_format:Y-m'],
            'category_id' => ['nullable', 'string', 'regex:/^(\d+|'.self::UncategorisedFilter.')$/'],
            'payment_method' => ['nullable', Rule::in([BillPayment::MethodCash, BillPayment::MethodCard, BillPayment::MethodUpi])],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function range(): array
    {
        $validated = $this->validated();

        if (isset($validated['from'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();

            return [$from, Carbon::parse($validated['to'] ?? $from)->startOfDay()];
        }

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m-d', "{$validated['month']}-01")
            : Carbon::now();

        return [$month->copy()->startOfMonth()->startOfDay(), $month->copy()->endOfMonth()->startOfDay()];
    }

    /** @return array{category_id: ?string, payment_method: ?string, search: ?string} */
    public function filters(): array
    {
        return [
            'category_id' => $this->validated('category_id'),
            'payment_method' => $this->validated('payment_method'),
            'search' => $this->validated('search'),
        ];
    }
}
