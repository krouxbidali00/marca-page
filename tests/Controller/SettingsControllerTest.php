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
        $client->request('GET', '/parametres');
        self::assertResponseRedirects('/connexion');
    }

    public function testSettingsRendersForLoggedInUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('me@example.test')->setDisplayName('Me')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/parametres');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Paramètres', (string) $client->getResponse()->getContent());
    }

    private function fetchCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        // Template doesn't expose a form for this token yet (added in a later task).
        // Seed the CSRF token directly into the session the test client is using.
        $client->request('GET', '/parametres');
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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

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
        self::assertResponseRedirects('/parametres');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'pw4@example.test']);
        self::assertSame('NewSecret456', $reloaded->getPassword());
    }

    public function testDeleteWrongPassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('del1@example.test')->setDisplayName('Del1')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_delete');
        $client->request('POST', '/settings/delete', [
            '_token' => $token,
            'currentPassword' => 'WrongPass',
        ]);
        self::assertResponseRedirects('/parametres');

        $em->clear();
        $stillHere = $em->getRepository(User::class)->findOneBy(['email' => 'del1@example.test']);
        self::assertNotNull($stillHere, 'User must not be deleted with wrong password');
    }

    public function testDeleteSuccessRemovesUserAndRedirects(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('del2@example.test')->setDisplayName('Del2')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $token = $this->fetchCsrfToken($client, 'settings_delete');
        $client->request('POST', '/settings/delete', [
            '_token' => $token,
            'currentPassword' => 'Secret123',
        ]);
        self::assertTrue($client->getResponse()->isRedirect());

        $em->clear();
        $gone = $em->getRepository(User::class)->findOneBy(['email' => 'del2@example.test']);
        self::assertNull($gone, 'User must be deleted from the database');
    }

    public function testExportReturnsJsonAttachment(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('exp1@example.test')->setDisplayName('Exp1')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/parametres/export');
        self::assertResponseIsSuccessful();
        self::assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('books', $payload);
        self::assertArrayHasKey('user', $payload);
        self::assertSame('exp1@example.test', $payload['user']['email']);
    }

    public function testEmailChangeRejectsExistingEmail(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $taken = (new User())->setEmail('taken@example.test')->setDisplayName('Taken')->setPassword('Secret123');
        $em->persist($taken);

        $me = (new User())->setEmail('me-uniq@example.test')->setDisplayName('Me')->setPassword('Secret123');
        $em->persist($me);
        $em->flush();
        $client->loginUser($me);

        $token = $this->fetchCsrfToken($client, 'settings_profile');
        $client->request('POST', '/settings/profile', [
            '_token' => $token,
            'displayName' => 'Me',
            'email' => 'taken@example.test',
            'currentPassword' => 'Secret123',
        ]);
        self::assertResponseRedirects('/parametres');

        $em->clear();
        $stillMe = $em->getRepository(User::class)->findOneBy(['email' => 'me-uniq@example.test']);
        self::assertNotNull($stillMe, 'Current user email must remain unchanged when target is already in use');
    }

    public function testAccountDeletionFormUsesConfirmModal(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('del@example.test')->setDisplayName('Del')->setPassword('Secret123');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/parametres');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form.settings-card--danger')->first();
        self::assertSame('confirm', $form->attr('data-controller'));
        self::assertStringContainsString('submit->confirm#gate', (string) $form->attr('data-action'));
        self::assertStringNotContainsString('settings-delete', (string) $client->getResponse()->getContent());
    }
}
