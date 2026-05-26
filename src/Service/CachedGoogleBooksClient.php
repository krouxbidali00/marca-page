<?php

namespace App\Service;

use App\Dto\GoogleBookResult;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsDecorator(decorates: GoogleBooksClient::class)]
final class CachedGoogleBooksClient implements GoogleBooksClientInterface
{
    public function __construct(
        private readonly GoogleBooksClientInterface $inner,
        #[Autowire(service: 'cache.google_books')]
        private readonly CacheInterface $cache,
    ) {
    }

    public function search(string $query, int $maxResults = 20): array
    {
        $normalized = strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $key = 'v1.search.' . hash('xxh128', $normalized . '|' . $maxResults);

        return $this->cache->get($key, function (ItemInterface $item) use ($query, $maxResults): array {
            $item->expiresAfter(86400);

            return $this->inner->search($query, $maxResults);
        });
    }

    public function getVolume(string $volumeId): GoogleBookResult
    {
        $key = 'v1.volume.' . hash('xxh128', $volumeId);

        return $this->cache->get($key, function (ItemInterface $item) use ($volumeId): GoogleBookResult {
            $item->expiresAfter(86400);

            return $this->inner->getVolume($volumeId);
        });
    }
}
