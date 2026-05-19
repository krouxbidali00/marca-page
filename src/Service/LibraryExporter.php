<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\Quote;
use App\Entity\Shelf;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ShelfRepository;

final class LibraryExporter
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly ShelfRepository $shelves,
    ) {
    }

    /**
     * @return array{
     *   exportedAt: string,
     *   user: array{email: string, displayName: string, createdAt: string},
     *   books: list<array<string, mixed>>,
     *   shelves: list<array{id: int, name: string}>
     * }
     */
    public function exportForUser(User $user): array
    {
        $books = $this->books->createQueryBuilder('b')
            ->leftJoin('b.quotes', 'q')->addSelect('q')
            ->leftJoin('b.shelf', 's')->addSelect('s')
            ->andWhere('b.owner = :owner')->setParameter('owner', $user)
            ->orderBy('b.addedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $shelves = $this->shelves->findBy(['owner' => $user], ['name' => 'ASC']);

        return [
            'exportedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'user' => [
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            'books' => array_map([$this, 'serializeBook'], $books),
            'shelves' => array_map(static fn (Shelf $s): array => ['id' => (int) $s->getId(), 'name' => $s->getName()], $shelves),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeBook(Book $book): array
    {
        return [
            'googleVolumeId' => $book->getGoogleVolumeId(),
            'title' => $book->getTitle(),
            'subtitle' => $book->getSubtitle(),
            'authors' => $book->getAuthors(),
            'categories' => $book->getCategories(),
            'publisher' => $book->getPublisher(),
            'publishedDate' => $book->getPublishedDate(),
            'isbn10' => $book->getIsbn10(),
            'isbn13' => $book->getIsbn13(),
            'language' => $book->getLanguage(),
            'pageCount' => $book->getPageCount(),
            'description' => $book->getDescription(),
            'readingStatus' => $book->getReadingStatus()->value,
            'currentPage' => $book->getCurrentPage(),
            'purchaseStatus' => $book->getPurchaseStatus()->value,
            'purchasedAt' => $book->getPurchasedAt()?->format('Y-m-d'),
            'purchaseFormat' => $book->getPurchaseFormat(),
            'rating' => $book->getRating(),
            'personalNotes' => $book->getPersonalNotes(),
            'shelf' => $book->getShelf()?->getName(),
            'addedAt' => $book->getAddedAt()->format(\DateTimeInterface::ATOM),
            'quotes' => array_map(
                static fn (Quote $q): array => [
                    'text' => $q->getText(),
                    'page' => $q->getPage(),
                    'createdAt' => $q->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $book->getQuotes()->toArray(),
            ),
        ];
    }
}
