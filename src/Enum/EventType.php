<?php

namespace App\Enum;

enum EventType: string
{
    case COMPETITION = 'COMPETITION';
    case ENTRAINEMENT = 'ENTRAINEMENT';
    case RANDONNEE = 'RANDONNEE';

    public function label(): string
    {
        return match($this) {
            self::COMPETITION => 'Competition',
            self::ENTRAINEMENT => 'Training',
            self::RANDONNEE => 'Hiking',
        };
    }
}
