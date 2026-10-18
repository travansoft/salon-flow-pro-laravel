<?php

namespace App\Http\Requests\Roles;

use App\Models\Role;
use App\Services\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $role = $this->route('role');
        $tenantId = app(TenantContext::class)->get()?->id;

        $nameRules = ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]*$/'];

        if (! $role instanceof Role || ! $role->isBuiltIn()) {
            $nameRules[] = Rule::unique('roles', 'name')
                ->where(function (Builder $query) use ($tenantId): void {
                    $query->where('guard_name', 'web');

                    if ($tenantId === null) {
                        $query->whereNull('tenant_id');

                        return;
                    }

                    $query->where(function (Builder $query) use ($tenantId): void {
                        $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                    });
                })
                ->ignore($role instanceof Role ? $role->id : null);
        }

        return [
            'name' => $nameRules,
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $isTenantRequest = app(TenantContext::class)->has();

                    if ($isTenantRequest && ! $this->user()->can($value)) {
                        $fail('You cannot grant a permission you do not hold yourself.');
                    }
                },
            ],
        ];
    }
}
