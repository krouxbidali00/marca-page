<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ShelfControllerTest extends WebTestCase
{
    public function testIndexRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/shelves');
        self::assertResponseRedirects('/login');
    }

    public function testIndexEmptyShowsEmptyState(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('shelves-empty@example.test')->setDisplayName('SE')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/shelves');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucune étagère', (string) $client->getResponse()->getContent());
    }

    public function testIndexListsShelvesAlphabeticallyWithCounts(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('shelves-list@example.test')->setDisplayName('SL')->setPassword('Secret123');
        $em->persist($user);

        foreach (['Zoologie', 'Aventure', 'Manga'] as $name) {
            $em->persist((new \App\Entity\Shelf())->setOwner($user)->setName($name));
        }
        $em->flush();

        $adv = $em->getRepository(\App\Entity\Shelf::class)->findOneBy(['name' => 'Aventure', 'owner' => $user]);
        $em->persist((new \App\Entity\Book())->setOwner($user)->setGoogleVolumeId('cl1')->setTitle('B1')->setShelf($adv));
        $em->persist((new \App\Entity\Book())->setOwner($user)->setGoogleVolumeId('cl2')->setTitle('B2')->setShelf($adv));
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/shelves');
        self::assertResponseIsSuccessful();

        $names = $crawler->filter('.shelf-card__name')->each(fn ($n) => trim($n->text()));
        self::assertSame(['Aventure', 'Manga', 'Zoologie'], $names);

        $counts = $crawler->filter('.shelf-card__count')->each(fn ($n) => trim($n->text()));
        self::assertSame(['2 livres', '0 livre', '0 livre'], $counts);
    }

    public function testCardLinksToLibraryFilteredByShelf(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('shelves-link@example.test')->setDisplayName('SLI')->setPassword('Secret123');
        $em->persist($user);
        $shelf = (new \App\Entity\Shelf())->setOwner($user)->setName('Link target');
        $em->persist($shelf);
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/shelves');
        $href = $crawler->filter('.shelf-card__link')->attr('href');
        self::assertSame('/library?shelf=' . $shelf->getId(), $href);
    }

    public function testIndexRendersAllThreeModals(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('shelves-modals@example.test')->setDisplayName('SM')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/shelves');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#shelfCreateModal'));
        self::assertCount(1, $crawler->filter('#shelfRenameModal'));
        self::assertCount(1, $crawler->filter('#shelfDeleteModal'));
    }

    public function testRenamePersistsNewName(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('rename@example.test')->setDisplayName('R')->setPassword('Secret123');
        $em->persist($user);
        $shelf = (new \App\Entity\Shelf())->setOwner($user)->setName('Old name');
        $em->persist($shelf);
        $em->flush();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/shelves');

        $csrf = $crawler->filter('[data-action="shelves#openRename"]')->attr('data-shelves-token-param');
        $client->request('POST', '/shelves/' . $shelf->getId() . '/rename', ['_token' => $csrf, 'name' => 'New name']);

        self::assertResponseRedirects('/shelves');
        $em->clear();
        $reloaded = $em->getRepository(\App\Entity\Shelf::class)->find($shelf->getId());
        self::assertSame('New name', $reloaded->getName());
    }

    public function testRenameForbiddenForOtherUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $alice = (new User())->setEmail('alice-r@example.test')->setDisplayName('A')->setPassword('Secret123');
        $bob = (new User())->setEmail('bob-r@example.test')->setDisplayName('B')->setPassword('Secret123');
        $em->persist($alice);
        $em->persist($bob);
        $shelf = (new \App\Entity\Shelf())->setOwner($alice)->setName('Alice shelf');
        $em->persist($shelf);
        $em->flush();
        $client->loginUser($bob);

        // Voter is checked before CSRF, so an invalid token is fine here — the access denial is what we verify.
        $client->request('POST', '/shelves/' . $shelf->getId() . '/rename', ['_token' => 'anything', 'name' => 'Hacked']);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testRenameForbiddenWithBadCsrf(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('renamecsrf@example.test')->setDisplayName('RC')->setPassword('Secret123');
        $em->persist($user);
        $shelf = (new \App\Entity\Shelf())->setOwner($user)->setName('Shelf');
        $em->persist($shelf);
        $em->flush();
        $client->loginUser($user);

        $client->request('POST', '/shelves/' . $shelf->getId() . '/rename', ['_token' => 'wrong', 'name' => 'X']);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testRenameEmptyNameDoesNotPersist(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('renameempty@example.test')->setDisplayName('RE')->setPassword('Secret123');
        $em->persist($user);
        $shelf = (new \App\Entity\Shelf())->setOwner($user)->setName('Keep me');
        $em->persist($shelf);
        $em->flush();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/shelves');

        $csrf = $crawler->filter('[data-action="shelves#openRename"]')->attr('data-shelves-token-param');
        $client->request('POST', '/shelves/' . $shelf->getId() . '/rename', ['_token' => $csrf, 'name' => '   ']);

        self::assertResponseRedirects('/shelves');
        $em->clear();
        $reloaded = $em->getRepository(\App\Entity\Shelf::class)->find($shelf->getId());
        self::assertSame('Keep me', $reloaded->getName());
    }

    public function testDeleteRemovesShelfAndNullifiesBookShelf(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('delete@example.test')->setDisplayName('D')->setPassword('Secret123');
        $em->persist($user);
        $shelf = (new \App\Entity\Shelf())->setOwner($user)->setName('Doomed');
        $em->persist($shelf);
        $book = (new \App\Entity\Book())->setOwner($user)->setGoogleVolumeId('dv1')->setTitle('Survivor')->setShelf($shelf);
        $em->persist($book);
        $em->flush();
        $bookId = $book->getId();
        $shelfId = $shelf->getId();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/shelves');

        $token = $crawler->filter('[data-action="shelves#openDelete"]')->attr('data-shelves-token-param');
        $client->request('POST', '/shelves/' . $shelfId . '/delete', ['_token' => $token]);

        self::assertResponseRedirects('/shelves');
        $em->clear();
        self::assertNull($em->getRepository(\App\Entity\Shelf::class)->find($shelfId));
        $reloadedBook = $em->getRepository(\App\Entity\Book::class)->find($bookId);
        self::assertNotNull($reloadedBook);
        self::assertNull($reloadedBook->getShelf());
    }

    public function testDeleteForbiddenForOtherUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $alice = (new User())->setEmail('alice-d@example.test')->setDisplayName('A')->setPassword('Secret123');
        $bob = (new User())->setEmail('bob-d@example.test')->setDisplayName('B')->setPassword('Secret123');
        $em->persist($alice);
        $em->persist($bob);
        $shelf = (new \App\Entity\Shelf())->setOwner($alice)->setName('Alice shelf');
        $em->persist($shelf);
        $em->flush();
        $client->loginUser($bob);

        // Voter is checked before CSRF, so an invalid token is fine here — the access denial is what we verify.
        $client->request('POST', '/shelves/' . $shelf->getId() . '/delete', ['_token' => 'anything']);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testDeleteForbiddenWithBadCsrf(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('deletecsrf@example.test')->setDisplayName('DC')->setPassword('Secret123');
        $em->persist($user);
        $shelf = (new \App\Entity\Shelf())->setOwner($user)->setName('Shelf');
        $em->persist($shelf);
        $em->flush();
        $client->loginUser($user);

        $client->request('POST', '/shelves/' . $shelf->getId() . '/delete', ['_token' => 'wrong']);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testNavbarLinkIsActiveOnShelvesPage(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = (new User())->setEmail('nav-shelves@example.test')->setDisplayName('NS')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/shelves');
        $active = $crawler->filter('.navbar-nav .nav-link.active')->text();
        self::assertSame('Étagères', trim($active));
    }
}
