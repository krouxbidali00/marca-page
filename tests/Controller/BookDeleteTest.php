<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookDeleteTest extends WebTestCase
{
    public function testXhrDeleteReturnsJsonAndRemovesBook(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('del@example.test')->setDisplayName('Del')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId('vol-del-1')->setTitle('To Delete');
        $em->persist($book);
        $em->flush();
        $bookId = $book->getId();
        $client->loginUser($user);

        // Establish a session (search page does not warm the library cache), then seed a valid CSRF token.
        $client->request('GET', '/livres/recherche');
        $session = $client->getRequest()->getSession();
        $token = bin2hex(random_bytes(16));
        $session->set('_csrf/delete_book_' . $bookId, $token);
        $session->save();

        $client->request(
            'POST',
            '/books/' . $bookId . '/delete',
            ['_token' => $token],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest'],
        );

        self::assertResponseIsSuccessful();
        self::assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($payload['ok']);
        self::assertCount(0, static::getContainer()->get(BookRepository::class)->findBy(['owner' => $user]));
    }
}
