<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    public function testRegisterCreatesUserAndLogsIn(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/inscription');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon coffret')->form([
            'registration_form[displayName]' => 'Claire',
            'registration_form[email]' => 'claire@example.test',
            'registration_form[plainPassword][first]' => 'Secret123',
            'registration_form[plainPassword][second]' => 'Secret123',
            'registration_form[agreeTerms]' => '1',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/bibliotheque');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'claire@example.test']);
        self::assertNotNull($user);
        self::assertSame('Claire', $user->getDisplayName());
    }

    public function testRegisterRejectsWeakPassword(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/inscription');

        $form = $crawler->selectButton('Créer mon coffret')->form([
            'registration_form[displayName]' => 'Bob',
            'registration_form[email]' => 'bob-weak@example.test',
            'registration_form[plainPassword][first]' => 'abc',
            'registration_form[plainPassword][second]' => 'abc',
            'registration_form[agreeTerms]' => '1',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'bob-weak@example.test']));
    }
}
