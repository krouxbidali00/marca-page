<?php

namespace App\Service;

use App\Dto\GoogleBookResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleBooksClient implements GoogleBooksClientInterface
{
    private const BASE = 'https://www.googleapis.com/books/v1/volumes';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GOOGLE_BOOKS_API_KEY)%')]
        private readonly string $apiKey = '',
    ) {
    }

    public function search(string $query, int $maxResults = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $params = [
            'q' => $query,
            'maxResults' => max(1, min(40, $maxResults)),
            'printType' => 'books',
            'orderBy' => 'relevance',
        ];

        try {
            $data = $this->request(self::BASE, $params);
        } catch (GoogleBooksException $e) {
            if (!$this->isTemporaryFailure($e)) {
                throw $e;
            }

            // Google Books can return backendFailed for broad terms while title-qualified searches work.
            $data = $this->request(self::BASE, array_replace($params, ['q' => 'intitle:' . $query]));
        }

        $items = \is_array($data['items'] ?? null) ? $data['items'] : [];

        return array_values(array_filter(array_map(
            fn (array $item): ?GoogleBookResult => $this->mapItem($item),
            $items,
        )));
    }

    public function getVolume(string $volumeId): GoogleBookResult
    {
        $data = $this->request(self::BASE . '/' . rawurlencode($volumeId), []);
        $result = $this->mapItem($data);
        if ($result === null) {
            throw new GoogleBooksException(\sprintf('Volume "%s" is missing required data.', $volumeId));
        }

        return $result;
    }

    /**
     * @param array<string, scalar> $params
     *
     * @return array<string, mixed>
     */
    private function request(string $url, array $params): array
    {
        if ($this->apiKey !== '') {
            $params['key'] = $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('GET', $url, ['query' => $params, 'timeout' => 8]);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw new GoogleBooksException(\sprintf('Google Books API returned HTTP %d.', $status), $status);
            }

            return $response->toArray();
        } catch (GoogleBooksException $e) {
            throw $e;
        } catch (HttpExceptionInterface | \JsonException $e) {
            throw new GoogleBooksException('Could not reach the Google Books API.', 0, $e);
        }
    }

    private function isTemporaryFailure(GoogleBooksException $exception): bool
    {
        $code = $exception->getCode();

        return $code >= 500;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function mapItem(array $item): ?GoogleBookResult
    {
        $id = $item['id'] ?? null;
        $info = $item['volumeInfo'] ?? null;
        if (!\is_string($id) || !\is_array($info) || empty($info['title'])) {
            return null;
        }

        $isbn10 = null;
        $isbn13 = null;
        foreach ($info['industryIdentifiers'] ?? [] as $ident) {
            if (!\is_array($ident)) {
                continue;
            }
            if (($ident['type'] ?? '') === 'ISBN_10') {
                $isbn10 = $ident['identifier'] ?? null;
            }
            if (($ident['type'] ?? '') === 'ISBN_13') {
                $isbn13 = $ident['identifier'] ?? null;
            }
        }

        $thumb = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;
        if (\is_string($thumb)) {
            $thumb = str_replace('http://', 'https://', $thumb);
        }

        return new GoogleBookResult(
            volumeId: $id,
            title: (string) $info['title'],
            subtitle: isset($info['subtitle']) ? (string) $info['subtitle'] : null,
            authors: array_values(array_map('strval', \is_array($info['authors'] ?? null) ? $info['authors'] : [])),
            publisher: isset($info['publisher']) ? (string) $info['publisher'] : null,
            publishedDate: isset($info['publishedDate']) ? (string) $info['publishedDate'] : null,
            pageCount: isset($info['pageCount']) ? (int) $info['pageCount'] : null,
            language: isset($info['language']) ? (string) $info['language'] : null,
            description: isset($info['description']) ? (string) $info['description'] : null,
            categories: array_values(array_map('strval', \is_array($info['categories'] ?? null) ? $info['categories'] : [])),
            isbn10: \is_string($isbn10) ? $isbn10 : null,
            isbn13: \is_string($isbn13) ? $isbn13 : null,
            thumbnailUrl: \is_string($thumb) ? $thumb : null,
        );
    }
}
