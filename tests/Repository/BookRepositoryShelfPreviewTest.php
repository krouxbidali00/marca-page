<?php

namespace App\Tests\Repository;

use App\Entity\Book;
use App\Entity\Shelf;
use App\Entity\User;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookRepositoryShelfPreviewTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BookRepository $books;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->books = self::getContainer()->get(BookRepository::class);
    }

    public function testFindShelfPreviewReturnsUpToLimitOrderedByThumbnailThenDate(): void
    {
        $user = (new User())->setEmail('preview@example.test')->setDisplayName('P')->setPassword('Secret123');
        $shelf = (new Shelf())->setOwner($user)->setName('Preview shelf');
        $this->em->persist($user);
        $this->em->persist($shelf);

        $noThumb = (new Book())->setOwner($user)->setGoogleVolumeId('p1')->setTitle('No thumb')->setShelf($shelf);
        $withThumbOld = (new Book())->setOwner($user)->setGoogleVolumeId('p2')->setTitle('With thumb old')
            ->setShelf($shelf)->setThumbnailUrl('http://example/old.jpg');
        $withThumbNew = (new Book())->setOwner($user)->setGoogleVolumeId('p3')->setTitle('With thumb new')
            ->setShelf($shelf)->setThumbnailUrl('http://example/new.jpg');

        $this->em->persist($noThumb);
        $this->em->persist($withThumbOld);
        $this->em->persist($withThumbNew);
        $this->em->flush();

        // Force a distinct addedAt order via DQL (Book has no setAddedAt setter).
        $update = 'UPDATE App\\Entity\\Book b SET b.addedAt = :d WHERE b.id = :id';
        $this->em->createQuery($update)->execute(['d' => new \DateTimeImmutable('-2 days'), 'id' => $withThumbOld->getId()]);
        $this->em->createQuery($update)->execute(['d' => new \DateTimeImmutable('-1 hour'), 'id' => $withThumbNew->getId()]);
        $this->em->createQuery($update)->execute(['d' => new \DateTimeImmutable('-1 day'), 'id' => $noThumb->getId()]);
        $this->em->clear();

        $shelfReloaded = $this->em->getRepository(\App\Entity\Shelf::class)->find($shelf->getId());
        $preview = $this->books->findShelfPreview($shelfReloaded, 4);

        self::assertCount(3, $preview);
        self::assertSame('With thumb new', $preview[0]->getTitle());
        self::assertSame('With thumb old', $preview[1]->getTitle());
        self::assertSame('No thumb', $preview[2]->getTitle());
    }

    public function testFindShelfPreviewRespectsLimit(): void
    {
        $user = (new User())->setEmail('preview2@example.test')->setDisplayName('P2')->setPassword('Secret123');
        $shelf = (new Shelf())->setOwner($user)->setName('Lots');
        $this->em->persist($user);
        $this->em->persist($shelf);
        for ($i = 0; $i < 6; $i++) {
            $this->em->persist(
                (new Book())->setOwner($user)->setGoogleVolumeId('pl-' . $i)->setTitle('Book ' . $i)->setShelf($shelf)
            );
        }
        $this->em->flush();

        $preview = $this->books->findShelfPreview($shelf, 4);

        self::assertCount(4, $preview);
    }

    public function testFindShelfPreviewIsDeterministicWhenAddedAtTies(): void
    {
        $user = (new User())->setEmail('preview-tie@example.test')->setDisplayName('PT')->setPassword('Secret123');
        $shelf = (new Shelf())->setOwner($user)->setName('Tied');
        $this->em->persist($user);
        $this->em->persist($shelf);

        // Three books persisted in a single request: same addedAt (constructor), no thumbnails.
        $b1 = (new Book())->setOwner($user)->setGoogleVolumeId('t1')->setTitle('Tie 1')->setShelf($shelf);
        $b2 = (new Book())->setOwner($user)->setGoogleVolumeId('t2')->setTitle('Tie 2')->setShelf($shelf);
        $b3 = (new Book())->setOwner($user)->setGoogleVolumeId('t3')->setTitle('Tie 3')->setShelf($shelf);
        $this->em->persist($b1);
        $this->em->persist($b2);
        $this->em->persist($b3);
        $this->em->flush();

        $preview = $this->books->findShelfPreview($shelf, 4);

        // Highest id (last persisted) comes first because of `addOrderBy('b.id', 'DESC')`.
        self::assertCount(3, $preview);
        self::assertSame('Tie 3', $preview[0]->getTitle());
        self::assertSame('Tie 2', $preview[1]->getTitle());
        self::assertSame('Tie 1', $preview[2]->getTitle());
    }
}
