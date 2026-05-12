<?php

namespace App\Tests\Service;

use App\Service\CoverThemePicker;
use PHPUnit\Framework\TestCase;

class CoverThemePickerTest extends TestCase
{
    public function testReturnsValueInRange(): void
    {
        $picker = new CoverThemePicker();

        foreach (['L\'Étranger', '', 'Le Petit Prince', 'a', str_repeat('x', 200)] as $seed) {
            $n = $picker->pick($seed);
            self::assertGreaterThanOrEqual(0, $n);
            self::assertLessThan(CoverThemePicker::COUNT, $n);
        }
    }

    public function testIsDeterministic(): void
    {
        $picker = new CoverThemePicker();

        self::assertSame($picker->pick('Germinal'), $picker->pick('Germinal'));
    }
}
