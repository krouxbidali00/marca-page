<?php

namespace App\Dto;

use App\Enum\StatsPeriod;

final readonly class StatsResult
{
    /**
     * @param array<string, int>                       $countByStatus
     * @param array<int, int>                          $ratingHistogram
     * @param list<array{name: string, count: int}>    $topAuthors
     * @param list<array{name: string, count: int}>    $topCategories
     * @param list<array{label: string, count: int}>   $activitySeries
     */
    public function __construct(
        public int $totalBooks,
        public int $totalPages,
        public int $totalQuotes,
        public StatsPeriod $period,
        public int $periodTotal,
        public array $countByStatus,
        public int $totalRated,
        public ?float $averageRating,
        public array $ratingHistogram,
        public array $topAuthors,
        public array $topCategories,
        public array $activitySeries,
    ) {
    }
}
