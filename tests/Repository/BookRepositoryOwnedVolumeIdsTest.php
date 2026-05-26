<?php

namespace App\Tests\Repository;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookRepositoryOwnedVolumeIdsTest extends KernelTestCase
{
    public function testReturnsOnlyOwnedVolumeIds(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repo = static::getContainer()->get(BookRepository::class);

        $user = (new User())->setEmail('owner@example.test')->setDisplayName('Owner')->setPassword('Secret123');
        $em->persist($user);
        foreach (['vol-a', 'vol-b'] as $vid) {
            $book = (new Book())->setOwner($user)->setGoogleVolumeId($vid)->setTitle('T-' . $vid);
            $em->persist($book);
        }
        $em->flush();

        $owned = $repo->findOwnedGoogleVolumeIds($user, ['vol-a', 'vol-unknown', 'vol-b']);
        sort($owned);
        self::assertSame(['vol-a', 'vol-b'], $owned);
    }

    public function testEmptyInputReturnsEmptyArrayWithoutQuery(): void
    {
        $repo = static::getContainer()->get(BookRepository::class);
        $user = (new User())->setEmail('empty@example.test')->setDisplayName('Empty')->setPassword('Secret123');

        self::assertSame([], $repo->findOwnedGoogleVolumeIds($user, []));
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

        self::assertSame([], $repo->findOwnedGoogleVolumeIds($owner, ['vol-x']));
    }
}
