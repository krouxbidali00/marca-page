<?php

namespace App\Tests\Controller;

use App\Entity\User;
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

        $client->xmlHttpRequest('GET', '/library');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('<html', $body);
        self::assertStringNotContainsString('<nav', $body);
        self::assertStringContainsString('data-library-target="counter"', $body);
    }
}
