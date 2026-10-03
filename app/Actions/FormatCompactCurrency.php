<?php

namespace App\Actions;

class FormatCompactCurrency
{
    public function handle(float $amount): string
    {
        $absolute = abs($amount);
        $sign = $amount < 0 ? '-' : '';

        if ($absolute >= 10000000) {
            return $sign.'₹'.$this->trim($absolute / 10000000).' Cr';
        }

        if ($absolute >= 100000) {
            return $sign.'₹'.$this->trim($absolute / 100000).' L';
        }

        if ($absolute >= 1000) {
            return $sign.'₹'.$this->trim($absolute / 1000).' K';
        }

        return $sign.'₹'.$this->trim($absolute);
    }

    private function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
