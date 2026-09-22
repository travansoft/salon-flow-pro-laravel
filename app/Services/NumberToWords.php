<?php

namespace App\Services;

class NumberToWords
{
    /** @var array<int, string> */
    private const Ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    /** @var array<int, string> */
    private const Tens = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function rupees(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = self::convert($rupees).' Rupees';

        if ($paise > 0) {
            $words .= ' and '.self::convert($paise).' Paise';
        }

        return $words.' Only';
    }

    private static function convert(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        foreach (['Crore' => 10000000, 'Lakh' => 100000, 'Thousand' => 1000] as $label => $divisor) {
            if ($number >= $divisor) {
                $parts[] = self::convertBelowThousand(intdiv($number, $divisor)).' '.$label;
                $number %= $divisor;
            }
        }

        if ($number > 0) {
            $parts[] = self::convertBelowThousand($number);
        }

        return implode(' ', $parts);
    }

    private static function convertBelowThousand(int $number): string
    {
        if ($number >= 100) {
            $result = self::Ones[intdiv($number, 100)].' Hundred';
            $remainder = $number % 100;

            return $remainder > 0 ? $result.' '.self::convertBelowHundred($remainder) : $result;
        }

        return self::convertBelowHundred($number);
    }

    private static function convertBelowHundred(int $number): string
    {
        if ($number < 20) {
            return self::Ones[$number];
        }

        $tens = self::Tens[intdiv($number, 10)];
        $ones = self::Ones[$number % 10];

        return $ones !== '' ? "{$tens} {$ones}" : $tens;
    }
}
