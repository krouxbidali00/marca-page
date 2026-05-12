<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibraryAccessTest extends WebTestCase
{
    public function testLibraryRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/library');

        self::assertResponseRedirects('/login');
    }

    public function testBookSearchRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/books/search');

        self::assertResponseRedirects('/login');
    }
}
