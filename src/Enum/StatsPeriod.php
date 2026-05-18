<?php

namespace App\Enum;

use Symfony\Component\HttpFoundation\Request;

enum StatsPeriod: string
{
    case All = 'all';
    case Last30Days = '30d';
    case CurrentYear = 'year';

    public static function fromRequest(Request $request): self
    {
        return self::tryFrom((string) $request->query->get('period', 'all')) ?? self::All;
    }

    public function since(): ?\DateTimeImmutable
    {
        return match ($this) {
            self::All => null,
            self::Last30Days => new \DateTimeImmutable('-30 days'),
            self::CurrentYear => new \DateTimeImmutable('first day of January ' . (new \DateTimeImmutable())->format('Y') . ' 00:00:00'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tout',
            self::Last30Days => '30 derniers jours',
            self::CurrentYear => 'Cette année',
        };
    }
}
