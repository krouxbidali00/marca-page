<?php

namespace App\Service;

/**
 * Picks a deterministic cover theme (0..COUNT-1) for a book, so books without a
 * Google Books thumbnail get a stable, palette-matching CSS cover.
 */
class CoverThemePicker
{
    public const COUNT = 12;

    public function pick(string $seed): int
    {
        return (int) (crc32($seed) % self::COUNT);
    }
}
