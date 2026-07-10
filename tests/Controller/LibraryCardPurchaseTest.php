<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibraryCardPurchaseTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::getContainer()->get('cache.library')->clear();
        static::getContainer()->get('cache.book_detail')->clear();
        parent::tearDown();
    }

    private function loginWithBook(string $email, string $volumeId, string $title): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail($email)->setDisplayName('Owner')->setPassword('Secret123');
        $em->persist($user);
        $book = (new Book())->setOwner($user)->setGoogleVolumeId($volumeId)->setTitle($title);
        $em->persist($book);
        $em->flush();
        $client->loginUser($user);

        return [$client, $book->getId()];
    }

    public function testCardRendersGiftAndBorrowForms(): void
    {
        [$client, $bookId] = $this->loginWithBook('card-render@example.test', 'vol-card-render', 'Grid Book');

        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();

        $forms = $crawler->filter('form.book-card-action');
        self::assertSame(2, $forms->count(), 'Expected a gift and a borrow quick-action form on the card');

        foreach ($forms as $form) {
            self::assertStringContainsString('/books/' . $bookId . '/purchase', (string) $form->getAttribute('action'));
        }

        $values = $crawler->filter('form.book-card-action input[name="purchaseStatus"]')->extract(['value']);
        self::assertContains('gifted', $values);
        self::assertContains('borrowed', $values);
    }

    public function testCardFormMarksGiftedWithoutJs(): void
    {
        [$client, $bookId] = $this->loginWithBook('card-post@example.test', 'vol-card-post', 'Grid Book');

        $crawler = $client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form.book-card-action input[name="_token"]')->first()->attr('value');

        // No X-Requested-With header: the plain-form fallback should redirect.
        $client->request('POST', '/books/' . $bookId . '/purchase', ['purchaseStatus' => 'gifted', '_token' => $token]);
        self::assertResponseRedirects();

        $fresh = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Book::class)->find($bookId);
        self::assertSame(PurchaseStatus::Gifted, $fresh->getPurchaseStatus());
    }
}
