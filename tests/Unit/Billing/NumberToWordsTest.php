<?php

namespace Tests\Unit\Billing;

use App\Services\NumberToWords;
use Tests\TestCase;

class NumberToWordsTest extends TestCase
{
    public function test_converts_a_whole_rupee_amount(): void
    {
        $this->assertSame('Two Hundred Thirty Six Rupees Only', NumberToWords::rupees(236.00));
    }

    public function test_converts_zero(): void
    {
        $this->assertSame('Zero Rupees Only', NumberToWords::rupees(0.0));
    }

    public function test_converts_paise(): void
    {
        $this->assertSame('Eighty Rupees and Fifty Paise Only', NumberToWords::rupees(80.50));
    }

    public function test_converts_thousands_and_lakhs(): void
    {
        $this->assertSame('One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees Only', NumberToWords::rupees(123456.00));
    }

    public function test_converts_teens_correctly(): void
    {
        $this->assertSame('Nineteen Rupees Only', NumberToWords::rupees(19.00));
    }
}
