<?php

namespace Tests\Unit\Rules;

use App\Rules\NotReservedSubdomain;
use Tests\TestCase;

class NotReservedSubdomainTest extends TestCase
{
    public function test_reserved_config_subdomain_fails(): void
    {
        $rule = new NotReservedSubdomain;
        $failed = false;

        $rule->validate('subdomain', 'admin', function () use (&$failed): void {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_reserved_route_word_fails(): void
    {
        $rule = new NotReservedSubdomain;
        $failed = false;

        $rule->validate('slug', 'login', function () use (&$failed): void {
            $failed = true;
        });

        $this->assertTrue($failed);
    }

    public function test_ordinary_value_passes(): void
    {
        $rule = new NotReservedSubdomain;
        $failed = false;

        $rule->validate('slug', 'mejora', function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
