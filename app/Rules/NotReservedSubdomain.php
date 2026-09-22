<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotReservedSubdomain implements ValidationRule
{
    /** @var array<int, string> */
    private const ReservedSlugs = ['login', 'register'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array($value, config('tenancy.reserved_subdomains'), true) || in_array($value, self::ReservedSlugs, true)) {
            $fail('The :attribute "'.$value.'" is reserved and cannot be used.');
        }
    }
}
