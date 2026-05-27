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

        // Search page renders results (the fake Google client returns one volume) and exposes a CSRF token.
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token]);
        self::assertResponseRedirects();

        $books = static::getContainer()->get(BookRepository::class)->findBy(['owner' => $user]);
        self::assertCount(1, $books);
        self::assertSame("L'Étranger", $books[0]->getTitle());

        // Importing the same volume again does not create a duplicate.
        // The search page now shows "Déjà ajouté" (no form) for owned books, so seed the CSRF token directly.
        $client->request('GET', '/livres/recherche?q=camus');
        $session = $client->getRequest()->getSession();
        $token2 = bin2hex(random_bytes(16));
        $session->set('_csrf/import_book', $token2);
        $session->save();
        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token2]);
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
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

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

        // Before import: the result offers the add form.
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('input[name="volumeId"]')->count());
        self::assertStringNotContainsString('Déjà ajouté', (string) $client->getResponse()->getContent());
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Import the volume returned by the fake Google client.
        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token]);

        // After import: "Déjà ajouté", and no add form for that result anymore.
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Déjà ajouté', (string) $client->getResponse()->getContent());
        self::assertSame(0, $crawler->filter('input[name="volumeId"]')->count());
    }
}
