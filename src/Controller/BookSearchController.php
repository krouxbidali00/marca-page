<?php

namespace App\Controller;

use App\Dto\GoogleBookResult;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Service\BookImporter;
use App\Service\GoogleBooksClientInterface;
use App\Service\GoogleBooksException;
use App\Service\LibraryCacheInvalidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BookSearchController extends AbstractController
{
    #[Route('/livres/recherche', name: 'app_book_search', methods: ['GET'])]
    public function search(
        Request $request,
        GoogleBooksClientInterface $googleBooks,
        BookRepository $bookRepository,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $results = [];
        $error = null;

        if ($query !== '') {
            try {
                $results = $googleBooks->search($query, 20);
            } catch (GoogleBooksException) {
                $error = 'La recherche Google Books est momentanément indisponible. Réessayez dans un instant.';
            }
        }

        /** @var User $user */
        $user = $this->getUser();
        $ownedVolumeIds = $bookRepository->findOwnedGoogleVolumeIds(
            $user,
            array_map(static fn (GoogleBookResult $result): string => $result->volumeId, $results),
        );

        $template = $request->query->getBoolean('fragment')
            ? 'book/_search_results.html.twig'
            : 'book/search.html.twig';

        return $this->render($template, [
            'query' => $query,
            'results' => $results,
            'error' => $error,
            'ownedVolumeIds' => $ownedVolumeIds,
        ]);
    }

    #[Route('/books/import', name: 'app_book_import', methods: ['POST'])]
    public function import(
        Request $request,
        BookImporter $importer,
        LibraryCacheInvalidator $cacheInvalidator,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        if (!$this->isCsrfTokenValid('import_book', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $isXhr = $request->isXmlHttpRequest();
        $volumeId = trim((string) $request->request->get('volumeId', ''));
        if ($volumeId === '') {
            if ($isXhr) {
                return new JsonResponse(['ok' => false, 'error' => 'Livre invalide.'], 400);
            }
            $this->addFlash('danger', 'Livre invalide.');

            return $this->redirectToRoute('app_book_search');
        }

        try {
            $book = $importer->importFromGoogle($user, $volumeId);
        } catch (GoogleBooksException) {
            if ($isXhr) {
                return new JsonResponse(
                    ['ok' => false, 'error' => 'Impossible de récupérer ce livre depuis Google Books.'],
                    502,
                );
            }
            $this->addFlash('danger', 'Impossible de récupérer ce livre depuis Google Books.');

            return $this->redirectToRoute('app_book_search', ['q' => (string) $request->request->get('q', '')]);
        }

        $cacheInvalidator->invalidateLibrary($user);

        if ($isXhr) {
            return new JsonResponse([
                'ok' => true,
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'deleteToken' => $csrfTokenManager->getToken('delete_book_' . $book->getId())->getValue(),
            ]);
        }

        $this->addFlash('success', \sprintf('« %s » a été ajouté à votre bibliothèque.', $book->getTitle()));

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }
}
