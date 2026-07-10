<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PurchaseBadgeTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

    public function testGiftedBadgeRendersGiftIcon(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('badge@example.test')->setDisplayName('Badge')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId('vol-badge-1')->setTitle('Cadeau')
            ->setPurchaseStatus(PurchaseStatus::Gifted);
        $em->persist($book);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/livres/' . $book->getId());
        self::assertResponseIsSuccessful();

        $badge = $crawler->filter('span.pill.pill-peche');
        self::assertGreaterThan(0, $badge->count(), 'Expected a pill-peche badge for a gifted book');
        self::assertStringContainsString('Offert', $badge->text());
        self::assertGreaterThan(0, $badge->filter('i.bi-gift')->count(), 'Expected a bi-gift icon inside the badge');
    }
}
