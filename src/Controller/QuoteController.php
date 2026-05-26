<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Quote;
use App\Security\BookVoter;
use App\Service\LibraryCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
class QuoteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LibraryCacheInvalidator $cacheInvalidator,
    ) {
    }

    #[Route('/books/{id}/quotes', name: 'app_quote_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(Book $book, Request $request, ValidatorInterface $validator): Response
    {
        $this->guard($book, $request);

        $page = $request->request->get('page');
        $quote = (new Quote())
            ->setBook($book)
            ->setText(trim((string) $request->request->get('text', '')))
            ->setPage($page !== null && $page !== '' ? max(1, (int) $page) : null);

        $errors = $validator->validate($quote);
        if (\count($errors) > 0) {
            $this->addFlash('danger', (string) $errors->get(0)->getMessage());
        } else {
            $this->em->persist($quote);
            $this->em->flush();
            $this->cacheInvalidator->invalidateBook($book);
            $this->addFlash('success', 'Citation ajoutée.');
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/books/{id}/quotes/{quote}/delete', name: 'app_quote_delete', requirements: ['id' => '\d+', 'quote' => '\d+'], methods: ['POST'])]
    public function delete(Book $book, #[MapEntity(id: 'quote')] Quote $quote, Request $request): Response
    {
        $this->guard($book, $request);

        if ($quote->getBook() !== $book) {
            throw $this->createNotFoundException();
        }

        $this->em->remove($quote);
        $this->em->flush();
        $this->cacheInvalidator->invalidateBook($book);
        $this->addFlash('success', 'Citation supprimée.');

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    private function guard(Book $book, Request $request): void
    {
        $this->denyAccessUnlessGranted(BookVoter::OWN, $book);

        if (!$this->isCsrfTokenValid('book_action_' . $book->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
