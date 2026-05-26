<?php

namespace App\Tests\Dto;

use App\Dto\LibraryFilter;
use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class LibraryFilterTest extends TestCase
{
    public function testDefaults(): void
    {
        $f = LibraryFilter::fromRequest(new Request());

        self::assertSame('', $f->query);
        self::assertSame([], $f->readingStatuses);
        self::assertSame([], $f->purchaseStatuses);
        self::assertSame([], $f->categories);
        self::assertNull($f->minRating);
        self::assertNull($f->maxPages);
        self::assertNull($f->shelfId);
        self::assertSame('recent', $f->sort);
        self::assertSame(1, $f->page);
        self::assertTrue($f->isEmpty());
    }

    public function testParsesValues(): void
    {
        $f = LibraryFilter::fromRequest(new Request(query: [
            'q' => '  camus ',
            'reading' => ['reading', 'to_read', 'bogus'],
            'purchase' => ['bought'],
            'category' => ['Roman', ' '],
            'minRating' => '3',
            'maxPages' => '500',
            'shelf' => '7',
            'sort' => 'title_asc',
            'page' => '2',
        ]));

        self::assertSame('camus', $f->query);
        self::assertSame([ReadingStatus::Reading, ReadingStatus::ToRead], $f->readingStatuses);
        self::assertSame([PurchaseStatus::Bought], $f->purchaseStatuses);
        self::assertSame(['Roman'], $f->categories);
        self::assertSame(3, $f->minRating);
        self::assertSame(500, $f->maxPages);
        self::assertSame(7, $f->shelfId);
        self::assertSame('title_asc', $f->sort);
        self::assertSame(2, $f->page);
        self::assertFalse($f->isEmpty());
    }

    public function testInvalidSortAndPageFallBack(): void
    {
        $f = LibraryFilter::fromRequest(new Request(query: ['sort' => 'nonsense', 'page' => '0']));

        self::assertSame('recent', $f->sort);
        self::assertSame(1, $f->page);
    }

    public function testMaxPagesCeilingTreatedAsNoFilter(): void
    {
        $f = LibraryFilter::fromRequest(new Request(query: ['maxPages' => (string) LibraryFilter::MAX_PAGES_CEILING]));

        self::assertNull($f->maxPages);
    }

    public function testToQueryParamsRoundTrip(): void
    {
        $f = LibraryFilter::fromRequest(new Request(query: [
            'q' => 'hugo',
            'reading' => ['finished'],
            'sort' => 'rating',
        ]));

        self::assertSame([
            'q' => 'hugo',
            'reading' => ['finished'],
            'sort' => 'rating',
        ], $f->toQueryParams());
    }

    public function testCacheSignatureIsDeterministic(): void
    {
        $a = new LibraryFilter(query: 'camus', readingStatuses: [ReadingStatus::Reading], page: 2, sort: 'title_asc');
        $b = new LibraryFilter(query: 'camus', readingStatuses: [ReadingStatus::Reading], page: 2, sort: 'title_asc');

        self::assertSame($a->cacheSignature(), $b->cacheSignature());
    }

    public function testCacheSignatureChangesWithQuery(): void
    {
        $a = new LibraryFilter(query: 'camus');
        $b = new LibraryFilter(query: 'kafka');

        self::assertNotSame($a->cacheSignature(), $b->cacheSignature());
    }

    public function testCacheSignatureChangesWithPage(): void
    {
        $a = new LibraryFilter(page: 1);
        $b = new LibraryFilter(page: 2);

        self::assertNotSame($a->cacheSignature(), $b->cacheSignature());
    }

    public function testCacheSignatureChangesWithSort(): void
    {
        $a = new LibraryFilter(sort: 'recent');
        $b = new LibraryFilter(sort: 'title_asc');

        self::assertNotSame($a->cacheSignature(), $b->cacheSignature());
    }

    public function testCacheSignatureIsIndependentOfArrayOrder(): void
    {
        $a = new LibraryFilter(readingStatuses: [ReadingStatus::Reading, ReadingStatus::Finished]);
        $b = new LibraryFilter(readingStatuses: [ReadingStatus::Finished, ReadingStatus::Reading]);

        self::assertSame($a->cacheSignature(), $b->cacheSignature());
    }

    public function testCacheSignatureIncludesPurchaseStatusesCategoriesShelfRatingPages(): void
    {
        $base = new LibraryFilter();
        $withPurchase = new LibraryFilter(purchaseStatuses: [PurchaseStatus::Bought]);
        $withCategory = new LibraryFilter(categories: ['Fiction']);
        $withShelf = new LibraryFilter(shelfId: 7);
        $withRating = new LibraryFilter(minRating: 4);
        $withPages = new LibraryFilter(maxPages: 300);

        $signatures = array_map(
            static fn (LibraryFilter $f): string => $f->cacheSignature(),
            [$base, $withPurchase, $withCategory, $withShelf, $withRating, $withPages],
        );

        self::assertCount(6, array_unique($signatures), 'each filter axis must alter the signature');
    }

    public function testCacheSignatureEscapesPipeInQuery(): void
    {
        $crafted = new LibraryFilter(query: 'hello|sort=evil');
        $natural = new LibraryFilter(query: 'hello', sort: 'evil');

        self::assertNotSame(
            $crafted->cacheSignature(),
            $natural->cacheSignature(),
            'a pipe in the query must not let one filter masquerade as another',
        );
    }
}
