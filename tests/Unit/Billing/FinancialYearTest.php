<?php

namespace Tests\Unit\Billing;

use App\Services\FinancialYear;
use Carbon\Carbon;
use Tests\TestCase;

class FinancialYearTest extends TestCase
{
    public function test_date_in_april_starts_a_new_financial_year(): void
    {
        $this->assertSame('2026-27', FinancialYear::forDate(Carbon::parse('2026-04-01')));
    }

    public function test_date_in_march_belongs_to_the_previous_financial_year(): void
    {
        $this->assertSame('2025-26', FinancialYear::forDate(Carbon::parse('2026-03-31')));
    }

    public function test_date_in_december_belongs_to_the_financial_year_that_started_in_april(): void
    {
        $this->assertSame('2026-27', FinancialYear::forDate(Carbon::parse('2026-12-15')));
    }
}
