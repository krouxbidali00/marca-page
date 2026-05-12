<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use App\Repository\ShelfRepository;
use App\Security\BookVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BookController extends AbstractController
{
    #[Route('/books/{id}', name: 'app_book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Book $book, BookRepository $books, ShelfRepository $shelves): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'similar' => $books->findSimilar($book, 3),
            'shelves' => $shelves->findBy(['owner' => $user], ['name' => 'ASC']),
            'readingStatuses' => ReadingStatus::cases(),
            'purchaseStatuses' => PurchaseStatus::cases(),
        ]);
    }

    #[Route('/books/{id}/delete', name: 'app_book_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Book $book, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);

        if (!$this->isCsrfTokenValid('delete_book_' . $book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($book);
        $em->flush();
        $this->addFlash('success', 'Livre retiré de votre bibliothèque.');

        return $this->redirectToRoute('app_library');
    }
}
