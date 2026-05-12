<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BookSearchController extends AbstractController
{
    #[Route('/books/search', name: 'app_book_search', methods: ['GET'])]
    public function search(): Response
    {
        return $this->render('book/search.html.twig');
    }

    #[Route('/books/import', name: 'app_book_import', methods: ['POST'])]
    public function import(): Response
    {
        return $this->redirectToRoute('app_book_search');
    }
}
