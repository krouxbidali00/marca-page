<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookDetailPurchaseButtonsTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

    private function loginWithBook(string $email, string $volumeId, ?PurchaseStatus $status): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail($email)->setDisplayName('Owner')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId($volumeId)->setTitle('Livre');
        if ($status !== null) {
            $book->setPurchaseStatus($status);
        }
        $em->persist($book);
        $em->flush();
        $client->loginUser($user);

        return [$client, $book->getId()];
    }

    public function testToBuyBookOffersGiftAndBorrowToggles(): void
    {
        [$client, $bookId] = $this->loginWithBook('detail-tobuy@example.test', 'vol-detail-tobuy', null);

        $crawler = $client->request('GET', '/livres/' . $bookId);
        self::assertResponseIsSuccessful();

        // The aside purchase buttons are plain POST forms with a hidden purchaseStatus.
        $values = $crawler->filter('form[action*="/purchase"] input[name="purchaseStatus"]')->extract(['value']);
        self::assertContains('gifted', $values);
        self::assertContains('borrowed', $values);
        self::assertContains('bought', $values);
    }

    public function testGiftedBookGiftButtonTogglesBackToToBuy(): void
    {
        [$client, $bookId] = $this->loginWithBook('detail-gifted@example.test', 'vol-detail-gifted', PurchaseStatus::Gifted);

        $crawler = $client->request('GET', '/livres/' . $bookId);
        self::assertResponseIsSuccessful();

        $values = $crawler->filter('form[action*="/purchase"] input[name="purchaseStatus"]')->extract(['value']);
        // The gift button now offers to revert to "to buy"; it no longer offers "gifted".
        self::assertContains('to_buy', $values);
        self::assertNotContains('gifted', $values);
    }
}
