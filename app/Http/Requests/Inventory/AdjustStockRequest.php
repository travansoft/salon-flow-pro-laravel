<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
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
        $isWriteOff = in_array($this->input('type'), [StockMovementType::Expired->value, StockMovementType::Damaged->value], true);

        return [
            'type' => ['nullable', Rule::in([
                StockMovementType::Manual->value,
                StockMovementType::Expired->value,
                StockMovementType::Damaged->value,
            ])],
            'quantity_delta' => $isWriteOff
                ? ['required', 'numeric', 'gt:0']
                : ['required', 'numeric'],
            'reason' => [$isWriteOff ? 'nullable' : 'required', 'string', 'max:255'],
        ];
    }
}
