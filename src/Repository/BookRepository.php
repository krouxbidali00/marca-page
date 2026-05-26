<?php

namespace App\Repository;

use App\Dto\LibraryFilter;
use App\Entity\Book;
use App\Entity\Shelf;
use App\Entity\User;
use App\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 24;

    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(service: 'cache.library')]
        private readonly TagAwareCacheInterface $libraryCache,
        #[Autowire(service: 'cache.book_detail')]
        private readonly TagAwareCacheInterface $bookDetailCache,
    ) {
        parent::__construct($registry, Book::class);
    }

    /**
     * @return Page<Book>
     */
    public function paginateForLibrary(User $owner, LibraryFilter $filter): Page
    {
        $key = sprintf('v1.lib.%d.%s', (int) $owner->getId(), hash('xxh128', $filter->cacheSignature()));

        return $this->libraryCache->get($key, function (ItemInterface $item) use ($owner, $filter): Page {
            $item->expiresAfter(3600);
            $item->tag(['user.' . (int) $owner->getId() . '.library']);

            return $this->doPaginateForLibrary($owner, $filter);
        });
    }

    public function findCachedForDetail(int $bookId): ?Book
    {
        $key = sprintf('v1.book.%d', $bookId);

        return $this->bookDetailCache->get($key, function (ItemInterface $item) use ($bookId): ?Book {
            $item->expiresAfter(3600);
            $item->tag(['book.' . $bookId]);

            return $this->createQueryBuilder('b')
                ->leftJoin('b.shelf', 's')->addSelect('s')
                ->leftJoin('b.owner', 'o')->addSelect('o')
                ->leftJoin('b.quotes', 'q')->addSelect('q')
                ->where('b.id = :id')->setParameter('id', $bookId)
                ->getQuery()
                ->getOneOrNullResult();
        });
    }

    /**
     * @return Page<Book>
     */
    private function doPaginateForLibrary(User $owner, LibraryFilter $filter): Page
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.shelf', 's')->addSelect('s')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner);

        if ($filter->query !== '') {
            $needle = '%' . mb_strtolower($filter->query) . '%';
            $qb->andWhere('LOWER(b.title) LIKE :q OR LOWER(b.subtitle) LIKE :q OR b.authorsText LIKE :q')
                ->setParameter('q', $needle);
        }

        if ($filter->readingStatuses !== []) {
            $qb->andWhere('b.readingStatus IN (:rs)')->setParameter('rs', $filter->readingStatuses);
        }

        if ($filter->purchaseStatuses !== []) {
            $qb->andWhere('b.purchaseStatus IN (:ps)')->setParameter('ps', $filter->purchaseStatuses);
        }

        if ($filter->minRating !== null) {
            $qb->andWhere('b.rating >= :minRating')->setParameter('minRating', $filter->minRating);
        }

        if ($filter->maxPages !== null) {
            $qb->andWhere('b.pageCount IS NOT NULL AND b.pageCount <= :maxPages')
                ->setParameter('maxPages', $filter->maxPages);
        }

        if ($filter->shelfId !== null) {
            $qb->andWhere('IDENTITY(b.shelf) = :shelfId')->setParameter('shelfId', $filter->shelfId);
        }

        foreach ($filter->categories as $i => $cat) {
            $qb->andWhere(\sprintf('b.categoriesText LIKE :cat%d', $i))
                ->setParameter('cat' . $i, '%' . mb_strtolower($cat) . '%');
        }

        match ($filter->sort) {
            'title_asc' => $qb->orderBy('b.title', 'ASC'),
            'rating' => $qb->orderBy('b.rating', 'DESC')->addOrderBy('b.addedAt', 'DESC'),
            'published' => $qb->orderBy('b.publishedDate', 'DESC')->addOrderBy('b.title', 'ASC'),
            'author' => $qb->orderBy('b.authorsText', 'ASC')->addOrderBy('b.title', 'ASC'),
            default => $qb->orderBy('b.addedAt', 'DESC'),
        };
        $qb->addOrderBy('b.id', 'DESC');

        $qb->setFirstResult(($filter->page - 1) * self::PER_PAGE)->setMaxResults(self::PER_PAGE);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total = \count($paginator);

        return new Page(iterator_to_array($paginator, false), $total, $filter->page, self::PER_PAGE);
    }

    /**
     * Other books from the same library that share an author or category, padded
     * with the most recently added books if there aren't enough matches.
     *
     * @return Book[]
     */
    public function findSimilar(Book $book, int $limit = 3): array
    {
        if ($book->getId() === null || $book->getOwner() === null) {
            return [];
        }

        $matched = [];
        $tokens = array_merge(
            array_map('mb_strtolower', $book->getAuthors()),
            array_map('mb_strtolower', $book->getCategories()),
        );

        if ($tokens !== []) {
            $qb = $this->createQueryBuilder('b')
                ->andWhere('b.owner = :owner')->setParameter('owner', $book->getOwner())
                ->andWhere('b.id != :id')->setParameter('id', $book->getId())
                ->orderBy('b.addedAt', 'DESC')
                ->setMaxResults($limit);

            $or = [];
            foreach (array_values(array_unique($tokens)) as $i => $token) {
                if ($token === '') {
                    continue;
                }
                $or[] = "b.authorsText LIKE :t$i OR b.categoriesText LIKE :t$i";
                $qb->setParameter("t$i", '%' . $token . '%');
            }
            if ($or !== []) {
                $qb->andWhere(implode(' OR ', $or));
                $matched = $qb->getQuery()->getResult();
            }
        }

        if (\count($matched) >= $limit) {
            return \array_slice($matched, 0, $limit);
        }

        $extra = $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')->setParameter('owner', $book->getOwner())
            ->andWhere('b.id != :id')->setParameter('id', $book->getId())
            ->orderBy('b.addedAt', 'DESC')
            ->setMaxResults($limit + \count($matched))
            ->getQuery()->getResult();

        $byId = [];
        foreach ([...$matched, ...$extra] as $b) {
            $byId[$b->getId()] = $b;
        }

        return \array_slice(array_values($byId), 0, $limit);
    }

    /**
     * @return list<Book>
     */
    public function findShelfPreview(Shelf $shelf, int $limit = 4): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.shelf = :shelf')
            ->setParameter('shelf', $shelf)
            ->orderBy('CASE WHEN b.thumbnailUrl IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('b.addedAt', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function sumPages(User $owner): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.pageCount), 0)')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
            ->getQuery()->getSingleScalarResult();
    }

    public function countAddedSince(User $owner, ?\DateTimeImmutable $since): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner);
        if ($since !== null) {
            $qb->andWhere('b.addedAt >= :since')->setParameter('since', $since);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<string,int> ReadingStatus->value => count, all 4 keys present (zero-filled).
     */
    public function countByStatus(User $owner, ?\DateTimeImmutable $since): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.readingStatus AS status, COUNT(b.id) AS c')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
            ->groupBy('b.readingStatus');
        if ($since !== null) {
            $qb->andWhere('b.addedAt >= :since')->setParameter('since', $since);
        }
        $rows = $qb->getQuery()->getResult();

        $counts = ['to_read' => 0, 'reading' => 0, 'finished' => 0, 'abandoned' => 0];
        foreach ($rows as $row) {
            $key = $row['status'] instanceof \App\Enum\ReadingStatus ? $row['status']->value : (string) $row['status'];
            $counts[$key] = (int) $row['c'];
        }
        return $counts;
    }

    public function countRated(User $owner, ?\DateTimeImmutable $since): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
            ->andWhere('b.rating IS NOT NULL');
        if ($since !== null) {
            $qb->andWhere('b.addedAt >= :since')->setParameter('since', $since);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function averageRating(User $owner, ?\DateTimeImmutable $since): ?float
    {
        $qb = $this->createQueryBuilder('b')
            ->select('AVG(b.rating)')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
            ->andWhere('b.rating IS NOT NULL');
        if ($since !== null) {
            $qb->andWhere('b.addedAt >= :since')->setParameter('since', $since);
        }
        $value = $qb->getQuery()->getSingleScalarResult();
        return $value === null ? null : (float) $value;
    }

    /**
     * @return array<int,int> 1..5 => count (all 5 buckets present).
     */
    public function ratingHistogram(User $owner, ?\DateTimeImmutable $since): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.rating AS rating, COUNT(b.id) AS c')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
            ->andWhere('b.rating IS NOT NULL')
            ->groupBy('b.rating');
        if ($since !== null) {
            $qb->andWhere('b.addedAt >= :since')->setParameter('since', $since);
        }
        $hist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($qb->getQuery()->getResult() as $row) {
            $hist[(int) $row['rating']] = (int) $row['c'];
        }
        return $hist;
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    public function topAuthors(User $owner, ?\DateTimeImmutable $since, int $limit): array
    {
        return $this->topFromJsonArray($owner, $since, 'authors', $limit);
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    public function topCategories(User $owner, ?\DateTimeImmutable $since, int $limit): array
    {
        return $this->topFromJsonArray($owner, $since, 'categories', $limit);
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function topFromJsonArray(User $owner, ?\DateTimeImmutable $since, string $field, int $limit): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.' . $field . ' AS items')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner);
        if ($since !== null) {
            $qb->andWhere('b.addedAt >= :since')->setParameter('since', $since);
        }

        $tally = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            foreach ((array) $row['items'] as $value) {
                $name = trim((string) $value);
                if ($name === '') {
                    continue;
                }
                $tally[$name] = ($tally[$name] ?? 0) + 1;
            }
        }
        arsort($tally);
        $top = [];
        foreach (\array_slice($tally, 0, $limit, preserve_keys: true) as $name => $count) {
            $top[] = ['name' => $name, 'count' => $count];
        }
        return $top;
    }

    /**
     * Bucketed series adapted to the period grain:
     *  - All        → 12 monthly buckets, oldest first (current month last)
     *  - CurrentYear → 12 monthly buckets (Jan → Dec of the current year)
     *  - Last30Days → 30 daily buckets, today last
     *
     * @return list<array{label: string, count: int}>
     */
    public function activitySeries(User $owner, \App\Enum\StatsPeriod $period): array
    {
        $now = new \DateTimeImmutable('today');

        if ($period === \App\Enum\StatsPeriod::Last30Days) {
            $start = $now->modify('-29 days');
            $buckets = [];
            for ($i = 0; $i < 30; $i++) {
                $day = $start->modify('+' . $i . ' days');
                $buckets[$day->format('Y-m-d')] = ['label' => $day->format('m-d'), 'count' => 0];
            }
            $rows = $this->createQueryBuilder('b')
                ->select('b.addedAt AS added')
                ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
                ->andWhere('b.addedAt >= :since')->setParameter('since', $start)
                ->getQuery()->getResult();
            foreach ($rows as $row) {
                $key = ($row['added'] instanceof \DateTimeInterface ? $row['added'] : new \DateTimeImmutable((string) $row['added']))->format('Y-m-d');
                if (isset($buckets[$key])) {
                    $buckets[$key]['count']++;
                }
            }
            return array_values($buckets);
        }

        if ($period === \App\Enum\StatsPeriod::CurrentYear) {
            $year = (int) $now->format('Y');
            $start = new \DateTimeImmutable($year . '-01-01 00:00:00');
            $buckets = [];
            for ($m = 1; $m <= 12; $m++) {
                $label = sprintf('%04d-%02d', $year, $m);
                $buckets[$label] = ['label' => $label, 'count' => 0];
            }
            $rows = $this->createQueryBuilder('b')
                ->select('b.addedAt AS added')
                ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
                ->andWhere('b.addedAt >= :since')->setParameter('since', $start)
                ->getQuery()->getResult();
            foreach ($rows as $row) {
                $key = ($row['added'] instanceof \DateTimeInterface ? $row['added'] : new \DateTimeImmutable((string) $row['added']))->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['count']++;
                }
            }
            return array_values($buckets);
        }

        // StatsPeriod::All — 12 monthly buckets, ending with the current month.
        $start = $now->modify('first day of -11 months');
        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->modify('+' . $i . ' months');
            $label = $month->format('Y-m');
            $buckets[$label] = ['label' => $label, 'count' => 0];
        }
        $rows = $this->createQueryBuilder('b')
            ->select('b.addedAt AS added')
            ->andWhere('b.owner = :owner')->setParameter('owner', $owner)
            ->andWhere('b.addedAt >= :since')->setParameter('since', $start)
            ->getQuery()->getResult();
        foreach ($rows as $row) {
            $key = ($row['added'] instanceof \DateTimeInterface ? $row['added'] : new \DateTimeImmutable((string) $row['added']))->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
            }
        }
        return array_values($buckets);
    }
}
