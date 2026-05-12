<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Quote;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class QuoteController extends AbstractController
{
    #[Route('/books/{id}/quotes', name: 'app_quote_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(Book $book): Response
    {
        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/books/{id}/quotes/{quote}/delete', name: 'app_quote_delete', requirements: ['id' => '\d+', 'quote' => '\d+'], methods: ['POST'])]
    public function delete(Book $book, #[MapEntity(id: 'quote')] Quote $quote): Response
    {
        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }
}
