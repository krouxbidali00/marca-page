<?php

namespace App\Tests\Enum;

use App\Enum\StatsPeriod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class StatsPeriodTest extends TestCase
{
    public function testFromRequestDefaultsToAll(): void
    {
        $r = new Request();
        self::assertSame(StatsPeriod::All, StatsPeriod::fromRequest($r));
    }

    public function testFromRequestParsesKnownValue(): void
    {
        $r = new Request(['period' => '30d']);
        self::assertSame(StatsPeriod::Last30Days, StatsPeriod::fromRequest($r));
    }

    public function testFromRequestFallsBackOnUnknown(): void
    {
        $r = new Request(['period' => 'banana']);
        self::assertSame(StatsPeriod::All, StatsPeriod::fromRequest($r));
    }

    public function testSinceForAllIsNull(): void
    {
        self::assertNull(StatsPeriod::All->since());
    }

    public function testSinceForLast30DaysIs30DaysAgo(): void
    {
        $since = StatsPeriod::Last30Days->since();
        self::assertNotNull($since);
        $delta = (new \DateTimeImmutable())->getTimestamp() - $since->getTimestamp();
        self::assertEqualsWithDelta(30 * 86400, $delta, 2);
    }

    public function testSinceForCurrentYearIsFirstOfJanuary(): void
    {
        $since = StatsPeriod::CurrentYear->since();
        self::assertNotNull($since);
        self::assertSame('01-01 00:00:00', $since->format('m-d H:i:s'));
        self::assertSame((int) (new \DateTimeImmutable())->format('Y'), (int) $since->format('Y'));
    }

    public function testLabelsAreFrench(): void
    {
        self::assertSame('Tout', StatsPeriod::All->label());
        self::assertSame('30 derniers jours', StatsPeriod::Last30Days->label());
        self::assertSame('Cette année', StatsPeriod::CurrentYear->label());
    }
}
