<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testHomeIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'élégamment');
    }

    public function testLoginPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    public function testRegisterPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="registration_form[email]"]');
    }
}
