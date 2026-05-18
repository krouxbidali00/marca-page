<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SettingsControllerTest extends WebTestCase
{
    public function testSettingsRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/settings');
        self::assertResponseRedirects('/login');
    }

    public function testSettingsRendersForLoggedInUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('me@example.test')->setDisplayName('Me')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Paramètres', (string) $client->getResponse()->getContent());
    }
}
