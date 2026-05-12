<?php

namespace App\Controller;

use App\Entity\Book;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/books/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
class BookActionController extends AbstractController
{
    #[Route('/reading-progress', name: 'app_book_reading_progress')]
    public function readingProgress(Book $book): Response
    {
        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/purchase', name: 'app_book_purchase')]
    public function purchase(Book $book): Response
    {
        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/rating', name: 'app_book_rating')]
    public function rating(Book $book): Response
    {
        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/notes', name: 'app_book_notes')]
    public function notes(Book $book): Response
    {
        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/shelf', name: 'app_book_shelf')]
    public function shelf(Book $book): Response
    {
        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }
}
