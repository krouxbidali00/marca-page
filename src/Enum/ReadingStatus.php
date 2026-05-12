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
}
