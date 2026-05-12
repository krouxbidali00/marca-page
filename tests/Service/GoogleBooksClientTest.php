<?php

namespace App\Tests\Service;

use App\Service\GoogleBooksClient;
use App\Service\GoogleBooksException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GoogleBooksClientTest extends TestCase
{
    public function testSearchMapsVolumes(): void
    {
        $json = json_encode(['items' => [[
            'id' => 'abc123',
            'volumeInfo' => [
                'title' => "L'Étranger",
                'authors' => ['Albert Camus'],
                'publisher' => 'Gallimard',
                'publishedDate' => '1942',
                'pageCount' => 186,
                'language' => 'fr',
                'description' => 'Meursault...',
                'categories' => ['Fiction'],
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '2070360024'],
                    ['type' => 'ISBN_13', 'identifier' => '9782070360024'],
                ],
                'imageLinks' => ['thumbnail' => 'http://books.google.com/img?id=abc123'],
            ],
        ]]], JSON_THROW_ON_ERROR);

        $client = new GoogleBooksClient(new MockHttpClient(new MockResponse($json)), '');

        $results = $client->search('camus');

        self::assertCount(1, $results);
        $r = $results[0];
        self::assertSame('abc123', $r->volumeId);
        self::assertSame("L'Étranger", $r->title);
        self::assertSame(['Albert Camus'], $r->authors);
        self::assertSame('2070360024', $r->isbn10);
        self::assertSame('9782070360024', $r->isbn13);
        self::assertSame('https://books.google.com/img?id=abc123', $r->thumbnailUrl);
        self::assertSame(186, $r->pageCount);
        self::assertSame('1942', $r->publishedYear());
    }

    public function testSearchReturnsEmptyArrayWhenNoItems(): void
    {
        $client = new GoogleBooksClient(new MockHttpClient(new MockResponse(json_encode(['totalItems' => 0], JSON_THROW_ON_ERROR))), '');

        self::assertSame([], $client->search('zzzz-nothing'));
    }

    public function testSearchReturnsEmptyArrayForBlankQuery(): void
    {
        $client = new GoogleBooksClient(new MockHttpClient([]), '');

        self::assertSame([], $client->search('   '));
    }

    public function testSearchThrowsOnHttpError(): void
    {
        $client = new GoogleBooksClient(new MockHttpClient(new MockResponse('boom', ['http_code' => 400])), '');

        $this->expectException(GoogleBooksException::class);
        $client->search('camus');
    }

    public function testSearchFallsBackToTitleQueryOnTemporaryHttpError(): void
    {
        $json = json_encode(['items' => [[
            'id' => 'franz-1',
            'volumeInfo' => [
                'title' => 'Le Procès',
                'authors' => ['Franz Kafka'],
            ],
        ]]], JSON_THROW_ON_ERROR);
        $queries = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$queries, $json): MockResponse {
            $queries[] = $options['query']['q'] ?? null;

            if (\count($queries) === 1) {
                return new MockResponse(json_encode(['error' => ['code' => 503]], JSON_THROW_ON_ERROR), ['http_code' => 503]);
            }

            return new MockResponse($json);
        });

        $client = new GoogleBooksClient($httpClient, '');

        $results = $client->search('Franz');

        self::assertSame(['Franz', 'intitle:Franz'], $queries);
        self::assertCount(1, $results);
        self::assertSame('Le Procès', $results[0]->title);
    }

    public function testGetVolumeMapsVolume(): void
    {
        $json = json_encode([
            'id' => 'vol-1',
            'volumeInfo' => [
                'title' => 'La Peste',
                'authors' => ['Albert Camus'],
                'publishedDate' => '1947-06-10',
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new GoogleBooksClient(new MockHttpClient(new MockResponse($json)), '');

        $r = $client->getVolume('vol-1');

        self::assertSame('La Peste', $r->title);
        self::assertNull($r->thumbnailUrl);
        self::assertSame('1947', $r->publishedYear());
    }
}
