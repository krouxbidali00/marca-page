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
}
