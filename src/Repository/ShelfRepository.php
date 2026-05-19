<?php

namespace App\Repository;

use App\Entity\Shelf;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shelf>
 */
class ShelfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shelf::class);
    }

    /**
     * @return array<int, array{shelf: Shelf, bookCount: int}>
     */
    public function listOverviewsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s AS shelf, COUNT(b.id) AS bookCount')
            ->leftJoin('s.books', 'b')
            ->where('s.owner = :user')
            ->setParameter('user', $user)
            ->groupBy('s.id')
            ->orderBy('LOWER(s.name)', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $r): array => ['shelf' => $r['shelf'], 'bookCount' => (int) $r['bookCount']],
            $rows,
        );
    }
}
