<?php

namespace App\Tests\Enum;

use App\Enum\PurchaseStatus;
use PHPUnit\Framework\TestCase;

class PurchaseStatusTest extends TestCase
{
    public function testToggledFromBoughtGoesToBuy(): void
    {
        self::assertSame(PurchaseStatus::ToBuy, PurchaseStatus::Bought->toggled());
    }

    public function testToggledFromToBuyGoesBought(): void
    {
        self::assertSame(PurchaseStatus::Bought, PurchaseStatus::ToBuy->toggled());
    }

    public function testToggledFromLentGoesBought(): void
    {
        self::assertSame(PurchaseStatus::Bought, PurchaseStatus::Lent->toggled());
    }
}
