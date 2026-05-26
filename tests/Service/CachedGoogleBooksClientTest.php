<?php

namespace App\Tests\Service;

use App\Dto\GoogleBookResult;
use App\Service\CachedGoogleBooksClient;
use App\Service\GoogleBooksClientInterface;
use App\Service\GoogleBooksException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CachedGoogleBooksClientTest extends TestCase
{
    private function makeResult(string $id, string $title): GoogleBookResult
    {
        return new GoogleBookResult(
            volumeId: $id,
            title: $title,
            subtitle: null,
            authors: [],
            publisher: null,
            publishedDate: null,
            pageCount: null,
            language: null,
            description: null,
            categories: [],
            isbn10: null,
            isbn13: null,
            thumbnailUrl: null,
        );
    }

    public function testSearchHitsCacheOnSecondCall(): void
    {
        $inner = $this->createMock(GoogleBooksClientInterface::class);
        $inner->expects(self::once())
            ->method('search')
            ->with('camus', 20)
            ->willReturn([$this->makeResult('abc', "L'Étranger")]);

        $client = new CachedGoogleBooksClient($inner, new ArrayAdapter());

        $first = $client->search('camus', 20);
        $second = $client->search('camus', 20);

        self::assertEquals($first, $second);
        self::assertCount(1, $first);
    }

    public function testSearchDifferentQueriesNotShared(): void
    {
        $inner = $this->createMock(GoogleBooksClientInterface::class);
        $inner->expects(self::exactly(2))
            ->method('search')
            ->willReturnCallback(fn (string $q): array => [$this->makeResult($q, $q)]);

        $client = new CachedGoogleBooksClient($inner, new ArrayAdapter());

        $client->search('camus');
        $client->search('kafka');
    }

    public function testSearchDifferentMaxResultsNotShared(): void
    {
        $inner = $this->createMock(GoogleBooksClientInterface::class);
        $inner->expects(self::exactly(2))
            ->method('search')
            ->willReturnCallback(fn (string $q, int $n): array => [$this->makeResult($q . '-' . $n, $q)]);

        $client = new CachedGoogleBooksClient($inner, new ArrayAdapter());

        $client->search('camus', 10);
        $client->search('camus', 40);
    }

    public function testSearchNormalizesQueryWhitespaceAndCase(): void
    {
        $inner = $this->createMock(GoogleBooksClientInterface::class);
        $inner->expects(self::once())
            ->method('search')
            ->willReturn([$this->makeResult('abc', "L'Étranger")]);

        $client = new CachedGoogleBooksClient($inner, new ArrayAdapter());

        $client->search('Camus');
        $client->search('  camus  ');
    }

    public function testSearchEmptyQueryBypassesCacheAndInner(): void
    {
        $inner = $this->createMock(GoogleBooksClientInterface::class);
        $inner->expects(self::never())->method('search');

        $client = new CachedGoogleBooksClient($inner, new ArrayAdapter());

        self::assertSame([], $client->search('   '));
    }

    public function testSearchExceptionIsNotCached(): void
    {
        $inner = $this->createMock(GoogleBooksClientInterface::class);
        $inner->expects(self::exactly(2))
            ->method('search')
            ->willThrowException(new GoogleBooksException('boom'));

        $client = new CachedGoogleBooksClient($inner, new ArrayAdapter());

        try {
            $client->search('camus');
            self::fail('expected exception');
        } catch (GoogleBooksException) {
        }

        $this->expectException(GoogleBooksException::class);
        $client->search('camus');
    }

    public function testGetVolumeHitsCacheOnSecondCall(): void
    {
        $inner = $this->createMock(GoogleBooksClientInterface::class);
        $inner->expects(self::once())
            ->method('getVolume')
            ->with('vol-1')
            ->willReturn($this->makeResult('vol-1', 'La Peste'));

        $client = new CachedGoogleBooksClient($inner, new ArrayAdapter());

        $client->getVolume('vol-1');
        $client->getVolume('vol-1');
    }
}
