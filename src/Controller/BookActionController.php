<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use App\Repository\ShelfRepository;
use App\Security\BookVoter;
use App\Service\LibraryCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/books/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
class BookActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LibraryCacheInvalidator $cacheInvalidator,
    ) {
    }

    #[Route('/reading-progress', name: 'app_book_reading_progress')]
    public function readingProgress(Book $book, Request $request): Response
    {
        $this->guard($book, $request);

        if ($request->request->getBoolean('toggle')) {
            $book->setReadingStatus($book->getReadingStatus()->toggled());
        } else {
            $status = ReadingStatus::tryFrom((string) $request->request->get('readingStatus', ''));
            if ($status !== null) {
                $book->setReadingStatus($status);
            }
        }
        if ($request->request->has('currentPage')) {
            $page = $request->request->get('currentPage');
            $book->setCurrentPage($page === '' || $page === null ? null : max(0, (int) $page));
        }
        $this->em->flush();
        $this->cacheInvalidator->invalidateLibrary($book->getOwner());
        $this->cacheInvalidator->invalidateBook($book);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('_partials/reading_status_toggle.html.twig', ['book' => $book]),
            ]);
        }

        return $this->respond($book, 'Progression mise à jour.');
    }

    #[Route('/purchase', name: 'app_book_purchase')]
    public function purchase(Book $book, Request $request): Response
    {
        $this->guard($book, $request);

        if ($request->request->getBoolean('toggle')) {
            $book->setPurchaseStatus($book->getPurchaseStatus()->toggled());
        } else {
            $status = PurchaseStatus::tryFrom((string) $request->request->get('purchaseStatus', ''));
            if ($status !== null) {
                $book->setPurchaseStatus($status);
            }
        }
        if ($request->request->has('purchaseFormat')) {
            $format = trim((string) $request->request->get('purchaseFormat', ''));
            $book->setPurchaseFormat($format !== '' ? $format : null);
        }
        if ($request->request->has('purchasedAt')) {
            $date = trim((string) $request->request->get('purchasedAt', ''));
            $book->setPurchasedAt($date !== '' ? new \DateTimeImmutable($date) : null);
        }
        $this->em->flush();
        $this->cacheInvalidator->invalidateLibrary($book->getOwner());
        $this->cacheInvalidator->invalidateBook($book);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('_partials/purchase_status_toggle.html.twig', ['book' => $book]),
                'purchaseStatus' => $book->getPurchaseStatus()->value,
            ]);
        }

        return $this->respond($book, 'Statut d\'achat mis à jour.');
    }

    #[Route('/rating', name: 'app_book_rating')]
    public function rating(Book $book, Request $request): Response
    {
        $this->guard($book, $request);

        $value = $request->request->get('rating');
        $book->setRating($value === '' || $value === null ? null : (int) $value);
        $this->em->flush();
        $this->cacheInvalidator->invalidateLibrary($book->getOwner());
        $this->cacheInvalidator->invalidateBook($book);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['rating' => $book->getRating()]);
        }

        return $this->respond($book, 'Note enregistrée.');
    }

    #[Route('/notes', name: 'app_book_notes')]
    public function notes(Book $book, Request $request): Response
    {
        $this->guard($book, $request);

        $notes = trim((string) $request->request->get('personalNotes', ''));
        $book->setPersonalNotes($notes !== '' ? $notes : null);
        $this->em->flush();
        $this->cacheInvalidator->invalidateBook($book);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'savedAt' => (new \DateTimeImmutable())->format('c')]);
        }

        return $this->respond($book, 'Notes enregistrées.');
    }

    #[Route('/shelf', name: 'app_book_shelf')]
    public function shelf(Book $book, Request $request, ShelfRepository $shelves): Response
    {
        $this->guard($book, $request);
        /** @var User $user */
        $user = $this->getUser();

        $shelfId = $request->request->get('shelfId');
        if ($shelfId === '' || $shelfId === null || $shelfId === '0') {
            $book->setShelf(null);
        } else {
            $book->setShelf($shelves->findOneBy(['id' => (int) $shelfId, 'owner' => $user]));
        }
        $this->em->flush();
        $this->cacheInvalidator->invalidateLibrary($book->getOwner());
        $this->cacheInvalidator->invalidateBook($book);

        return $this->respond($book, 'Étagère mise à jour.');
    }

    #[Route('/title', name: 'app_book_title')]
    public function title(Book $book, Request $request): Response
    {
        $this->guard($book, $request);

        $title = trim((string) $request->request->get('title', ''));
        if ($title === '') {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Le titre ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->respondError($book, 'Le titre ne peut pas être vide.');
        }

        $book->setTitle(mb_substr($title, 0, 500));
        $this->em->flush();
        $this->cacheInvalidator->invalidateLibrary($book->getOwner());
        $this->cacheInvalidator->invalidateBook($book);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['title' => $book->getTitle()]);
        }

        return $this->respond($book, 'Titre mis à jour.');
    }

    private function guard(Book $book, Request $request): void
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);

        if (!$this->isCsrfTokenValid('book_action_' . $book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function respond(Book $book, string $message): Response
    {
        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    private function respondError(Book $book, string $message): Response
    {
        $this->addFlash('error', $message);

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }
}
