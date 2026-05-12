<?php

namespace App\Dto;

final readonly class GoogleBookResult
{
    /**
     * @param string[] $authors
     * @param string[] $categories
     */
    public function __construct(
        public string $volumeId,
        public string $title,
        public ?string $subtitle,
        public array $authors,
        public ?string $publisher,
        public ?string $publishedDate,
        public ?int $pageCount,
        public ?string $language,
        public ?string $description,
        public array $categories,
        public ?string $isbn10,
        public ?string $isbn13,
        public ?string $thumbnailUrl,
    ) {
    }

    public function authorsLine(): string
    {
        return implode(', ', $this->authors) ?: 'Auteur inconnu';
    }

    public function publishedYear(): ?string
    {
        return $this->publishedDate ? substr($this->publishedDate, 0, 4) : null;
    }
}
