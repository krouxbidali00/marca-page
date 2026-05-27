<?php

namespace App\Tests\Repository;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookRepositoryOwnedBookIdsTest extends KernelTestCase
{
    public function testMapsOwnedVolumeIdsToBookIds(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repo = static::getContainer()->get(BookRepository::class);

        $user = (new User())->setEmail('owner@example.test')->setDisplayName('Owner')->setPassword('Secret123');
        $em->persist($user);
        $ids = [];
        foreach (['vol-a', 'vol-b'] as $vid) {
            $book = (new Book())->setOwner($user)->setGoogleVolumeId($vid)->setTitle('T-' . $vid);
            $em->persist($book);
            $em->flush();
            $ids[$vid] = $book->getId();
        }

        $map = $repo->findOwnedBookIdsByVolumeId($user, ['vol-a', 'vol-unknown', 'vol-b']);

        self::assertSame(['vol-a' => $ids['vol-a'], 'vol-b' => $ids['vol-b']], $map);
    }

    public function testEmptyInputReturnsEmptyArrayWithoutQuery(): void
    {
        $repo = static::getContainer()->get(BookRepository::class);
        $user = (new User())->setEmail('empty@example.test')->setDisplayName('Empty')->setPassword('Secret123');

        self::assertSame([], $repo->findOwnedBookIdsByVolumeId($user, []));
    }

    public function testOnlyReturnsCurrentUsersBooks(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repo = static::getContainer()->get(BookRepository::class);

        $owner = (new User())->setEmail('a@example.test')->setDisplayName('A')->setPassword('Secret123');
        $other = (new User())->setEmail('b@example.test')->setDisplayName('B')->setPassword('Secret123');
        $em->persist($owner);
        $em->persist($other);
        $em->persist((new Book())->setOwner($other)->setGoogleVolumeId('vol-x')->setTitle('Other book'));
        $em->flush();

        self::assertSame([], $repo->findOwnedBookIdsByVolumeId($owner, ['vol-x']));
    }
}
