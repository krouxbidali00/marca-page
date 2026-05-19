<?php

namespace App\Tests\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\ReadingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibraryFragmentTest extends WebTestCase
{
    public function testXhrRequestReturnsFragmentWithoutLayout(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('frag@example.test')->setDisplayName('Frag')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->xmlHttpRequest('GET', '/bibliotheque');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('<html', $body);
        self::assertStringNotContainsString('<nav', $body);
        self::assertStringContainsString('data-library-target="counter"', $body);
    }

    public function testFragmentRespectsReadingStatusFilter(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('frag2@example.test')->setDisplayName('Frag2')->setPassword('Secret123');
        $em->persist($user);

        $reading = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId('vol-reading')
            ->setTitle('Currently Reading Book')
            ->setReadingStatus(ReadingStatus::Reading);
        $finished = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId('vol-finished')
            ->setTitle('Finished Book')
            ->setReadingStatus(ReadingStatus::Finished);

        $em->persist($reading);
        $em->persist($finished);
        $em->flush();

        $client->loginUser($user);
        $client->xmlHttpRequest('GET', '/bibliotheque?reading%5B%5D=reading');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Currently Reading Book', $body);
        self::assertStringNotContainsString('Finished Book', $body);
    }

    public function testFragmentPaginationLinksPreserveFilters(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('frag3@example.test')->setDisplayName('Frag3')->setPassword('Secret123');
        $em->persist($user);

        // Library page size is 24 (BookRepository::PER_PAGE); create 30 to force pagination.
        for ($i = 0; $i < 30; $i++) {
            $em->persist(
                (new Book())
                    ->setOwner($user)
                    ->setGoogleVolumeId('vol-' . $i)
                    ->setTitle('Book ' . $i)
                    ->setReadingStatus(ReadingStatus::Reading)
            );
        }
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->xmlHttpRequest('GET', '/bibliotheque?reading%5B%5D=reading');

        self::assertResponseIsSuccessful();
        $paginationLinks = $crawler->filter('a.page-link')->extract(['href']);
        self::assertNotEmpty($paginationLinks, 'Expected pagination links in fragment');
        foreach ($paginationLinks as $href) {
            if (str_contains($href, 'page=')) {
                self::assertStringContainsString('reading', $href, "Pagination href must preserve reading filter: $href");
            }
        }
    }
}
