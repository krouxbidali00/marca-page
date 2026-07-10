<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookCardRemoveTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

    public function testLibraryGridRendersRemoveFormAndDeletes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('grid@example.test')->setDisplayName('Grid')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId('vol-grid-1')->setTitle('Grid Book');
        $em->persist($book);
        $em->flush();
        $bookId = $book->getId();
        $client->loginUser($user);

        // The grid renders a per-card remove form targeting app_book_delete.
        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('form.book-card-remove')->count());
        $form = $crawler->filter('form.book-card-remove')->first();
        self::assertStringContainsString('/books/' . $bookId . '/delete', (string) $form->attr('action'));
        $token = $form->filter('input[name="_token"]')->attr('value');

        // Submitting it removes the book and redirects.
        $client->request('POST', '/books/' . $bookId . '/delete', ['_token' => $token]);
        self::assertResponseRedirects();
        self::assertCount(0, static::getContainer()->get(BookRepository::class)->findBy(['owner' => $user]));
    }

    public function testLibraryGridRemoveFormUsesConfirmModal(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('gridmodal@example.test')->setDisplayName('GridModal')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId('vol-gridmodal-1')->setTitle('Grid Modal Book');
        $em->persist($book);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form.book-card-remove')->first();
        self::assertSame('confirm', $form->attr('data-controller'));
        self::assertStringContainsString('submit->confirm#gate', (string) $form->attr('data-action'));
        self::assertStringNotContainsString('onsubmit', (string) $client->getResponse()->getContent());
    }
}
