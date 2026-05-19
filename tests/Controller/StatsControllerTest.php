<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatsControllerTest extends WebTestCase
{
    public function testStatsRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/statistiques');
        self::assertResponseRedirects('/connexion');
    }

    public function testStatsRendersForLoggedInUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('s@example.test')->setDisplayName('S')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/statistiques');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('en chiffres', (string) $client->getResponse()->getContent());
        self::assertGreaterThanOrEqual(5, $crawler->filter('canvas[data-stats-chart-target="canvas"]')->count());
    }

    public function testPeriodPillsReflectQueryParam(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('s2@example.test')->setDisplayName('S2')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/statistiques?period=year');
        self::assertResponseIsSuccessful();
        $active = $crawler->filter('.stats-period a.active')->text();
        self::assertSame('Cette année', trim($active));
    }
}
