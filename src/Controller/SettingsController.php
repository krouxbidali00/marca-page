<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/index.html.twig');
    }

    #[Route('/settings/profile', name: 'app_settings_profile', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('settings_profile', $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Réessayez.');
            return $this->redirectToRoute('app_settings');
        }

        $displayName = trim((string) $request->request->get('displayName', ''));
        $email = trim((string) $request->request->get('email', ''));
        $currentPassword = (string) $request->request->get('currentPassword', '');

        if ($displayName === '' || $email === '') {
            $this->addFlash('danger', 'Le nom et l’email sont obligatoires.');
            return $this->redirectToRoute('app_settings');
        }

        $emailChanged = $email !== $user->getEmail();

        if ($emailChanged) {
            if (!$hasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Mot de passe actuel invalide. L’email n’a pas été modifié.');
                return $this->redirectToRoute('app_settings');
            }

            $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                $this->addFlash('danger', 'Cet email est déjà utilisé.');
                return $this->redirectToRoute('app_settings');
            }
            $user->setEmail($email);
        }

        $user->setDisplayName($displayName);
        $em->flush();

        $this->addFlash('success', 'Profil mis à jour.');
        return $this->redirectToRoute('app_settings');
    }

    #[Route('/settings/password', name: 'app_settings_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('settings_password', $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Réessayez.');
            return $this->redirectToRoute('app_settings');
        }

        $current = (string) $request->request->get('currentPassword', '');
        $new = (string) $request->request->get('newPassword', '');
        $confirm = (string) $request->request->get('confirmPassword', '');

        if (!$hasher->isPasswordValid($user, $current)) {
            $this->addFlash('danger', 'Mot de passe actuel invalide.');
            return $this->redirectToRoute('app_settings');
        }
        if (\strlen($new) < 8) {
            $this->addFlash('danger', 'Le nouveau mot de passe doit faire au moins 8 caractères.');
            return $this->redirectToRoute('app_settings');
        }
        if ($new !== $confirm) {
            $this->addFlash('danger', 'La confirmation ne correspond pas au nouveau mot de passe.');
            return $this->redirectToRoute('app_settings');
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();

        $this->addFlash('success', 'Mot de passe mis à jour.');
        return $this->redirectToRoute('app_settings');
    }
}
