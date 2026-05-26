<?php

namespace App\Tests\Security;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookVoterCacheHitTest extends WebTestCase
{
    public function testOwnerCheckSucceedsOnCacheHit(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('voter@test.fr')->setDisplayName('Voter')->setPassword('Secret123');
        $em->persist($user);

        $book = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId('vol-voter-1')
            ->setTitle('Voter Test')
            ->setReadingStatus(ReadingStatus::ToRead);
        $em->persist($book);
        $em->flush();

        $client->loginUser($user);

        // 1st GET: cache miss, book fetched freshly, voter passes
        $client->request('GET', '/livres/' . $book->getId());
        self::assertResponseIsSuccessful();

        // 2nd GET: cache HIT — book is deserialized; owner is a new object instance.
        // With === identity check this would 403. With ID equality it must still pass.
        $client->request('GET', '/livres/' . $book->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Voter Test');
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $container->get('cache.library')->clear();
        $container->get('cache.book_detail')->clear();
        parent::tearDown();
    }
}
