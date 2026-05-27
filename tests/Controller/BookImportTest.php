<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookImportTest extends WebTestCase
{
    public function testImportAddsBookToLibrary(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('reader@example.test')->setDisplayName('Reader')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // The search page exposes the import CSRF token on the toggle cell.
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('[data-library-toggle-import-token-value]')->first()->attr('data-library-toggle-import-token-value');

        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token]);
        self::assertResponseRedirects();

        $books = static::getContainer()->get(BookRepository::class)->findBy(['owner' => $user]);
        self::assertCount(1, $books);
        self::assertSame("L'Étranger", $books[0]->getTitle());

        // Importing the same volume again does not create a duplicate (token attr is still present when owned).
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        $token = $crawler->filter('[data-library-toggle-import-token-value]')->first()->attr('data-library-toggle-import-token-value');
        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token]);
        self::assertCount(1, static::getContainer()->get(BookRepository::class)->findBy(['owner' => $user]));

        // The book shows up on the library page.
        $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Étranger', (string) $client->getResponse()->getContent());
    }

    public function testXhrImportReturnsJsonAndDoesNotRedirect(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('xhr@example.test')->setDisplayName('Xhr')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        $token = $crawler->filter('[data-library-toggle-import-token-value]')->first()->attr('data-library-toggle-import-token-value');

        $client->request(
            'POST',
            '/books/import',
            ['volumeId' => 'test-vol-1', '_token' => $token],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest'],
        );

        self::assertResponseIsSuccessful();
        self::assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($payload['ok']);
        self::assertSame("L'Étranger", $payload['title']);
        self::assertIsInt($payload['id']);
        self::assertGreaterThan(0, $payload['id']);
        self::assertNotEmpty($payload['deleteToken']);

        $books = static::getContainer()->get(BookRepository::class)->findBy(['owner' => $user]);
        self::assertCount(1, $books);
    }

    public function testSearchMarksAlreadyOwnedResultAsAdded(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('marker@example.test')->setDisplayName('Marker')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // Before import: not owned — book-id is 0 and the remove button is hidden.
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-library-toggle-book-id-value="0"]')->count());
        self::assertStringContainsString('d-none', $crawler->filter('[data-library-toggle-target="remove"]')->first()->attr('class'));
        $token = $crawler->filter('[data-library-toggle-import-token-value]')->first()->attr('data-library-toggle-import-token-value');

        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token]);

        // After import: owned — book-id is the real id and the remove button is visible.
        $bookId = static::getContainer()->get(BookRepository::class)->findOneBy(['owner' => $user])->getId();
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-library-toggle-book-id-value="' . $bookId . '"]')->count());
        self::assertStringNotContainsString('d-none', $crawler->filter('[data-library-toggle-target="remove"]')->first()->attr('class'));
    }
}
