<?php

namespace App\Services;

use Carbon\CarbonInterface;

class FinancialYear
{
    public static function forDate(CarbonInterface $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return "{$startYear}-".str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT);
    }
}
