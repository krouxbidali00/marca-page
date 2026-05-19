<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibraryAccessTest extends WebTestCase
{
    public function testLibraryRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/bibliotheque');

        self::assertResponseRedirects('/connexion');
    }

    public function testBookSearchRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/livres/recherche');

        self::assertResponseRedirects('/connexion');
    }
}
