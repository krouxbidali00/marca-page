<?php

namespace App\Service;

use App\Dto\GoogleBookResult;

interface GoogleBooksClientInterface
{
    /**
     * @return GoogleBookResult[]
     *
     * @throws GoogleBooksException
     */
    public function search(string $query, int $maxResults = 20): array;

    /**
     * @throws GoogleBooksException
     */
    public function getVolume(string $volumeId): GoogleBookResult;
}
