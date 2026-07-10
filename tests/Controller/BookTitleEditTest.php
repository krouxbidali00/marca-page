<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookTitleEditTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

    private function persistUserWithBook(string $email, string $volumeId, string $title): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail($email)->setDisplayName('Owner')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId($volumeId)->setTitle($title);
        $em->persist($book);
        $em->flush();

        return [$user, $book->getId()];
    }

    // The book detail page renders the book_action_<id> token inside the
    // reading-progress form; scrape it there so this test is independent of
    // the front-end pencil button built in later tasks.
    private function actionToken(KernelBrowser $client, int $bookId): string
    {
        $crawler = $client->request('GET', '/livres/' . $bookId);
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[action*="/reading-progress"] input[name="_token"]')->attr('value');
    }

    private function freshTitle(int $bookId): string
    {
        return static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Book::class)->find($bookId)->getTitle();
    }

    public function testXhrRenameUpdatesTitle(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('title1@example.test', 'vol-title-1', 'Ancien titre');
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/title',
            ['title' => 'Nouveau titre', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Nouveau titre', $payload['title']);
        self::assertSame('Nouveau titre', $this->freshTitle($bookId));
    }

    public function testEmptyTitleIsRejected(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('title2@example.test', 'vol-title-2', 'Titre conservé');
        $client->loginUser($user);

        $token = $this->actionToken($client, $bookId);
        $client->request(
            'POST',
            '/books/' . $bookId . '/title',
            ['title' => '   ', '_token' => $token],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Titre conservé', $this->freshTitle($bookId));
    }

    public function testInvalidCsrfIsForbidden(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('title3@example.test', 'vol-title-3', 'Titre initial');
        $client->loginUser($user);

        $client->request(
            'POST',
            '/books/' . $bookId . '/title',
            ['title' => 'Peu importe', '_token' => 'wrong-token'],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame('Titre initial', $this->freshTitle($bookId));
    }

    public function testGridRendersEditTrigger(): void
    {
        $client = static::createClient();
        [$user, $bookId] = $this->persistUserWithBook('title4@example.test', 'vol-title-4', 'Titre carte');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();

        // Title node is marked for in-place updates.
        self::assertStringContainsString('Titre carte', $crawler->filter('[data-book-title]')->first()->text());

        // The card exposes a pencil trigger wired to the title-edit controller.
        $trigger = $crawler->filter('button[data-action~="title-edit#open"]');
        self::assertSame(1, $trigger->count());
        self::assertSame((string) $bookId, $trigger->attr('data-title-edit-id-param'));
        self::assertSame('Titre carte', $trigger->attr('data-title-edit-title-param'));
        self::assertNotEmpty($trigger->attr('data-title-edit-token-param'));
    }

    public function testLibraryPageRendersTitleModal(): void
    {
        $client = static::createClient();
        [$user, ] = $this->persistUserWithBook('title5@example.test', 'vol-title-5', 'Titre modale');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();

        // The stable library container also drives the title-edit controller.
        $container = $crawler->filter('[data-controller*="library"]')->first();
        self::assertStringContainsString('title-edit', $container->attr('data-controller'));

        // Exactly one shared modal, with the form the controller submits.
        self::assertSame(1, $crawler->filter('#bookTitleModal')->count());
        self::assertSame(1, $crawler->filter('#bookTitleModal form[data-title-edit-target="form"]')->count());
        self::assertSame(1, $crawler->filter('#bookTitleModal input[data-title-edit-target="input"]')->count());
    }
}
