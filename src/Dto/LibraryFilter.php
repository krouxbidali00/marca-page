<?php

namespace App\Dto;

use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use Symfony\Component\HttpFoundation\Request;

final readonly class LibraryFilter
{
    public const SORTS = ['recent', 'title_asc', 'author', 'rating', 'published'];
    public const MAX_PAGES_CEILING = 800;

    /**
     * @param ReadingStatus[]  $readingStatuses
     * @param PurchaseStatus[] $purchaseStatuses
     * @param string[]         $categories
     */
    public function __construct(
        public string $query = '',
        public array $readingStatuses = [],
        public array $purchaseStatuses = [],
        public array $categories = [],
        public ?int $minRating = null,
        public ?int $maxPages = null,
        public ?int $shelfId = null,
        public string $sort = 'recent',
        public int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $q = $request->query;

        $reading = [];
        foreach ($q->all('reading') as $v) {
            $case = ReadingStatus::tryFrom((string) $v);
            if ($case !== null) {
                $reading[] = $case;
            }
        }

        $purchase = [];
        foreach ($q->all('purchase') as $v) {
            $case = PurchaseStatus::tryFrom((string) $v);
            if ($case !== null) {
                $purchase[] = $case;
            }
        }

        $categories = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $q->all('category'),
        )));

        $minRating = $q->has('minRating') && $q->getInt('minRating') > 0
            ? max(1, min(5, $q->getInt('minRating')))
            : null;

        $maxPages = $q->has('maxPages') && $q->getInt('maxPages') > 0 && $q->getInt('maxPages') < self::MAX_PAGES_CEILING
            ? $q->getInt('maxPages')
            : null;

        $shelfId = $q->has('shelf') && $q->getInt('shelf') > 0 ? $q->getInt('shelf') : null;

        $sort = (string) $q->get('sort', 'recent');
        if (!\in_array($sort, self::SORTS, true)) {
            $sort = 'recent';
        }

        $page = max(1, $q->getInt('page', 1));

        return new self(
            query: trim((string) $q->get('q', '')),
            readingStatuses: $reading,
            purchaseStatuses: $purchase,
            categories: $categories,
            minRating: $minRating,
            maxPages: $maxPages,
            shelfId: $shelfId,
            sort: $sort,
            page: $page,
        );
    }

    public function isEmpty(): bool
    {
        return $this->query === ''
            && $this->readingStatuses === []
            && $this->purchaseStatuses === []
            && $this->categories === []
            && $this->minRating === null
            && $this->maxPages === null
            && $this->shelfId === null;
    }

    public function hasReadingStatus(ReadingStatus $status): bool
    {
        return \in_array($status, $this->readingStatuses, true);
    }

    public function hasPurchaseStatus(PurchaseStatus $status): bool
    {
        return \in_array($status, $this->purchaseStatuses, true);
    }

    public function hasCategory(string $category): bool
    {
        return \in_array($category, $this->categories, true);
    }

    /**
     * A deterministic textual signature of all filter values, used as a stable cache key.
     * Order-insensitive on arrays (sorts each axis) so equivalent filters collide.
     */
    public function cacheSignature(): string
    {
        $reading = array_map(static fn (ReadingStatus $s): string => $s->value, $this->readingStatuses);
        sort($reading);

        $purchase = array_map(static fn (PurchaseStatus $s): string => $s->value, $this->purchaseStatuses);
        sort($purchase);

        $categories = $this->categories;
        sort($categories);

        return implode('|', [
            'q=' . $this->query,
            'reading=' . implode(',', $reading),
            'purchase=' . implode(',', $purchase),
            'categories=' . implode(',', $categories),
            'minRating=' . ($this->minRating ?? ''),
            'maxPages=' . ($this->maxPages ?? ''),
            'shelf=' . ($this->shelfId ?? ''),
            'sort=' . $this->sort,
            'page=' . $this->page,
        ]);
    }

    /**
     * Current state as a query-parameters array, optionally with one value removed
     * (used for the "remove this filter" chips and for pagination links).
     *
     * @return array<string, mixed>
     */
    public function toQueryParams(): array
    {
        $params = [];
        if ($this->query !== '') {
            $params['q'] = $this->query;
        }
        if ($this->readingStatuses !== []) {
            $params['reading'] = array_map(static fn (ReadingStatus $s): string => $s->value, $this->readingStatuses);
        }
        if ($this->purchaseStatuses !== []) {
            $params['purchase'] = array_map(static fn (PurchaseStatus $s): string => $s->value, $this->purchaseStatuses);
        }
        if ($this->categories !== []) {
            $params['category'] = $this->categories;
        }
        if ($this->minRating !== null) {
            $params['minRating'] = $this->minRating;
        }
        if ($this->maxPages !== null) {
            $params['maxPages'] = $this->maxPages;
        }
        if ($this->shelfId !== null) {
            $params['shelf'] = $this->shelfId;
        }
        if ($this->sort !== 'recent') {
            $params['sort'] = $this->sort;
        }

        return $params;
    }
}
