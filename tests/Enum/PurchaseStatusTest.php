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

    public function testToggledFromGiftedGoesBought(): void
    {
        self::assertSame(PurchaseStatus::Bought, PurchaseStatus::Gifted->toggled());
    }

    public function testToggledFromBorrowedGoesBought(): void
    {
        self::assertSame(PurchaseStatus::Bought, PurchaseStatus::Borrowed->toggled());
    }

    public function testGiftedLabelAndPill(): void
    {
        self::assertSame('gifted', PurchaseStatus::Gifted->value);
        self::assertSame('Offert', PurchaseStatus::Gifted->label());
        self::assertSame('pill-peche', PurchaseStatus::Gifted->pillClass());
    }

    public function testBorrowedLabelAndPill(): void
    {
        self::assertSame('borrowed', PurchaseStatus::Borrowed->value);
        self::assertSame('Emprunté', PurchaseStatus::Borrowed->label());
        self::assertSame('pill-lavande', PurchaseStatus::Borrowed->pillClass());
    }

    public function testEveryCaseHasNonEmptyLabelAndPillClass(): void
    {
        foreach (PurchaseStatus::cases() as $status) {
            self::assertNotSame('', $status->label());
            self::assertStringStartsWith('pill-', $status->pillClass());
        }
    }
}
