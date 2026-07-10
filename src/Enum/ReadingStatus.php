<?php

namespace App\Enum;

enum ReadingStatus: string
{
    case ToRead = 'to_read';
    case Reading = 'reading';
    case Finished = 'finished';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::ToRead => 'À lire',
            self::Reading => 'En cours',
            self::Finished => 'Terminé',
            self::Abandoned => 'Abandonné',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::ToRead => 'pill-lavande',
            self::Reading => 'pill-peche',
            self::Finished => 'pill-sauge',
            self::Abandoned => 'pill-outline',
        };
    }

    /**
     * Two-state toggle used by the library card: a finished book becomes "to read"
     * again, and any not-yet-finished state (to read / reading / abandoned) becomes finished.
     */
    public function toggled(): self
    {
        return $this === self::Finished ? self::ToRead : self::Finished;
    }
}
