<?php

namespace App\Http\Requests\Users;

use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()->id;
        $editing = $this->route('managedUser');
        $passwordRequirement = $editing ? 'nullable' : 'required';

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'username')->where('tenant_id', $tenantId)->ignore($editing?->id),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->where('tenant_id', $tenantId)->ignore($editing?->id),
            ],
            'password' => [$passwordRequirement, 'string', 'min:8'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(function (Builder $query) use ($tenantId): void {
                    $query->where(function (Builder $query) use ($tenantId): void {
                        $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                    });
                }),
            ],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
