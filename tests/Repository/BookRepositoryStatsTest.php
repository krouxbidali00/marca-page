<?php

namespace App\Tests\Repository;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookRepositoryStatsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BookRepository $books;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->books = self::getContainer()->get(BookRepository::class);

        $this->user = (new User())->setEmail('stats@example.test')->setDisplayName('Stats')->setPassword('Secret123');
        $this->em->persist($this->user);
    }

    private function book(string $title, ReadingStatus $status = ReadingStatus::ToRead, ?int $rating = null, ?int $pages = null, ?string $addedAt = null): Book
    {
        $b = (new Book())
            ->setOwner($this->user)
            ->setGoogleVolumeId('vol-' . $title)
            ->setTitle($title)
            ->setReadingStatus($status);
        if ($rating !== null) {
            $b->setRating($rating);
        }
        if ($pages !== null) {
            $b->setPageCount($pages);
        }
        $this->em->persist($b);
        $this->em->flush();
        if ($addedAt !== null) {
            $ref = new \ReflectionClass(Book::class);
            $prop = $ref->getProperty('addedAt');
            $prop->setAccessible(true);
            $prop->setValue($b, new \DateTimeImmutable($addedAt));
            $this->em->flush();
        }
        return $b;
    }

    public function testSumPagesIgnoresNullPageCount(): void
    {
        $this->book('A', pages: 200);
        $this->book('B', pages: 300);
        $this->book('C', pages: null);
        self::assertSame(500, $this->books->sumPages($this->user));
    }

    public function testCountByStatusReturnsAllKeysZeroFilled(): void
    {
        $this->book('A', ReadingStatus::Reading);
        $this->book('B', ReadingStatus::Reading);
        $this->book('C', ReadingStatus::Finished);

        $counts = $this->books->countByStatus($this->user, null);
        self::assertSame(2, $counts['reading']);
        self::assertSame(1, $counts['finished']);
        self::assertSame(0, $counts['to_read']);
        self::assertSame(0, $counts['abandoned']);
    }

    public function testCountByStatusFiltersBySince(): void
    {
        $this->book('Old', ReadingStatus::Finished, addedAt: '2020-01-01 12:00:00');
        $this->book('New', ReadingStatus::Finished, addedAt: 'now');

        $counts = $this->books->countByStatus($this->user, new \DateTimeImmutable('-1 day'));
        self::assertSame(1, $counts['finished']);
    }

    public function testCountAddedSinceCountsOnlyRecent(): void
    {
        $this->book('Old', addedAt: '2020-01-01 12:00:00');
        $this->book('Recent', addedAt: 'now');
        self::assertSame(1, $this->books->countAddedSince($this->user, new \DateTimeImmutable('-1 day')));
        self::assertSame(2, $this->books->countAddedSince($this->user, null));
    }

    public function testCountRatedIgnoresUnrated(): void
    {
        $this->book('A', rating: 4);
        $this->book('B', rating: 5);
        $this->book('C');
        self::assertSame(2, $this->books->countRated($this->user, null));
    }

    public function testAverageRatingReturnsNullWhenNothingRated(): void
    {
        $this->book('A');
        self::assertNull($this->books->averageRating($this->user, null));
    }

    public function testAverageRating(): void
    {
        $this->book('A', rating: 2);
        $this->book('B', rating: 4);
        self::assertSame(3.0, $this->books->averageRating($this->user, null));
    }

    public function testRatingHistogramReturnsAllBucketsZeroFilled(): void
    {
        $this->book('A', rating: 3);
        $this->book('B', rating: 3);
        $this->book('C', rating: 5);
        $this->book('Un-rated');

        $hist = $this->books->ratingHistogram($this->user, null);
        self::assertSame([1 => 0, 2 => 0, 3 => 2, 4 => 0, 5 => 1], $hist);
    }
}
