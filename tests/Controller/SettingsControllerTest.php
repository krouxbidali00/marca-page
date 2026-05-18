<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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

    private function fetchCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        // Template doesn't expose a form for this token yet (added in a later task).
        // Seed the CSRF token directly into the session the test client is using.
        $client->request('GET', '/settings');
        $session = $client->getRequest()->getSession();
        $token = bin2hex(random_bytes(16));
        $session->set('_csrf/' . $tokenId, $token);
        $session->save();
        return $token;
    }

    public function testUpdateDisplayNameOnly(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('p1@example.test')->setDisplayName('Old')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_profile');

        $client->request('POST', '/settings/profile', [
            '_token' => $token,
            'displayName' => 'New Name',
            'email' => 'p1@example.test',
            'currentPassword' => '',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'p1@example.test']);
        self::assertSame('New Name', $reloaded->getDisplayName());
    }

    public function testEmailChangeRequiresCurrentPassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('p2@example.test')->setDisplayName('Two')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_profile');

        $client->request('POST', '/settings/profile', [
            '_token' => $token,
            'displayName' => 'Two',
            'email' => 'p2-new@example.test',
            'currentPassword' => '',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $stillOld = $em->getRepository(User::class)->findOneBy(['email' => 'p2@example.test']);
        self::assertNotNull($stillOld, 'Email must not change without current password');
    }

    public function testEmailChangeWithWrongPassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('p3@example.test')->setDisplayName('Three')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_profile');

        $client->request('POST', '/settings/profile', [
            '_token' => $token,
            'displayName' => 'Three',
            'email' => 'p3-new@example.test',
            'currentPassword' => 'WrongPass',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $stillOld = $em->getRepository(User::class)->findOneBy(['email' => 'p3@example.test']);
        self::assertNotNull($stillOld);
    }

    public function testEmailChangeWithRightPassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('p4@example.test')->setDisplayName('Four')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_profile');

        $client->request('POST', '/settings/profile', [
            '_token' => $token,
            'displayName' => 'Four',
            'email' => 'p4-new@example.test',
            'currentPassword' => 'Secret123',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $updated = $em->getRepository(User::class)->findOneBy(['email' => 'p4-new@example.test']);
        self::assertNotNull($updated, 'Email must change when password is correct');
    }

    public function testProfileWithInvalidCsrf(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('p5@example.test')->setDisplayName('Five')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('POST', '/settings/profile', [
            '_token' => 'wrong-token',
            'displayName' => 'Hacked',
            'email' => 'p5@example.test',
            'currentPassword' => '',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'p5@example.test']);
        self::assertSame('Five', $reloaded->getDisplayName());
    }

    public function testPasswordWrongCurrent(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('pw1@example.test')->setDisplayName('PW1')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_password');
        $client->request('POST', '/settings/password', [
            '_token' => $token,
            'currentPassword' => 'WrongPass',
            'newPassword' => 'NewSecret456',
            'confirmPassword' => 'NewSecret456',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'pw1@example.test']);
        self::assertSame('Secret123', $reloaded->getPassword());
    }

    public function testPasswordMismatchConfirmation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('pw2@example.test')->setDisplayName('PW2')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_password');
        $client->request('POST', '/settings/password', [
            '_token' => $token,
            'currentPassword' => 'Secret123',
            'newPassword' => 'NewSecret456',
            'confirmPassword' => 'Different789',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'pw2@example.test']);
        self::assertSame('Secret123', $reloaded->getPassword());
    }

    public function testPasswordTooShort(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('pw3@example.test')->setDisplayName('PW3')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_password');
        $client->request('POST', '/settings/password', [
            '_token' => $token,
            'currentPassword' => 'Secret123',
            'newPassword' => 'short',
            'confirmPassword' => 'short',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'pw3@example.test']);
        self::assertSame('Secret123', $reloaded->getPassword());
    }

    public function testPasswordChangeSuccess(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('pw4@example.test')->setDisplayName('PW4')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_password');
        $client->request('POST', '/settings/password', [
            '_token' => $token,
            'currentPassword' => 'Secret123',
            'newPassword' => 'NewSecret456',
            'confirmPassword' => 'NewSecret456',
        ]);
        self::assertResponseRedirects('/settings');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'pw4@example.test']);
        self::assertSame('NewSecret456', $reloaded->getPassword());
    }
}
