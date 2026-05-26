<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibraryCacheInvalidationTest extends WebTestCase
{
    public function testRatingABookRefreshesLibraryListing(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('cache@test.fr')->setDisplayName('Cache')->setPassword('Secret123');
        $em->persist($user);

        $book = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId('vol-cache-1')
            ->setTitle('Test Cacheable')
            ->setReadingStatus(ReadingStatus::ToRead);
        $em->persist($book);
        $em->flush();

        $client->loginUser($user);

        // 1st GET: library is computed and cached; book has no rating yet
        $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
        $html1 = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Test Cacheable', $html1);
        // Unrated books do not show the star-fill icon in the card
        self::assertStringNotContainsString('bi-star-fill', $html1);

        // Pull the CSRF token from the book detail page
        $crawler = $client->request('GET', '/livres/' . $book->getId());
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Rate the book 5 stars
        $client->request('POST', '/books/' . $book->getId() . '/rating', [
            'rating' => '5',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();

        // Confirm the rating was persisted before checking the view
        $em->clear();
        $persisted = static::getContainer()->get(BookRepository::class)->find($book->getId());
        self::assertSame(5, $persisted->getRating(), 'Rating must be persisted in the database');

        // 2nd GET: the cache must have been invalidated; the new rating must appear
        $client->request('GET', '/bibliotheque');
        $html2 = (string) $client->getResponse()->getContent();

        // After rating, the card renders a bi-star-fill icon — absent before rating
        self::assertStringContainsString('bi-star-fill', $html2, 'Library listing must show the star icon after rating');
        self::assertNotSame($html1, $html2, 'Library HTML must differ after a rating mutation — cache was not invalidated');
    }

    public function testImportingABookAppearsImmediatelyInLibrary(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('cache2@test.fr')->setDisplayName('Cache2')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        // Warm the library cache while empty
        $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Étranger', (string) $client->getResponse()->getContent());

        // Import a book via the search controller (FakeGoogleBooksClient returns L'Étranger)
        $crawler = $client->request('GET', '/livres/recherche?q=camus');
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/books/import', ['volumeId' => 'test-vol-1', '_token' => $token]);
        self::assertResponseRedirects();

        // The library listing must now include the imported book — proves the cache was invalidated
        $client->request('GET', '/bibliotheque');
        self::assertStringContainsString('Étranger', (string) $client->getResponse()->getContent());
    }
}
