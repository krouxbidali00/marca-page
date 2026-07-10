<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookPurchaseStatusTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

    private function persistUser(string $email): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail($email)->setDisplayName('Owner')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistBook(User $user, string $volumeId, string $title, ?PurchaseStatus $status = null): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId($volumeId)->setTitle($title);
        if ($status !== null) {
            $book->setPurchaseStatus($status);
        }
        $em->persist($book);
        $em->flush();

        return $book->getId();
    }

    private function actionToken(KernelBrowser $client, int $bookId): string
    {
        $crawler = $client->request('GET', '/livres/' . $bookId);
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[action*="/reading-progress"] input[name="_token"]')->attr('value');
    }

    private function freshStatus(int $bookId): PurchaseStatus
    {
        return static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Book::class)->find($bookId)->getPurchaseStatus();
    }

    public function testXhrMarkGiftedReturnsJsonAndPersists(): void
    {
        $client = static::createClient();
        $user = $this->persistUser('purchase-xhr@example.test');
        $bookId = $this->persistBook($user, 'vol-purchase-xhr', 'Un livre');
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/purchase',
            ['purchaseStatus' => 'gifted', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('gifted', $payload['purchaseStatus']);
        self::assertSame(PurchaseStatus::Gifted, $this->freshStatus($bookId));
    }

    public function testGiftedBookIsExcludedFromToBuyFilter(): void
    {
        $client = static::createClient();
        $user = $this->persistUser('purchase-filter@example.test');
        $this->persistBook($user, 'vol-tobuy', 'A Acheter Book');               // defaults to ToBuy
        $this->persistBook($user, 'vol-gifted', 'Offert Book', PurchaseStatus::Gifted);
        $client->loginUser($user);

        $client->xmlHttpRequest('GET', '/bibliotheque?purchase%5B%5D=to_buy');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('A Acheter Book', $body);
        self::assertStringNotContainsString('Offert Book', $body);
    }
}
