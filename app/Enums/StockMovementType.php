<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Manual = 'manual';
    case Purchase = 'purchase';
    case Expired = 'expired';
    case Damaged = 'damaged';
    case Usage = 'usage';
    case UsageReversal = 'usage_reversal';
    case CountCorrection = 'count_correction';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual adjustment',
            self::Purchase => 'Purchase',
            self::Expired => 'Expired',
            self::Damaged => 'Damaged',
            self::Usage => 'Used in bill',
            self::UsageReversal => 'Bill cancelled',
            self::CountCorrection => 'Count correction (estimate variance)',
        };
    }
}
