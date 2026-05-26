<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class LibraryCacheInvalidator
{
    public function __construct(
        #[Autowire(service: 'cache.library')]
        private readonly TagAwareCacheInterface $libraryCache,
        #[Autowire(service: 'cache.book_detail')]
        private readonly TagAwareCacheInterface $bookDetailCache,
    ) {
    }

    public function invalidateLibrary(User $user): void
    {
        $this->libraryCache->invalidateTags(['user.' . (int) $user->getId() . '.library']);
    }

    public function invalidateBook(Book $book): void
    {
        $this->bookDetailCache->invalidateTags(['book.' . (int) $book->getId()]);
    }
}
