<?php

namespace App\Enum;

enum ObjectifCours: string
{
    case PERTE_POIDS = 'PERTE_POIDS';
    case PRISE_DE_MASSE = 'PRISE_DE_MASSE';
    case ENDURANCE = 'ENDURANCE';
    case RELAXATION = 'RELAXATION';

    public function label(): string
    {
        return match($this) {
            self::PERTE_POIDS => 'Perte de poids',
            self::PRISE_DE_MASSE => 'Prise de masse ',
            self::ENDURANCE => 'Endurance',
            self::RELAXATION => 'Relaxation',
        };
    }
    public function iconClass(): string
    {
        return match($this) {
            self::PERTE_POIDS => 'fas fa-weight',
            self::PRISE_DE_MASSE => 'fas fa-dumbbell',
            self::ENDURANCE => 'fas fa-heartbeat',
            self::RELAXATION => 'fas fa-spa',
        };
    }

    
}