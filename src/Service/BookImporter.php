<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BookImporter
{
    public function __construct(
        private readonly GoogleBooksClientInterface $googleBooks,
        private readonly CoverThemePicker $coverThemePicker,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Imports a Google Books volume into the user's library. If the user already
     * owns this volume, returns the existing Book without creating a duplicate.
     *
     * @throws GoogleBooksException
     */
    public function importFromGoogle(User $user, string $volumeId): Book
    {
        $existing = $this->em->getRepository(Book::class)
            ->findOneBy(['owner' => $user, 'googleVolumeId' => $volumeId]);
        if ($existing instanceof Book) {
            return $existing;
        }

        $data = $this->googleBooks->getVolume($volumeId);

        $book = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId($data->volumeId)
            ->setTitle($data->title)
            ->setSubtitle($data->subtitle)
            ->setAuthors($data->authors)
            ->setPublisher($data->publisher)
            ->setPublishedDate($data->publishedDate)
            ->setPageCount($data->pageCount)
            ->setLanguage($data->language)
            ->setDescription($data->description)
            ->setCategories($data->categories)
            ->setIsbn10($data->isbn10)
            ->setIsbn13($data->isbn13)
            ->setThumbnailUrl($data->thumbnailUrl)
            ->setCoverTheme($this->coverThemePicker->pick($data->title));

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }
}
