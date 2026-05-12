<?php

namespace App\Enum;

enum PurchaseStatus: string
{
    case ToBuy = 'to_buy';
    case Bought = 'bought';
    case Lent = 'lent';

    public function label(): string
    {
        return match ($this) {
            self::ToBuy => 'À acheter',
            self::Bought => 'Acheté',
            self::Lent => 'Prêté',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::ToBuy => 'pill-rose',
            self::Bought => 'pill-sauge',
            self::Lent => 'pill-outline',
        };
    }
}
