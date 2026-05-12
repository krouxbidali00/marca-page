<?php

namespace App\Tests\Double;

use App\Dto\GoogleBookResult;
use App\Service\GoogleBooksClientInterface;

final class FakeGoogleBooksClient implements GoogleBooksClientInterface
{
    public function search(string $query, int $maxResults = 20): array
    {
        return trim($query) === '' ? [] : [$this->volume('test-vol-1')];
    }

    public function getVolume(string $volumeId): GoogleBookResult
    {
        return $this->volume($volumeId);
    }

    private function volume(string $id): GoogleBookResult
    {
        return new GoogleBookResult(
            volumeId: $id,
            title: "L'Étranger",
            subtitle: null,
            authors: ['Albert Camus'],
            publisher: 'Gallimard',
            publishedDate: '1942',
            pageCount: 186,
            language: 'fr',
            description: 'Meursault, modeste employé de bureau à Alger…',
            categories: ['Fiction'],
            isbn10: '2070360024',
            isbn13: '9782070360024',
            thumbnailUrl: null,
        );
    }
}
