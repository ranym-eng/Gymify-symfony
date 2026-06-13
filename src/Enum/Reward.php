<?php

namespace App\Enum;

enum Reward: string
{
    case MEDAILLE = 'MEDAILLE';
    case CERTIFICAT = 'CERTIFICAT';
    case TROPHÉE = 'TROPHÉE';

    public function label(): string
    {
        return match($this) {
            self::MEDAILLE => 'Medal',
            self::CERTIFICAT => 'Certificate',
            self::TROPHÉE => 'Trophy',
        };
    }
}