<?php

namespace App\Tests\Repository;

use App\Entity\Book;
use App\Entity\Quote;
use App\Entity\User;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class QuoteRepositoryTest extends KernelTestCase
{
    public function testCountForUserCountsOnlyOwnQuotes(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repo = self::getContainer()->get(QuoteRepository::class);

        $alice = (new User())->setEmail('alice@example.test')->setDisplayName('Alice')->setPassword('Secret123');
        $bob = (new User())->setEmail('bob@example.test')->setDisplayName('Bob')->setPassword('Secret123');
        $em->persist($alice);
        $em->persist($bob);

        $aliceBook = (new Book())->setOwner($alice)->setGoogleVolumeId('v-a')->setTitle('Alice Book');
        $bobBook = (new Book())->setOwner($bob)->setGoogleVolumeId('v-b')->setTitle('Bob Book');
        $em->persist($aliceBook);
        $em->persist($bobBook);

        $em->persist((new Quote())->setBook($aliceBook)->setText('q1'));
        $em->persist((new Quote())->setBook($aliceBook)->setText('q2'));
        $em->persist((new Quote())->setBook($bobBook)->setText('q3'));
        $em->flush();

        self::assertSame(2, $repo->countForUser($alice));
        self::assertSame(1, $repo->countForUser($bob));
    }
}
