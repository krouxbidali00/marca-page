<?php

namespace App\Tests\Enum;

use App\Enum\ReadingStatus;
use PHPUnit\Framework\TestCase;

class ReadingStatusTest extends TestCase
{
    public function testToggledFromFinishedGoesToRead(): void
    {
        self::assertSame(ReadingStatus::ToRead, ReadingStatus::Finished->toggled());
    }

    public function testToggledFromToReadGoesFinished(): void
    {
        self::assertSame(ReadingStatus::Finished, ReadingStatus::ToRead->toggled());
    }

    public function testToggledFromReadingGoesFinished(): void
    {
        self::assertSame(ReadingStatus::Finished, ReadingStatus::Reading->toggled());
    }

    public function testToggledFromAbandonedGoesFinished(): void
    {
        self::assertSame(ReadingStatus::Finished, ReadingStatus::Abandoned->toggled());
    }
}
