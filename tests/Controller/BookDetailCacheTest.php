<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\ReadingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookDetailCacheTest extends WebTestCase
{
    public function testEditingNotesUpdatesDetailPage(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())->setEmail('detail@test.fr')->setDisplayName('Detail')->setPassword('Secret123');
        $em->persist($user);

        $book = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId('vol-detail-1')
            ->setTitle('Detail Test')
            ->setReadingStatus(ReadingStatus::ToRead);
        $em->persist($book);
        $em->flush();

        $client->loginUser($user);

        // 1st GET: detail page rendered and cached; no personal notes yet
        $crawler = $client->request('GET', '/livres/' . $book->getId());
        self::assertResponseIsSuccessful();
        $html1 = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Detail Test', $html1);
        self::assertStringNotContainsString('My fresh new notes', $html1);

        // Pull a CSRF token from the detail page (all forms share the same book_action_<id> token)
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Update notes via the action endpoint
        $client->request('POST', '/books/' . $book->getId() . '/notes', [
            'personalNotes' => 'My fresh new notes',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();

        // 2nd GET: new notes must appear — proves the book detail cache was invalidated
        $client->request('GET', '/livres/' . $book->getId());
        $html2 = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('My fresh new notes', $html2, 'Detail page must render the updated notes after cache invalidation');
        self::assertNotSame($html1, $html2, 'Detail page HTML must differ after a notes mutation — cache was not invalidated');
    }
}
