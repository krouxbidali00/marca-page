<?php

namespace App\Controller;

use App\Entity\Shelf;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ShelfRepository;
use App\Security\ShelfVoter;
use App\Service\CoverThemePicker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ShelfController extends AbstractController
{
    #[Route('/etageres', name: 'app_shelf_index', methods: ['GET'])]
    public function index(
        ShelfRepository $shelves,
        BookRepository $books,
        CoverThemePicker $themePicker,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $overviews = [];
        foreach ($shelves->listOverviewsForUser($user) as $row) {
            $shelf = $row['shelf'];
            $overviews[] = [
                'shelf' => $shelf,
                'bookCount' => $row['bookCount'],
                'preview' => $books->findShelfPreview($shelf, 4),
                'theme' => $themePicker->pick($shelf->getName()),
            ];
        }

        return $this->render('shelves/index.html.twig', [
            'overviews' => $overviews,
            'totalShelves' => count($overviews),
        ]);
    }

    #[Route('/shelves', name: 'app_shelf_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('create_shelf', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $name = trim((string) $request->request->get('name', ''));
        if ($name !== '') {
            $shelf = (new Shelf())->setOwner($user)->setName(mb_substr($name, 0, 80));
            $em->persist($shelf);
            $em->flush();
            $this->addFlash('success', \sprintf('Étagère « %s » créée.', $shelf->getName()));
        }

        $back = (string) $request->request->get('_back', '');

        return $this->redirect($back !== '' && str_starts_with($back, '/') ? $back : $this->generateUrl('app_library'));
    }

    #[Route('/shelves/{id}/rename', name: 'app_shelf_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rename(Shelf $shelf, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(ShelfVoter::OWN, $shelf);

        if (!$this->isCsrfTokenValid('rename_shelf_' . $shelf->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('error', 'Le nom de l\'étagère ne peut pas être vide.');

            return $this->redirectToRoute('app_shelf_index');
        }

        $shelf->setName(mb_substr($name, 0, 80));
        $em->flush();
        $this->addFlash('success', \sprintf('Étagère renommée en « %s ».', $shelf->getName()));

        return $this->redirectToRoute('app_shelf_index');
    }

    #[Route('/shelves/{id}/delete', name: 'app_shelf_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Shelf $shelf, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(ShelfVoter::OWN, $shelf);

        if (!$this->isCsrfTokenValid('delete_shelf_' . $shelf->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $shelf->getName();
        $em->remove($shelf);
        $em->flush();
        $this->addFlash('success', \sprintf('Étagère « %s » supprimée.', $name));

        return $this->redirectToRoute('app_shelf_index');
    }
}
