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
}
