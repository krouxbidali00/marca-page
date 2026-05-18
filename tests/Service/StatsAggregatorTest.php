<?php

namespace App\Tests\Service;

use App\Entity\Book;
use App\Entity\Quote;
use App\Entity\User;
use App\Enum\ReadingStatus;
use App\Enum\StatsPeriod;
use App\Service\StatsAggregator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StatsAggregatorTest extends KernelTestCase
{
    public function testComputeAllPeriod(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $agg = self::getContainer()->get(StatsAggregator::class);

        $user = (new User())->setEmail('agg@example.test')->setDisplayName('Agg')->setPassword('Secret123');
        $em->persist($user);

        $b1 = (new Book())->setOwner($user)->setGoogleVolumeId('v1')->setTitle('Read')
            ->setReadingStatus(ReadingStatus::Finished)->setRating(5)->setPageCount(100)
            ->setAuthors(['Camus'])->setCategories(['Roman']);
        $b2 = (new Book())->setOwner($user)->setGoogleVolumeId('v2')->setTitle('Reading')
            ->setReadingStatus(ReadingStatus::Reading)->setRating(4)->setPageCount(200)
            ->setAuthors(['Camus'])->setCategories(['Essai']);
        $b3 = (new Book())->setOwner($user)->setGoogleVolumeId('v3')->setTitle('Unrated');
        $em->persist($b1);
        $em->persist($b2);
        $em->persist($b3);
        $em->persist((new Quote())->setBook($b1)->setText('hello'));
        $em->flush();

        $result = $agg->compute($user, StatsPeriod::All);

        self::assertSame(3, $result->totalBooks);
        self::assertSame(300, $result->totalPages);
        self::assertSame(1, $result->totalQuotes);
        self::assertSame(StatsPeriod::All, $result->period);
        self::assertSame(3, $result->periodTotal);
        self::assertSame(1, $result->countByStatus['finished']);
        self::assertSame(1, $result->countByStatus['reading']);
        self::assertSame(1, $result->countByStatus['to_read']);
        self::assertSame(2, $result->totalRated);
        self::assertSame(4.5, $result->averageRating);
        self::assertSame(1, $result->ratingHistogram[5]);
        self::assertSame(1, $result->ratingHistogram[4]);
        self::assertSame('Camus', $result->topAuthors[0]['name']);
        self::assertSame(2, $result->topAuthors[0]['count']);
        self::assertCount(2, $result->topCategories);
        self::assertCount(12, $result->activitySeries);
    }

    public function testComputeRespectsCurrentYearPeriod(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $agg = self::getContainer()->get(StatsAggregator::class);

        $user = (new User())->setEmail('agg2@example.test')->setDisplayName('Agg2')->setPassword('Secret123');
        $em->persist($user);

        $em->persist((new Book())->setOwner($user)->setGoogleVolumeId('vnew')->setTitle('New')->setRating(5));
        $em->flush();

        $old = (new Book())->setOwner($user)->setGoogleVolumeId('vold')->setTitle('Old')->setRating(1);
        $em->persist($old);
        $em->flush();
        $ref = new \ReflectionClass(Book::class);
        $prop = $ref->getProperty('addedAt');
        $prop->setAccessible(true);
        $prop->setValue($old, new \DateTimeImmutable('2020-01-01 12:00:00'));
        $em->flush();

        $result = $agg->compute($user, StatsPeriod::CurrentYear);
        self::assertSame(1, $result->periodTotal);
        self::assertSame(5.0, $result->averageRating);
    }
}
