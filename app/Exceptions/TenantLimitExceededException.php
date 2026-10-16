<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class TenantLimitExceededException extends ValidationException
{
    public static function forUsers(int $limit): self
    {
        return self::withMessages([
            'limit' => "This salon has reached its limit of {$limit} active users. Disable an existing user or ask the platform administrator to raise the limit.",
        ]);
    }

    public static function forBranches(int $limit): self
    {
        return self::withMessages([
            'limit' => "This salon has reached its limit of {$limit} active branches. Disable an existing branch or ask the platform administrator to raise the limit.",
        ]);
    }
}
