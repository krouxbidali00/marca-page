<?php

namespace App\Service;

use App\Dto\StatsResult;
use App\Entity\User;
use App\Enum\StatsPeriod;
use App\Repository\BookRepository;
use App\Repository\QuoteRepository;

final class StatsAggregator
{
    private const TOP_LIMIT = 8;

    public function __construct(
        private readonly BookRepository $books,
        private readonly QuoteRepository $quotes,
    ) {
    }

    public function compute(User $user, StatsPeriod $period): StatsResult
    {
        $since = $period->since();

        return new StatsResult(
            totalBooks: $this->books->count(['owner' => $user]),
            totalPages: $this->books->sumPages($user),
            totalQuotes: $this->quotes->countForUser($user),
            period: $period,
            periodTotal: $this->books->countAddedSince($user, $since),
            countByStatus: $this->books->countByStatus($user, $since),
            totalRated: $this->books->countRated($user, $since),
            averageRating: $this->books->averageRating($user, $since),
            ratingHistogram: $this->books->ratingHistogram($user, $since),
            topAuthors: $this->books->topAuthors($user, $since, self::TOP_LIMIT),
            topCategories: $this->books->topCategories($user, $since, self::TOP_LIMIT),
            activitySeries: $this->books->activitySeries($user, $period),
        );
    }
}
