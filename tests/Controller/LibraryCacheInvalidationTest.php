<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class LibraryCacheInvalidationTest extends WebTestCase
{
    /**
     * Clear the tag-aware cache pools after each test to prevent stale filesystem entries
     * from leaking into subsequent tests (DAMA rolls back DB sequences, so user/book IDs
     * can be reused across tests, making cache key collisions possible).
     */
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

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

        // Follow the redirect to the book detail page so the flash message is consumed there,
        // not leaked into the subsequent library GET (which would make the assertion pass even
        // when the library cache is stale and the book card is absent).
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        // The library listing must now include the imported book — proves the cache was invalidated.
        // At this point the flash is gone, so 'Étranger' can only appear in the book card itself.
        $client->request('GET', '/bibliotheque');
        $libraryHtml = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Rien ici', $libraryHtml, 'Library must not show the empty-state message after import');
        self::assertStringContainsString('Étranger', $libraryHtml, 'Library listing must show the imported book title');
    }
}
