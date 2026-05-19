<?php

namespace App\Tests\Repository;

use App\Entity\Book;
use App\Entity\Shelf;
use App\Entity\User;
use App\Repository\ShelfRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ShelfRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ShelfRepository $shelves;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->shelves = self::getContainer()->get(ShelfRepository::class);
    }

    public function testListOverviewsReturnsShelvesAlphabeticallyWithBookCount(): void
    {
        $user = (new User())->setEmail('r@example.test')->setDisplayName('R')->setPassword('Secret123');
        $this->em->persist($user);

        $zoo = (new Shelf())->setOwner($user)->setName('Zoologie');
        $adv = (new Shelf())->setOwner($user)->setName('Aventure');
        $man = (new Shelf())->setOwner($user)->setName('Manga');
        $this->em->persist($zoo);
        $this->em->persist($adv);
        $this->em->persist($man);

        $b1 = (new Book())->setOwner($user)->setGoogleVolumeId('v1')->setTitle('B1')->setShelf($adv);
        $b2 = (new Book())->setOwner($user)->setGoogleVolumeId('v2')->setTitle('B2')->setShelf($adv);
        $b3 = (new Book())->setOwner($user)->setGoogleVolumeId('v3')->setTitle('B3')->setShelf($man);
        $this->em->persist($b1);
        $this->em->persist($b2);
        $this->em->persist($b3);

        $this->em->flush();

        $overviews = $this->shelves->listOverviewsForUser($user);

        self::assertCount(3, $overviews);
        self::assertSame('Aventure', $overviews[0]['shelf']->getName());
        self::assertSame(2, $overviews[0]['bookCount']);
        self::assertSame('Manga', $overviews[1]['shelf']->getName());
        self::assertSame(1, $overviews[1]['bookCount']);
        self::assertSame('Zoologie', $overviews[2]['shelf']->getName());
        self::assertSame(0, $overviews[2]['bookCount']);
    }

    public function testListOverviewsIsScopedToUser(): void
    {
        $alice = (new User())->setEmail('alice-rep@example.test')->setDisplayName('Alice')->setPassword('Secret123');
        $bob = (new User())->setEmail('bob-rep@example.test')->setDisplayName('Bob')->setPassword('Secret123');
        $this->em->persist($alice);
        $this->em->persist($bob);
        $this->em->persist((new Shelf())->setOwner($alice)->setName('Alice shelf'));
        $this->em->persist((new Shelf())->setOwner($bob)->setName('Bob shelf'));
        $this->em->flush();

        $overviews = $this->shelves->listOverviewsForUser($alice);

        self::assertCount(1, $overviews);
        self::assertSame('Alice shelf', $overviews[0]['shelf']->getName());
    }
}
