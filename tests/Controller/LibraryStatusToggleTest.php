<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibraryStatusToggleTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

    private function persistUserWithBook(
        string $email,
        string $volumeId,
        ReadingStatus $reading = ReadingStatus::ToRead,
        PurchaseStatus $purchase = PurchaseStatus::ToBuy,
    ): array {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail($email)->setDisplayName('Owner')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId($volumeId)
            ->setTitle('Un livre')
            ->setReadingStatus($reading)
            ->setPurchaseStatus($purchase);
        $em->persist($book);
        $em->flush();

        return [$user, $book->getId()];
    }

    // The book_action_<id> token is rendered inside the detail-page reading-progress
    // form; scrape it there (same approach as BookTitleEditTest).
    private function actionToken(KernelBrowser $client, int $bookId): string
    {
        $crawler = $client->request('GET', '/livres/' . $bookId);
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[action*="/reading-progress"] input[name="_token"]')->attr('value');
    }

    private function freshReading(int $bookId): ReadingStatus
    {
        return static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Book::class)->find($bookId)->getReadingStatus();
    }

    private function freshPurchase(int $bookId): PurchaseStatus
    {
        return static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Book::class)->find($bookId)->getPurchaseStatus();
    }

    public function testToggleReadingFromToReadToFinished(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('rt1@example.test', 'vol-rt-1', ReadingStatus::ToRead);
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/reading-progress',
            ['toggle' => '1', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertStringContainsString('pill--toggle', $payload['html']);
        self::assertStringContainsString('Terminé', $payload['html']);
        self::assertSame(ReadingStatus::Finished, $this->freshReading($bookId));
    }

    public function testToggleReadingIsReversible(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('rt2@example.test', 'vol-rt-2', ReadingStatus::Finished);
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/reading-progress',
            ['toggle' => '1', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        self::assertSame(ReadingStatus::ToRead, $this->freshReading($bookId));
    }

    public function testToggleReadingFromReadingGoesFinished(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('rt3@example.test', 'vol-rt-3', ReadingStatus::Reading);
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/reading-progress',
            ['toggle' => '1', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertSame(ReadingStatus::Finished, $this->freshReading($bookId));
    }

    public function testTogglePurchaseFromToBuyToBought(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('pt1@example.test', 'vol-pt-1', ReadingStatus::ToRead, PurchaseStatus::ToBuy);
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/purchase',
            ['toggle' => '1', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertStringContainsString('Acheté', $payload['html']);
        self::assertSame(PurchaseStatus::Bought, $this->freshPurchase($bookId));
    }

    public function testTogglePurchaseFromLentGoesBought(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('pt2@example.test', 'vol-pt-2', ReadingStatus::ToRead, PurchaseStatus::Lent);
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/purchase',
            ['toggle' => '1', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertSame(PurchaseStatus::Bought, $this->freshPurchase($bookId));
    }

    public function testInvalidCsrfIsForbidden(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('ct1@example.test', 'vol-ct-1', ReadingStatus::ToRead);
        $client->loginUser($user);

        $client->request(
            'POST',
            '/books/' . $bookId . '/reading-progress',
            ['toggle' => '1', '_token' => 'wrong'],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(ReadingStatus::ToRead, $this->freshReading($bookId));
    }

    public function testCannotToggleAnotherUsersBook(): void
    {
        $client = static::createClient();
        [, $bookId] = $this->persistUserWithBook('owner@example.test', 'vol-own-1', ReadingStatus::ToRead);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $intruder = (new User())->setEmail('intruder@example.test')->setDisplayName('Intruder')->setPassword('Secret123');
        $em->persist($intruder);
        $em->flush();
        $client->loginUser($intruder);

        $client->request(
            'POST',
            '/books/' . $bookId . '/reading-progress',
            ['toggle' => '1', '_token' => 'whatever'],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(ReadingStatus::ToRead, $this->freshReading($bookId));
    }

    public function testNonXhrToggleRedirects(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('nx1@example.test', 'vol-nx-1', ReadingStatus::ToRead);
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/reading-progress',
            ['toggle' => '1', '_token' => $token]
        );

        self::assertResponseRedirects('/livres/' . $bookId);
        self::assertSame(ReadingStatus::Finished, $this->freshReading($bookId));
    }

    public function testTogglingRefreshesLibraryListing(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('cache@example.test', 'vol-cache-1', ReadingStatus::ToRead);
        $client->loginUser($user);

        // 1st GET caches the listing with the "À lire" toggle rendered.
        $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('actuellement : À lire', (string) $client->getResponse()->getContent());

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/reading-progress',
            ['toggle' => '1', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );
        self::assertResponseIsSuccessful();

        // 2nd GET must reflect the new status: the library cache tag was dropped.
        $client->request('GET', '/bibliotheque');
        self::assertStringContainsString('actuellement : Terminé', (string) $client->getResponse()->getContent());
    }

    public function testCardRendersToggleButtonsOutsideTheLink(): void
    {
        $client = static::createClient();
        [$user, ] = $this->persistUserWithBook('card1@example.test', 'vol-card-1', ReadingStatus::ToRead, PurchaseStatus::ToBuy);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();

        // Two toggle pills per card, wired to the controller.
        self::assertSame(2, $crawler->filter('button.pill--toggle[data-action~="status-toggle#toggle"]')->count());

        // They must NOT sit inside the navigational <a> (a button in <a> is invalid + would navigate).
        self::assertSame(0, $crawler->filter('a.book-card button.pill--toggle')->count());

        // The status row is a status-toggle controller carrying the CSRF token.
        $row = $crawler->filter('.book-card-status[data-controller="status-toggle"]');
        self::assertSame(1, $row->count());
        self::assertNotEmpty($row->attr('data-status-toggle-token-value'));

        // Each pill points at its own action route.
        $urls = $crawler->filter('button.pill--toggle')->each(fn ($n) => $n->attr('data-status-toggle-url-param'));
        self::assertTrue((bool) array_filter($urls, fn ($u) => str_contains((string) $u, '/reading-progress')));
        self::assertTrue((bool) array_filter($urls, fn ($u) => str_contains((string) $u, '/purchase')));
    }

    public function testCardStillRendersTitleEditPencil(): void
    {
        $client = static::createClient();
        [$user, ] = $this->persistUserWithBook('card2@example.test', 'vol-card-2');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();

        // Non-regression: the title-edit pencil is untouched.
        self::assertSame(1, $crawler->filter('button[data-action~="title-edit#open"]')->count());
    }
}
