<?php

namespace App\Enum;

enum PurchaseStatus: string
{
    case ToBuy = 'to_buy';
    case Bought = 'bought';
    case Lent = 'lent';
    case Gifted = 'gifted';
    case Borrowed = 'borrowed';

    public function label(): string
    {
        return match ($this) {
            self::ToBuy => 'À acheter',
            self::Bought => 'Acheté',
            self::Lent => 'Prêté',
            self::Gifted => 'Offert',
            self::Borrowed => 'Emprunté',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::ToBuy => 'pill-rose',
            self::Bought => 'pill-sauge',
            self::Lent => 'pill-outline',
            self::Gifted => 'pill-peche',
            self::Borrowed => 'pill-lavande',
        };
    }

    /**
     * Two-state toggle used by the library card: a bought book becomes "to buy"
     * again, and any not-yet-bought state (to buy / lent / gifted / borrowed) becomes bought.
     */
    public function toggled(): self
    {
        return $this === self::Bought ? self::ToBuy : self::Bought;
    }
}
