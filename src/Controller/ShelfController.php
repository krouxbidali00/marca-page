<?php

namespace App\Controller;

use App\Entity\Shelf;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ShelfRepository;
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
    #[Route('/shelves', name: 'app_shelf_index', methods: ['GET'])]
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
            $em->persist((new Shelf())->setOwner($user)->setName(mb_substr($name, 0, 80)));
            $em->flush();
            $this->addFlash('success', \sprintf('Étagère « %s » créée.', $name));
        }

        $back = (string) $request->request->get('_back', '');

        return $this->redirect($back !== '' && str_starts_with($back, '/') ? $back : $this->generateUrl('app_library'));
    }
}
