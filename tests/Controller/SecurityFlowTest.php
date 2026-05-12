<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityFlowTest extends WebTestCase
{
    public function testLoginRedirectsToLibrary(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // The test env uses the "plaintext" password hasher, so the stored value is the raw password.
        $em->persist((new User())->setEmail('bob@example.test')->setDisplayName('Bob')->setPassword('Secret123'));
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Ouvrir ma bibliothèque')->form([
            '_username' => 'bob@example.test',
            '_password' => 'Secret123',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/library');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testWrongPasswordStaysOnLogin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist((new User())->setEmail('eve@example.test')->setDisplayName('Eve')->setPassword('Secret123'));
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Ouvrir ma bibliothèque')->form([
            '_username' => 'eve@example.test',
            '_password' => 'wrong',
        ]);
        $client->submit($form);
        $client->followRedirect();

        self::assertSelectorExists('.alert-danger');
    }
}
